<?php 
/*
*   Members page, used to login. If user have only 
*  one active subscription, redirect them to url
*  elsewhere, redirect to member page
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Member display page
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class LoginController extends Am_Controller_Auth
{
    protected $configBase = 'protect.php_include';
    const NORMAL = 'normal';
    const COOKIE = 'cookie';
    const PLUGINS = 'plugins';

    protected $redirect_url;
    
    // config items
    protected $remember_login = false; // checkbox
    protected $remember_auto = false; // always remember
    protected $remember_period = 60; // days
    /** logout redirect url from config */
    protected $redirect = null; // redirect after logout
    protected $failure_redirect = null; // redirect on failure
    
    public function init()
    {
        $this->loginField = 'amember_login';
        $this->passField  = 'amember_pass';
        parent::init();
        if ($this->getParam('amember_redirect_url'))
            $this->setRedirectUrl($this->getParam('amember_redirect_url'));
        
        if($this->getParam('_amember_redirect_url'))
            $this->setRedirectUrl (base64_decode ($this->getParam('_amember_redirect_url')));
        
        
        $this->remember_login = $this->getDi()->config->get($this->configBase.'.remember_login', false);
        $this->remember_auto = $this->getDi()->config->get($this->configBase.'.remember_auto', false);
        if ($this->remember_auto) $this->remember_login = true;
        $this->remember_period = $this->getDi()->config->get($this->configBase.'.remember_period', 60);
        $this->redirect = $this->getDi()->config->get($this->configBase.'.redirect', false);
    }
    public function getAuth()
    {
        return $this->getDi()->auth;
    }
    public function getHiddenVars()
    {
        $arr = parent::getHiddenVars();
        if ($this->redirect_url)
            $arr['amember_redirect_url'] = $this->redirect_url;
        if ($f = $this->_request->getFiltered('saved_form'))
            $arr['saved_form'] = $f;
        return $arr;
    }
    public function getLogoutUrl()
    {
        return get_first($this->redirect_url, $this->redirect, REL_ROOT_URL, ROOT_URL);
    }
    protected function getConfiguredRedirect()
    {
        $default = REL_ROOT_URL . '/member';
        switch ($this->getDi()->config->get('protect.php_include.redirect_ok', 'first_url'))
        {
            case 'first_url':
                $first = true;
            case 'last_url':
                $resources = $this->getDi()->resourceAccessTable->getAllowedResources($this->getDi()->user, 
                    ResourceAccess::USER_VISIBLE_PAGES);
                if (!$resources) return $default;
                if (empty($first))
                {
                    $resources = array_reverse($resources);
                }
                foreach ($resources as $res)
                {
                    if ($res instanceof File)
                        continue;
                    if (empty($res->hide) && ($url = $res->getUrl()))
                        return $url;
                }
                return $default;
            case 'url':
                return $this->getDi()->config->get('protect.php_include.redirect_ok_url', $default);
            default:
            case 'member': 
                return $default;
        }
    }
    public function getOkUrl()
    {
        return get_first($this->redirect_url, $this->getConfiguredRedirect());
    }
    
    public function indexAction()
    {
        if ($this->getAuth()->getUsername())
            $this->getDi()->hook->call(new Am_Event_AuthSessionRefresh($this->getAuth()->getUser()));

        // if not logged-in and no submit 
        if (!$this->getAuth()->getUserId() && !$this->getLogin())
        {
            $res = $this->getAuth()->checkExternalLogin($this->getRequest());
            if ($res && $res->isValid())
            {
                $this->authResult = $res;
                return $this->onLogin(self::PLUGINS);
            }
        }
        parent::indexAction();
    }
    public function doLogin()
    {
        /// if there is re-captcha enabled, validate it and remove failed_login records if any
        if (($cc = $this->getParam('recaptcha_challenge_field')) 
            && ($rr = $this->getParam('recaptcha_response_field')) 
            && Am_Recaptcha::isConfigured() 
            && $this->getDi()->recaptcha->validate($cc, $rr))
        {
            $this->getAuth()->getProtector()->deleteRecord($this->getRequest()->getClientIp());
        }
        
        $result = parent::doLogin();
        if ($result->getCode() == Am_Auth_Result::USER_NOT_FOUND)
        {
            $event = new Am_Event_AuthTryLogin($this->getLogin(), $this->getPass());
            $this->getDi()->hook->call($event);
            if ($event->isCreated()) // user created, try again!
                $result = parent::doLogin();
        }
        return $result;
    }
    public function onLogin($source = self::NORMAL)
    {
        $user = $this->getAuth()->getUser();
        if ($source == self::NORMAL && $this->remember_login)
            if ($this->remember_auto || $this->getInt('remember_login'))
            {
                $this->setCookie('amember_ru', 
                    $user->login, 
                    $this->getDi()->time+$this->getDi()->config->get($this->configBase.'remember_period',60)*3600*24, '/', null, false, false, true);
                $this->setCookie('amember_rp', 
                    $user->getLoginCookie(), 
                    $this->getDi()->time+$this->getDi()->config->get($this->configBase.'remember_period',60)*3600*24, '/', null, false, false, true);
            }
        return parent::onLogin();
    }
    public function logoutAction()
    {
        $this->setCookie('amember_ru', null, $this->getDi()->time-100*3600*24);
        $this->setCookie('amember_rp', null, $this->getDi()->time-100*3600*24);
        parent::logoutAction();
    }
    /** @return string url to login page */
    public function findLoginUrl()
    {
        $root = REL_ROOT_URL;
        return $root . '/login';
    }
    public function renderLoginPage()
    {
        $showRecaptcha = Am_Recaptcha::isConfigured() && $this->authResult 
            && ($this->authResult->getCode() == Am_Auth_Result::FAILURE_ATTEMPTS_VIOLATION);
        if ($showRecaptcha)
        {
            $recaptcha = $this->getDi()->recaptcha;
        }
        if ($this->isAjax() && $this->getRequest()->isPost())
        {
            $ret = array(
                'ok' => false,
                'error' => @$this->view->error,
                'code' => $this->authResult ? $this->authResult->getCode() : null,
            );
            if ($showRecaptcha)
            {
                $ret['recaptcha_key'] = $recaptcha->getPublicKey();
                $ret['recaptcha_error'] = $recaptcha->getError();
            }
            return $this->ajaxResponse($ret);
        }
        $loginUrl = $this->findLoginUrl();
        
        if ($showRecaptcha)
            $this->view->recaptcha = $recaptcha->render();
        $this->view->assign('form_action', $loginUrl);
        $this->view->assign('this_config', $this->getDi()->config->get($this->configBase));
        if ($this->isAjax()) {
            $this->view->display('_login.phtml');
        } else {
            $this->view->display('login.phtml');
        }
    }
    public function setRedirectUrl($url){
        $redirect_url = parse_url($url);
        
        if(!is_array($redirect_url)) return;
        
        if(array_key_exists('host', $redirect_url) && !$this->getDi()->config->get('other_domains_redirect')){
            $match = false;
            foreach(array(ROOT_URL, ROOT_SURL) as $u){
                $amember_url = parse_url($u);
                if(Am_License::getMinDomain($amember_url['host']) == Am_License::getMinDomain($redirect_url['host']))
                    $match = true;
            }
        } else $match = true;
        if($match) $this->redirect_url = $url;
    }
    public function redirectOk()
    {
        if ($this->isAjax())
        {
            return $this->ajaxResponse(array('ok' => true, 'url' => $this->getOkUrl()));
        }
        return parent::redirectOk();
    }
}