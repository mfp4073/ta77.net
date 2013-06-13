<?php
/**
 * User authentication
 * @package Am_Auth
 */

/**
 * Class handles authentication and storage of authentication data in the session
 * @package Am_Auth
 */
abstract class Am_Auth_Abstract {
    /**
     * Session
     * @var Zend_Session_Namespace
     */
    public $session;
    /**
     * User record
     * @var User
     */
    protected $user;

    /** @var string - must be overriden ! */
    protected $userClass = null;

    protected $configPrefix="";
    /**
     * @var Am_Auth_BruteforceProtector
     */
    protected $protector;
    /** @var Am_Di */
    protected $di;

    protected $idField = null;
    protected $loginField = null;
    protected $loginType = null; // for example: Am_Auth_BrutefoceProtector::TYPE_USER;

    public function __construct(Zend_Session_Namespace $session, Am_Di $di)
    {
        $this->session = $session;
        $this->di = $di;
    }
    /** @return Am_Di */
    protected function getDi()
    {
        return $this->di;
    }

    /**
     * Set user variable to session
     */
    abstract protected function setSessionVar(array $record = null);
    /**
     * Get user variable from session
     * @return array|null
     */
    abstract protected function getSessionVar();
    /**
     * Authenticate agains database record and return record or null
     * sets $code to Am_Auth_Result code
     * @return Zend_Table_Row|null
     */
    abstract protected function authenticate($login, $pass, & $code = null);
    
    /**
     * Load user based on @link getSesisonVar()
     * @return Am_Record
     */
    abstract protected function loadUser();

    /**
     * lazy-load
     */
    public function getProtector()
    {
        if (null == $this->protector)
        {
            $this->protector = new Am_Auth_BruteforceProtector(
                $this->getDi()->db, 
                $this->getDi()->config->get($this->configPrefix.'bruteforce_count', 5),
                $this->getDi()->config->get($this->configPrefix.'bruteforce_delay', 120),
                $this->loginType);
        }
        return $this->protector;
    }
    /**
     * For testing only
     * @access private
     */
    public function _setProtector(Am_Auth_BruteforceProtector $protector=null)
    {
        $this->protector = $protector;
    }
    
    public function logout()
    {
        //Zend_Session::regenerateId();
        $this->user = null;
        $this->setSessionVar(null);
    }
    
    /**
     * Logs-in customer by given username and password
     * @param string Username
     * @param string Password
     * @param string IP
     * @return Am_Auth_Result
     */
    public function login($login, $pass, $ip, $disableProtector = false, $loginAttemptId=null)
    {
        //Zend_Session::regenerateId();
        $this->setUser(null, null);
        
        $login = preg_replace('/[^a-zA-Z0-9 _.@-]/ms', '', $login);
        if (!strlen($login) || !strlen($pass))
        {
            return new Am_Auth_Result(Am_Auth_Result::INVALID_INPUT);
        }
        if ($loginAttemptId)
            if (@in_array($loginAttemptId, @$this->session->login_attempt_id))
                return new Am_Auth_Result(Am_Auth_Result::INVALID_INPUT,
                        ___('Session expired, please enter username and password again'));
        ///
        if (!$disableProtector) {
            $bp = $this->getProtector();

            $wait = $bp->loginAllowed($ip, $login);
            if (null !== $wait)
            { // this customer have to wait before next attempt
                $fail = new Am_Auth_Result(Am_Auth_Result::FAILURE_ATTEMPTS_VIOLATION,
                    ___('Please wait %d seconds before next login attempt', $wait));
                $fail->wait = $wait;
                return $fail;
            }
        }
        $code = null;
        $user = $this->authenticate($login, $pass, $code);
        if ($user && ($code == Am_Auth_Result::SUCCESS))
        {
            $newResult = $this->checkUser($user, $ip, $code);
            if ($newResult) return $newResult; // as returned from checkUser()
            $this->setUser($user, $ip, $code);
            $this->onSuccess();
            if ($loginAttemptId)
                if (isset($this->session->login_attempt_id))
                    $this->session->login_attempt_id[] = $loginAttemptId;
                else
                    $this->session->login_attempt_id = array($loginAttemptId);
        } else {
            if (!$disableProtector)
                $bp->reportFailure($ip, $login);
        }
        return new Am_Auth_Result($code);    
    }
    
    /**
     * @return null|Am_Auth_Result returns $result in case of error, null if all OK
     */
    public function checkUser($user, $ip)
    {
    }
    
    /**
     * additional actions to execute once user is authenticated and written to session
     */
    public function onSuccess()
    {
    }

    /**
     * Return username of currently logged-in
     * customer or null
     *
     * @return string|null
     */
    public function getUsername()
    {
        $u = $this->getSessionVar();
        return is_null($u) ? null : $u[$this->loginField];
    }
    
    /**
     * Return id of the logged-in customer
     * @return integer|null
     */
    public function getUserId()
    {
        $u = $this->getSessionVar();
        return is_null($u) ? null : $u[$this->idField];
    }
    
    
    /**
     * Return user object of currently logged-in
     * customer, or null
     *
     * @return null
     */
    public function getUser($refresh=false)
    {
        if (null == $this->getSessionVar())
            return null;
        if (!isset($this->user) || $refresh)
            $this->user = $this->loadUser();
        return $this->user;            
    }
    public function setUser($user, $ip=null)
    {
        $this->user = $user;
        $this->setSessionVar($user ? $user->toArray() : null);
        return $this;
    }
 
}