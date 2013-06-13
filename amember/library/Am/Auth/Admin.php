<?php

$badWords = array('script', 'onabort', 'onactivate',
    'onafterprint', 'onafterupdate', 'onbeforeactivate', 'onbeforecopy',
    'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus', 'onbeforepaste',
    'onbeforeprint', 'onbeforeunload', 'onbeforeupdate', 'onblur', 'onbounce',
    'oncellchange', 'onchange', 'onclick', 'oncontextmenu', 'oncontrolselect',
    'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged', 'ondatasetcomplete',
    'ondblclick', 'ondeactivate', 'ondrag', 'ondragdrop', 'ondragend', 'ondragenter',
    'ondragleave', 'ondragover', 'ondragstart', 'ondrop', 'onerror', 'onerrorupdate',
    'onfilterupdate', 'onfinish', 'onfocus', 'onfocusin', 'onfocusout', 'onhelp', 'onkeydown',
    'onkeypress', 'onkeyup', 'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown',
    'onmouseenter', 'onmouseleave', 'onmousemove', 'onmoveout', 'onmouseover', 'onmouseup',
    'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange',
    'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart', 'onrowexit',
    'onrowsdelete', 'onrowsinserted', 'onscroll', 'onselect', 'onselectionchange', 'onselectstart',
    'onstart', 'onstop', 'onsubmit', 'onunload');
foreach ($_GET as $k => $v)
    if (@preg_match('/\b'.join('|', $badWords).'\b/', $v))
       die('Bad word detected in GET parameter, access deined');

class Am_Auth_Admin extends Am_Auth_Abstract
{
    const PERM_SETUP = 'setup';
    const PERM_BACKUP_RESTORE = 'backup_restore';
    const PERM_REPORT = 'report';
    const PERM_IMPORT = 'import';
    const PERM_EMAIL = 'email';
    const PERM_LOGS = 'logs'; //Error Logs
    const PERM_LOGS_ACCESS = 'logs_access';
    const PERM_LOGS_INVOICE = 'logs_invoice';
    const PERM_LOGS_MAIL = 'logs_mail';
    const PERM_LOGS_ADMIN = 'logs_admin';
    const PERM_SUPER_USER = 'super_user'; // this cannot be assigned to "perms"
    
    protected $permissions = array();
    protected $idField = 'admin_id';
    protected $loginField = 'login';
    protected $loginType = Am_Auth_BruteforceProtector::TYPE_ADMIN;
    protected $userClass = 'Admin';

    static protected $instance;

    public function getSessionVar()
    {
        return $this->session->admin;
    }
    public function setSessionVar(array $row = null)
    {
        $this->session->admin = $row;
    }
    protected function authenticate($login, $pass, & $code = null)
    {
        return Am_Di::getInstance()->adminTable->getAuthenticatedRow($login, $pass, $code);
    }
    /**
     * Make sure session has the same browser and is not expired
     * @todo implement checksession
     */
    public function checkSession()
    {
        
    }
    public function onSuccess()
    {
        $user = $this->getUser();
        if ($user && $user->last_session != Zend_Session::getId()) 
        {
            $ip = $this->getDi()->request->getClientIp();
            $user->last_ip = preg_replace('/[^0-9\.]+/', '', $ip);
            $user->last_login = $this->getDi()->sqlDateTime;
            $user->last_session = Zend_Session::getId();
            $user->updateSelectedFields(array('last_ip', 'last_login', 'last_session'));
        }
        $this->getDi()->adminLogTable->log('Logged in');
        $this->session->setExpirationSeconds(3600*2);
    }
    public function logout()
    {
        if ($this->getUserId())
            $this->getDi()->adminLogTable->log('Logged out');
        return parent::logout();
    }
    protected function loadUser()
    {
        $var = $this->getSessionVar();
        $id = $var[$this->idField];
        if ($id < 0) throw new Am_Exception_InternalError("Empty id");
        return Am_Di::getInstance()->adminTable->load($id);
    }
    
    function getPermissionsList(){
        if (empty($this->permissions))
        {
            $this->permissions = array();
            foreach (array('_u'  => ___('Users'),
                           '_invoice' => ___('Invoices'), 
                           '_payment' => ___('Payments'), 
                           '_product' => ___('Products'), 
                           '_content' => ___('Content'), 
                           '_coupon' => ___('Coupons'), 
                        ) as $k => $v)
                $this->permissions['grid'.$k] = array(
                    '__label' => $v,
                    'browse' => ___('Browse'),
                    'edit'   => ___('Edit'),
                    'insert' => ___('Insert'),
                    'delete' => ___('Delete'),
                    'export' => ___('Export'),
                );

            $this->permissions['grid_u']['merge'] = ___('Merge');
            
            $this->permissions = array_merge($this->permissions, array(
                self::PERM_EMAIL => ___('Send E-Mail Messages'),
                self::PERM_SETUP => ___('Change Configuration Settings'),
                self::PERM_BACKUP_RESTORE => ___('Download backup / Restore from backup'),
                self::PERM_REPORT => ___('Run Reports'),
                self::PERM_IMPORT => ___('Import'),
                self::PERM_LOGS => ___('Logs: Errors'),
                self::PERM_LOGS_ACCESS => ___('Logs: Access'),
                self::PERM_LOGS_INVOICE => ___('Logs: Invoice'),
                self::PERM_LOGS_MAIL => ___('Logs: Mail Queue'),
                self::PERM_LOGS_ADMIN => ___('Logs: Admin Log'),
            ));        
            $event = Am_Di::getInstance()->hook->call(Am_Event::GET_PERMISSIONS_LIST);
            foreach ($event->getReturn() as $k => $v)
                $this->permissions[$k] = $v;
        }
        return $this->permissions;
    }
    
}

