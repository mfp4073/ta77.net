<?php

/**
 * abstract controller to handle customer autentication
 * @package Am_Auth 
 */
abstract class Am_Controller_Auth extends Am_Controller
{
    protected $configBase = null;
    protected $urlLogin = null;

    protected $loginField = 'login';
    protected $passField = 'pass';
    
    /** @var Am_Auth_Result */
    protected $authResult;

    /** @return Am_Auth_Abstract */
    abstract function getAuth();
    /** @return null */
    abstract public function renderLoginPage();

    public function onLogin(){
        return $this->redirectOk();
    }
    public function _notexistingAction()
    {
        return $this->indexAction();
    }

    public function logoutAction(){
        $this->getAuth()->logout();
        unset($this->getSession()->signup_member_id);
        unset($this->getSession()->signup_member_login);
        $this->redirectLogout();
    }
    public function getLogin()
    {
        // no, reject _GET
        $login = $this->_request->isPost() ? $this->_request->get($this->loginField) : "";
        return preg_replace('/[^a-zA-Z0-9 _.@-]/', '', $login);
    }
    public function getPass()
    {
        ///no do not accept query for login, 
        return $this->isPost()? $this->getParam($this->passField) : null;
    }
    public function indexAction()
    {
        if (null != $this->getAuth()->getUsername())
            return $this->redirectOk();
        $login = $this->getLogin();
        $pass  = $this->getPass();
        if (strlen($login) && strlen($pass))
        {
            $this->authResult = $this->doLogin();
            if ($this->authResult->isValid()) {
                return $this->onLogin();
            } else
                $this->view->error = array($this->authResult->getMessage());
        } else {
            if ($this->_request->isPost())
                $this->view->error = array(___("Please enter username and password"));
        }
        $this->view->{$this->loginField} =  $login;
        $this->view->hidden = $this->getHiddenVars();
        $this->renderLoginPage();
    }
    /**
     * @return array of key=>value to pass between requests
     */
    public function getHiddenVars()
    {
        return array(
            'login_attempt_id' => time(),
        );
    }
    /** @return Am_Auth_Result */
    public function doLogin()
    {
        return $this->getAuth()->login($this->getLogin(), $this->getPass(), $this->_request->getClientIp(), false, $this->getInt('login_attempt_id'));
    }
    public function filterUrl($url)
    {
        return strip_tags($url);
    }
    abstract public function getLogoutUrl();
    abstract public function getOkUrl();
    public function redirectOk()
    {
        $this->redirectLocation($this->filterUrl($this->getOkUrl()));
    }
    public function redirectLogout(){
        $this->redirectLocation($this->filterUrl($this->getLogoutUrl()));
    }
}