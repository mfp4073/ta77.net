<?php 

class ProfileController extends Am_Controller
{
    /** @var int */
    protected $user_id;
    /** @var User */
    protected $user;

    protected $saved = false;
    
    const SECURITY_CODE_STORE_PREFIX ='member-verify-email-profile-';
    const SECURITY_CODE_EXPIRE = 48; //hrs
    const EMAIL_CODE_LEN = 10;

    function preDispatch()
    {
        if ($this->getRequest()->getActionName() != 'confirm-email') {
            $this->getDi()->auth->requireLogin(ROOT_URL . '/profile');
            $this->user = $this->getDi()->userTable->load($this->getDi()->auth->getUserId());
            $this->view->assign('user', $this->user->toArray());
            $this->user_id = $this->user->user_id;
        }
    }
    
    function savedAction()
    {
        $this->view->assign('saved', true);
        $this->saved = true;
        $this->indexAction();
    }
    
    function emailChangesToAdmin()
    {
        if(!$this->getDi()->config->get('profile_changed',0))
            return;
        $changed = '';
        $olduser = $this->getDi()->userTable->load($this->user->user_id)->toArray();
        foreach($this->user->toArray() as $k => $v){
            if($k=='pass') continue;
            if(($o=$olduser[$k])!=$v) {
                is_scalar($o) || ($o = serialize($o));
                is_scalar($v) || ($v = serialize($v));
                $changed.="[$k]: old value - $o, new value - $v\n";
            }
        }
        if(!strlen($changed))
            return;
        $et = new Am_Mail_Template();
        $et->setTemplate(EmailTemplate::FORMAT_TEXT, '%site_title% User profile has been changed', 
            "There is an automated message from aMember Pro script. 

User has changed profile.

User details:
    Username:   %user.login%
    Email:      %user.email%
    Name:       %user.name_f% %user.name_l%

Changed fields:
$changed
--
Best Regards,
%site_title%
%root_url%", '', null, 'xx');
        $et->setUser($this->user);
        $et->sendAdmin();
    }

    function indexAction()
    {
        $this->form = new Am_Form_Profile();
        $record = $this->getDi()->savedFormTable->getByType(SavedForm::T_PROFILE);
        if (!$record)
            throw new Am_Exception_Configuration("No profile form configured");
        
        $this->form->initFromSavedForm($record);
        $this->form->setUser($this->user);

        $u = $this->user->toArray();
        $u['visible_in_profile'][3] = 3;
        unset($u['pass']);

        $dataSources = array(
            new HTML_QuickForm2_DataSource_Array($u)
        );

        if ($this->form->isSubmitted()) {
            array_unshift($dataSources, $this->_request);
        }

        $this->form->setDataSources($dataSources);

        if ($this->form->isSubmitted() && $this->form->validate())
        {
            $vars = $this->form->getValue();
            unset($vars['user_id']);
            if (!empty($vars['pass']))
                $this->user->setPass($vars['pass']);
            unset($vars['pass']);
            
            $ve = $this->handleEmail($record, $vars) ? 1 : 0;
            
            $u = $this->user->setForUpdate($vars);
            $this->emailChangesToAdmin();
            $u->update();
            $this->getDi()->auth->setUser($u, '');
            $msg = $ve ? ___('Verification email has been sent to your address.
                    E-mail will be changed in your account after confirmation') :
                    ___('Your profile has been updated successfully');
            return $this->redirectLocation($this->getFullUrl() . '?a=saved&_msg='.  urlencode($msg));
            return $this->redirectLocation($this->getFullUrl() . '?a=saved&ve='.$ve);
        }
        
        $this->view->form = $this->form;
        $this->view->display('member/profile.phtml');
    }
    
    public function confirmEmailAction() {
        /* @var $user User */
        $em = $this->getRequest()->getParam('em');
        list($user_id, $code) = explode(':', $em);
        
        $data = $this->getDi()->store->getBlob(self::SECURITY_CODE_STORE_PREFIX . $user_id);
        if (!$data) {
            throw new Am_Exception_FatalError(___('Security code is invalid'));
        }
        
        $data = unserialize($data);
        $user = $this->getDi()->userTable->load($user_id);
        
        if ($user && //user exist
            $data['security_code'] && //security code exist
            ($data['security_code'] == $code)) {//security code is valid

            $user->email = $data['email'];
            $user->save();
            
            $this->getDi()->store->delete(self::SECURITY_CODE_STORE_PREFIX . $user_id);
            
            $url = $this->getUrl('member', 'index');
            $this->redirectLocation($url);
            exit;
            
        } else {
            throw new Am_Exception_FatalError(___('Security code is invalid'));
        }     
    }
    
    protected function handleEmail(SavedForm $form, & $vars) {
        /* @var $user User */
        $user = $this->user;
        $bricks = $form->getBricks();
        foreach ($bricks as $brick) {
            if ($brick->getClass() == 'email' 
                    && $brick->getConfig('validate')
                    && $vars['email'] != $user->email) {
                
                $code = $this->getDi()->app->generateRandomString(self::EMAIL_CODE_LEN);
                
                $data = array(
                    'security_code' => $code,
                    'email' => $vars['email']
                );
                
                $this->getDi()->store->setBlob(
                    self::SECURITY_CODE_STORE_PREFIX . $this->user_id, 
                    serialize($data), 
                    sqlTime(Am_Di::getInstance()->time + self::SECURITY_CODE_EXPIRE * 3600)
                );
                
                $tpl = Am_Mail_Template::load('verify_email_profile', get_first($user->lang, 
                    Am_Di::getInstance()->app->getDefaultLocale(false)), true);
                
                $cur_email = $user->email;          
                $user->email = $vars['email'];
                
                $tpl->setUser($user);
                $tpl->setCode($code);
                $tpl->setUrl($this->getDi()->config->get('root_surl') . '/profile/confirm-email?em=' . $user->pk() . ':' . $code);
                $tpl->send($user);
                
                $user->email = $cur_email;
                
                unset($vars['email']);
                return true;
            }
        }
        
        return false;
    }
}
