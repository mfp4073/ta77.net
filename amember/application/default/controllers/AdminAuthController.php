<?php

class Admin_RestorePassForm extends Am_Form_Admin {
    public function init()
    {
        $login = $this->addElement('text', 'login', array('size'=>18))
                ->setLabel(___('Username or E-mail'));
        $login->addRule('callback', ___('User is not found in database'), array($this, 'checkLogin'));
        
        $this->addElement('submit', '', array('value'=>___('Get New Password')));
    }
    
    public function checkLogin($login) {
        $admin = Am_Di::getInstance()->adminTable->findFirstByLogin($login);
        if (!$admin) {
            $admin = Am_Di::getInstance()->adminTable->findFirstByEmail($login);
        }
        
        return (boolean)$admin;
    }
}

class AdminAuthController extends Am_Controller_Auth
{
    protected $loginField = 'login';
    protected $passField = 'passwd';
    const EXPIRATION_PERIOD = 2; //hrs
    const CODE_STATUS_VALID = 1;
    const CODE_STATUS_EXPIRED = -1;
    const CODE_STATUS_INVALID = 0;
    const SECURITY_CODE_STORE_PREFIX ='admin-restore-password-request-';
    
    protected function checkAdminAuthorized() {
        // nop
    }
    public function getAuth()
    {
        return $this->getDi()->authAdmin;
    }
    
    public function changePassAction() 
    {
        $s = $this->getRequest()->getFiltered('s');
        $admin_id = $this->getDi()->store->get(self::SECURITY_CODE_STORE_PREFIX . $s);
        if ($admin_id <= 0)
        {
            $this->view->title = ___('Security code is invalid');
            $root = $this->escape(REL_ROOT_URL);
            $this->view->content = ___('Security code is invalid') . 
                "<br /><a href='$root/admin-auth/send-pass'>".___('Continue')."</a>";
            $this->view->display('admin/layout-login.phtml');
            return;
        }
        $admin = $this->getDi()->adminTable->load($admin_id);
        
        $pass = $this->getDi()->app->generateRandomString(10);
        
        $et = Am_Mail_Template::load('send_password_admin', null, true);
        $et->setUser($admin);
        $et->setPass($pass);
        $et->send($admin);
        $admin->setPass($pass);
        $admin->update();
        $this->getDi()->store->delete(self::SECURITY_CODE_STORE_PREFIX . $s);
        
        $this->view->title = ___('Password changed');
        $root = $this->escape(REL_ROOT_URL);
        $this->view->content = ___('New password has been e-mailed to your e-mail address') . 
            "<br /><a href='$root/admin-auth'>".___('Continue')."</a>";
        $this->view->display('admin/layout-login.phtml');
    }
    
    public function sendPassAction() {
        $form = new Admin_RestorePassForm;
        $this->view->form = $form;
        if ($form->isSubmitted()) {
            $form->setDataSources(array(
                $this->getRequest()
            ));
        }
        if ($this->getRequest()->isPost() && $form->validate()) {
            $login = $this->getRequest()->getParam('login');
            //admin should be found for sure. we already tried it while validating form
            $admin = $this->getDi()->adminTable->findFirstByLogin($login);
            if (!$admin) {
                $admin = $this->getDi()->adminTable->findFirstByEmail($login);
            }
            $this->sendSecurityCode($admin);
            $this->view->message = ___('Link to reset your password was sent to your Email.');
            $this->view->form = null; //do not show form
        } else {
            $this->view->message = ___("Please enter your username or email\n". 
                                       "address. You will receive a link to create\n". 
                                       "a new password via email.");
        }
        
        $this->view->display('admin/send-pass.phtml');
    }
    
    private function sendSecurityCode(Admin $admin)
    {
        $security_code = $this->getDi()->app->generateRandomString(16);
        $securitycode_expire = sqlTime(time() + self::EXPIRATION_PERIOD * 60 * 60);

        $et = Am_Mail_Template::load('send_security_code_admin', null, true);
        $et->setUser($admin);
        $et->setUrl(sprintf('%s/admin-auth/change-pass/?s=%s',
                $this->getDi()->config->get('root_url'),
                $security_code)
        );
        $et->setHours(self::EXPIRATION_PERIOD);
        $et->send($admin);
        $this->getDi()->store->set(
                self::SECURITY_CODE_STORE_PREFIX . $security_code, 
                $admin->pk(), 
                $securitycode_expire
        );
    }
    
    protected function checkUri($uri) {
        //allow only valid uri without parameters.
        $uri = trim(substr($uri, strlen(REL_ROOT_URL)), '/');
        return preg_match('/^[-a-zA-Z0-9]+(\/[-a-zA-Z0-9]*)*$/', $uri);
    }
    
    public function renderLoginPage()
    {
        // only store if GET, nothing already stored, and no params in URL
        if ($this->_request->isGet() && empty($this->getSession()->admin_redirect) &&
            !$this->_request->getQuery() && $this->checkUri($this->_request->getRequestUri()))
        {
            $this->getSession()->admin_redirect = $this->_request->getRequestUri();
        }
        
        if ($this->isAjax()) {
            header("Content-type: text/plain; charset=UTF-8");
            header('HTTP/1.0 402 Admin Login Required');
            $err = "Admin login required";
            echo $this->getJson(array('err' => $err, 'ok' => false));
        } else
            $this->view->display('admin/login.phtml');
    }
    public function getLogoutUrl()
    {
        return REL_ROOT_URL . '/admin/';
    }
    public function getOkUrl()
    {
        $uri = $this->getUriFromSession();
        return $uri ? $uri : REL_ROOT_URL . '/admin/';
    }
    public function redirectOk()
    {
        if ($this->isAjax()) {
            header("Content-type: text/plain; charset=UTF-8");
            header('HTTP/1.0 200 OK');
            echo $this->getJson(array('ok' => true, 'adminLogin' => $this->getAuth()->getUsername()));
        } else
            parent::redirectOk();
    }
    
    protected function getUriFromSession() 
    {
        $uri = $this->getSession()->admin_redirect;
        $this->getSession()->admin_redirect = null;
        return $this->checkUri($uri) ? $uri : null;
    }
}