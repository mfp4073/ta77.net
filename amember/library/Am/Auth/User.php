<?php

/**
 */
class Am_Auth_User extends Am_Auth_Abstract
{
    protected $idField = 'user_id';
    protected $loginField = 'login';
    protected $loginType = Am_Auth_BruteforceProtector::TYPE_USER;
    protected $fromCookie = false;
    protected $userClass = 'User';

    protected $plaintextPass = null;
    
    public function getSessionVar()
    {
        if (!$this->session)
            return null;
        return $this->session->user;
    }
    public function setSessionVar(array $row = null)
    {
        $this->session->user = $row;
    }
    protected function authenticate($login, $pass, & $code = null)
    {
        $this->plaintextPass = $this->fromCookie ? null : $pass;
        $code = null;
        $u = $this->fromCookie ?
                $this->getDi()->userTable->getAuthenticatedCookieRow($login, $pass, $code) :
                $this->getDi()->userTable->getAuthenticatedRow($login, $pass, $code);
        if (!$u &&
            !$this->fromCookie &&
            $this->getDi()->config->get('allow_auth_by_savedpass') &&
            ($user = $this->getDi()->userTable->getByLoginOrEmail($login))) {

            foreach($this->getDi()->savedPassTable->findByUserId($user->pk()) as $savedPass) {
                if ($savedPass->checkPassword($pass)) {
                    $u = $user;
                    $code = Am_Auth_Result::SUCCESS;
                    break;
                }
            }
        }
        return $u;
    }
    
    public function onIpCountExceeded(User $user)
    {
        if ($user->is_locked < 0) return; // auto-lock disabled
        if (in_array('email-admin', $this->getDi()->config->get('max_ip_actions',array())))
        {
            $et = Am_Mail_Template::load('max_ip_actions_admin');
            if (!$et)
                throw new Am_Exception_Configuration("No e-mail template found for [max_ip_actions_admin]");
            $et->setMaxipcount($this->getDi()->config->get('max_ip_count',0))
                ->setMaxipperiod($this->getDi()->config->get('max_ip_period',0))
                ->setUser($user);
            if (in_array('disable-user', $this->getDi()->config->get('max_ip_actions', array())))
                $et->setUserlocked(___('Customer account has been automatically locked.'));
            $et->sendAdmin();
        }
        if (in_array('email-user', $this->getDi()->config->get('max_ip_actions',array())))
        {
            $et = Am_Mail_Template::load('max_ip_actions_user');
            if (!$et)
                throw new Am_Exception_Configuration("No e-mail template found for [max_ip_actions_user]");
            $et->setMaxipcount($this->getDi()->config->get('max_ip_count',0))
                ->setMaxipperiod($this->getDi()->config->get('max_ip_period',0))
                ->setUser($user);
            if (in_array('disable-user', $this->getDi()->config->get('max_ip_actions', array())))
                $et->setUserlocked(___('Your account has been automatically locked.'));
            $et->send($user->email);
        }
        if (in_array('disable-user', $this->getDi()->config->get('max_ip_actions', array())))
        {   // disable customer
            $user->lock();
        }
    }
    
    /**  run additional checks on authenticated user */
    public function checkUser($user, $ip)
    {
        /* @var $user User */
        if (!$user->isLocked())
        {
            // now log access and check for account sharing
            $accessLog = $this->getDi()->accessLogTable;
            $accessLog->logOnce($user->user_id, $ip);
            if (($user->is_locked >=0)
                        && $accessLog->isIpCountExceeded($user->user_id, $ip))
            {
                $this->onIpCountExceeded($user);
                $this->setUser(null, $ip);
                return new Am_Auth_Result(Am_Auth_Result::LOCKED);
            }
        } else { // if locked
            $this->setUser(null, $ip);
            return new Am_Auth_Result(Am_Auth_Result::LOCKED);
        }
        if(!$user->isApproved())
            return new Am_Auth_Result(Am_Auth_Result::NOT_APPROVED);
    }
    
    public function onSuccess()
    {
        $this->getDi()->hook->call(
            new Am_Event_AuthAfterLogin($this->getUser(), $this->plaintextPass));
    }
    
    public function logout() {
        if ($this->getUser()) 
            $this->getDi()->hook->call(
                new Am_Event_AuthAfterLogout($this->getUser()));
        return parent::logout();
    }
    public function setFromCookie($flag){
        $this->fromCookie = (bool)$flag;
    }
    static function _setInstance($instance){
        self::$instance = $instance;
    }
    /** @return Am_Auth_User provides fluent interface */
    function requireLogin($redirectUrl = null){
        if (!$this->getUserId()) 
        {
            $front = Zend_Controller_Front::getInstance();
            if (!$front->getRequest()) 
                $front->setRequest(new Am_Request);
            else
                $front->setRequest(clone $front->getRequest());
            $front->getRequest()->setActionName('index');
            if (!$front->getResponse()) $front->setResponse (new Zend_Controller_Response_Http);
            
            require_once APPLICATION_PATH . '/default/controllers/LoginController.php';
            $c = new LoginController(
                    $front->getRequest(),
                    $front->getResponse(),
                    array('di' => Am_Di::getInstance()));
            if ($redirectUrl)
                $c->setRedirectUrl($redirectUrl);
            $c->run();
            
            Zend_Controller_Front::getInstance()->getResponse()->sendResponse();
            exit();
        }else{
            $this->getDi()->accessLogTable->logOnce($this->getUserId());
        }
    }
    /**
     * Once the customer is logged in, check if he has access to given products (links)
     * @throws Am_Exception_InputError if access not allowed
     */
    function checkAccess($productIds, $linkIds=null){
        if (!array_intersect($productIds, $this->getUser()->getActiveProductIds()))
            throw new Am_Exception_AccessDenied("You have no subscription ");
    }
    protected function loadUser()
    {
        $var = $this->getSessionVar();
        $id = $var[$this->idField];
        if ($id < 0) throw new Am_Exception_InternalError("Empty id");
        $user = $this->getDi()->userTable->load($id, false);
        if ($user && $user->data()->get(User::NEED_SESSION_REFRESH))
        {
            $user->data()->set(User::NEED_SESSION_REFRESH, false)->update();
            $this->getDi()->hook->call(new Am_Event_AuthSessionRefresh($user));
        }
        return $user;
    }

    function checkExternalLogin(Am_Request $request){
        // Check cookies
        if ($this->getDi()->config->get('protect.php_include.remember_login', false) 
            && !is_null($request->getCookie('amember_ru')) 
            && !is_null($request->getCookie('amember_rp')))
        {
            $this->setFromCookie(true);
            $authResult = $this->login($request->getCookie('amember_ru'), $request->getCookie('amember_rp'), $request->getClientIp(), false);
            $this->setFromCookie(false);
            if ($authResult->isValid())
                return $authResult;
        }
        /// Check plugins login;
        $e = new Am_Event_AuthCheckLoggedIn();
        $this->getDi()->hook->call($e);
        if ($e->isSuccess())
        {
        
            $errorResult = $this->checkUser($e->getUser(), $request->getClientIp());
            if ($errorResult)
                return;
            $this->setUser($e->getUser(), $request->getClientIp());
            $this->onSuccess();
            return new Am_Auth_Result(Am_Auth_Result::SUCCESS);
        }

    }
}