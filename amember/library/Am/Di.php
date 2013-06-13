<?php

/**
 * Dependency injector - holds references to "global" objects
 * @package Am_Utils
 * @property Am_Interval $interval
 * @property DbSimple_MyPdo $db database
 * @property Am_Crypt $crypt crypt class
 * @property Am_Hook $hook hook manager
 * @property Am_Blocks $blocks blocks - small template pieces to insert
 * @property Am_Config $config configuration
 * @property Am_Auth_User $auth user-side authentication
 * @property User $user currently authenticated customer or throws exception if no auth
 * @property Am_Auth_Admin $authAdmin admin-side authentication
 * @property Am_Paysystem_List $paysystemList list of paysystems
 * @property Am_Store $store permanent data storage
 * @property Am_StoreRebuild $storeRebuild permanent data storage
 * @property Am_Upload_Acl $uploadAcl upload acl list
 * @property Am_Recaptcha $recaptcha Re-Captcha API
 * @property Am_Navigation_UserTabs $navigationUserTabs User Tabs in Admin CP
 * @property Am_Navigation_UserTabs $navigationAdmin Admin Menu
 * @property Am_Navigation_UserTabs $navigationUser Member Page menu
 * @property Am_Theme $theme User-side theme 
 * @property array $viewPath paths to templates
 * @property ArrayObject $plugins modules, misc, payment, protect,storage
 * @property Am_Plugins $modules
 * @property Am_Plugins $plugins_protect
 * @property Am_Plugins $plugins_payment
 * @property Am_Plugins $plugins_misc
 * @property Am_Plugins $plugins_tax (new plugins to this array may be added via "misc" plugins)
 * @property Am_Plugins_Storage $plugins_storage
 * @property array $languagesListUser list of available languages -> their self-names
 * @property array $languagesListAdmin list of available languages -> their self-names
 * @property Zend_Cache_Backend $cacheBackend cache backend
 * @property Zend_Cache_Core $cache cache
 * @property Zend_Cache_Frontend_Function $cacheFunction cache function call results
 * @property Am_App $app application-specific routines
 * @property Am_Locale $locale locale
 * @property Am_Request $request current request
 * @property Am_View $view view object (not shared! each call returns new instance)
 * @property Am_Mail $mail mail object (not shared! each call returns new instance)
 
 * @property int $time current time (timestamp)
 * @property string $sqlDate current date in SQL format yyyy-mm-dd
 * @property string $sqlDateTime current datetime in SQL format yyyy-mm-dd hh:ii:ss
 * 
 * @property DateTime $dateTime current DateTime object with default timezone (created from @link time)
 * 
 * /// tables
 * @property AccessLogTable $accessLogTable
 * @property AccessTable $accessTable
 * @property AdminLogTable $adminLogTable
 * @property AdminTable $adminTable
 * @property BanTable $banTable
 * @property BillingPlanTable $billingPlanTable
 * @property CcRecordTable $CcRecordTable
 * @property CcRebillTable $ccRebillTable
 * @property CountryTable $countryTable
 * @property CouponBatchTable $couponBatchTable
 * @property CouponTable $couponTable
 * @property CurrencyExchangeTable $currencyExchangeTable
 * @property EmailSentTable $emailSentTable
 * @property EmailTemplateTable $emailTemplateTable
 * @property ErrorLogTable $errorLogTable
 * @property FileTable $fileTable
 * @property FileDownloadTable $fileDownloadTable
 * @property FolderTable $folderTable
 * @property IntegrationTable $integrationTable
 * @property InvoiceItemTable $invoiceItemTable
 * @property InvoiceLogTable $invoiceLogTable
 * @property InvoicePaymentTable $invoicePaymentTable
 * @property InvoiceRefundTable $invoiceRefundTable
 * @property InvoiceTable $invoiceTable
 * @property LinkTable $linkTable
 * @property MailQueueTable $mailQueueTable
 * @property PageTable $pageTable
 * @property ProductCategoryTable $productCategoryTable
 * @property ProductTable $productTable
 * @property ProductUpgradeTable $productUpgradeTable
 * @property ResourceAccessTable $resourceAccessTable
 * @property SavedFormTable $savedFormTable
 * @property SavedReportTable $savedReportTable
 * @property SavedPassTable $savedPassTable
 * @property StateTable $stateTable
 * @property TranslationTable $translationTable
 * @property UploadTable $uploadTable
 * @property UserGroupTable $userGroupTable
 * @property UserStatusTable $userStatusTable
 * @property UserTable $userTable
 * /// affiliate module tables
 * @property AffBannerTable $affBannerTable
 * @property AffClickTable $affClickTable
 * @property AffCommissionRuleTable $affCommissionRuleTable
 * @property AffCommissionTable $affCommissionTable
 * @property AffLeadTable $affLeadTable
 * @property AffPayoutDetailTable $affPayoutDetailTable
 * @property AffPayoutTable $affPayoutTable
 * // helpdesk
 * @property HelpdeskMessageTable $helpdeskMessageTable
 * @property HelpdeskTicketTable $helpdeskTicketTable
 * @property HelpdeskSnippetTable $helpdeskSnippetTable
 * @property HelpdeskFaqTable $helpdeskFaqTable
 * // newsletter
 * @property NewsletterListTable $newsletterListTable
 * @property NewsletterUserSubscriptionTable $newsletterUserSubscriptionTable
 * 
 * @property-read Access $accessRecord creates new record on each access!
 * @property-read AccessLog $accessLogRecord creates new record on each access!
 * @property-read Admin $adminRecord creates new record on each access!
 * @property-read AdminLog $adminLogRecord creates new record on each access!
 * @property-read AffBanner $affBannerRecord creates new record on each access!
 * @property-read AffClick $affClickRecord creates new record on each access!
 * @property-read AffCommission $affCommissionRecord creates new record on each access!
 * @property-read AffCommissionRule $affCommissionRuleRecord creates new record on each access!
 * @property-read AffLead $affLeadRecord creates new record on each access!
 * @property-read AffPayout $affPayoutRecord creates new record on each access!
 * @property-read AffPayoutDetail $affPayoutDetailRecord creates new record on each access!
 * @property-read Ban $banRecord creates new record on each access!
 * @property-read BillingPlan $billingPlanRecord creates new record on each access!
 * @property-read CcRecord $CcRecordRecord creates new record on each access!
 * @property-read CcRebill $ccRebillRecord creates new record on each access!
 * @property-read Country $countryRecord creates new record on each access!
 * @property-read Coupon $couponRecord creates new record on each access!
 * @property-read CouponBatch $couponBatchRecord creates new record on each access!
 * @property-read CurrencyExchange $currencyExchangeRecord creates new record on each access!
 * @property-read EmailSent $emailSentRecord creates new record on each access!
 * @property-read EmailTemplate $emailTemplateRecord creates new record on each access!
 * @property-read ErrorLog $errorLogRecord creates new record on each access!
 * @property-read File $fileRecord creates new record on each access!
 * @property-read FileDownload $fileDownloadRecord creates new record on each access!
 * @property-read Folder $folderRecord creates new record on each access!
 * @property-read HelpdeskMessage $helpdeskMessageRecord creates new record on each access!
 * @property-read HelpdeskTicket $helpdeskTicketRecord creates new record on each access!
 * @property-read HelpdeskSnippet $helpdeskSnippetRecord creates new record on each access!
 * @property-read HelpdeskFaq $helpdeskFaqRecord creates new record on each access!
 * @property-read Integration $integrationRecord creates new record on each access!
 * @property-read InviteCampaign $inviteCampaignRecord creates new record on each access!
 * @property-read InviteCode $inviteCodeRecord creates new record on each access!
 * @property-read Invoice $invoiceRecord creates new record on each access!
 * @property-read InvoiceItem $invoiceItemRecord creates new record on each access!
 * @property-read InvoiceLog $invoiceLogRecord creates new record on each access!
 * @property-read InvoicePayment $invoicePaymentRecord creates new record on each access!
 * @property-read InvoiceRefund $invoiceRefundRecord creates new record on each access!
 * @property-read Link $linkRecord creates new record on each access!
 * @property-read MailQueue $mailQueueRecord creates new record on each access!
 * @property-read NewsletterList $newsletterListRecord creates new record on each access!
 * @property-read NewsletterUserSubscription $newsletterUserSubscriptionRecord creates new record on each access!
 * @property-read Page $pageRecord creates new record on each access!
 * @property-read Product $productRecord creates new record on each access!
 * @property-read ProductCategory $productCategoryRecord creates new record on each access!
 * @property-read ProductUpgrade $productUpgradeRecord creates new record on each access!
 * @property-read ResourceAbstract $resourceAbstractRecord creates new record on each access!
 * @property-read ResourceAccess $resourceAccessRecord creates new record on each access!
 * @property-read SavedForm $savedFormRecord creates new record on each access!
 * @property-read SavedReport $savedReportRecord creates new record on each access!
 * @property-read SavedPass $savedPassRecord creates new record on each access!
 * @property-read State $stateRecord creates new record on each access!
 * @property-read Translation $translationRecord creates new record on each access!
 * @property-read Upload $uploadRecord creates new record on each access!
 * @property-read User $userRecord creates new record on each access!
 * @property-read UserGroup $userGroupRecord creates new record on each access!
 * @property-read UserStatus $userStatusRecord creates new record on each access!
 * 
 */
class Am_Di extends sfServiceContainerBuilder
{
    static $instance;
    
    function init()
    {
        $this->register('crypt', 'Am_Crypt_Strong')
            ->addMethodCall('checkKeyChanged');
        $this->register('hook', 'Am_Hook')
            ->addArgument($this->getService('service_container'));
        $this->register('config', 'Am_Config')
            ->addMethodCall('read');
        $this->register('paysystemList', 'Am_Paysystem_List')
            ->addArgument(new sfServiceReference('service_container'));
        $this->register('store', 'Am_Store');
        $this->register('storeRebuild', 'Am_StoreRebuild');
        $this->register('uploadAcl', 'Am_Upload_Acl');
        $this->register('recaptcha', 'Am_Recaptcha');
        $this->register('request', 'Am_Request');
        $this->register('mail', 'Am_Mail');
        $this->register('view', 'Am_View')
            ->addArgument(new sfServiceReference('service_container'))
            ->setShared(false);
        $this->register('blocks', 'Am_Blocks');
        $this->register('navigationUserTabs', 'Am_Navigation_UserTabs')
            ->addMethodCall('addDefaultPages');
        $this->register('navigationUser', 'Am_Navigation_User')
            ->addMethodCall('addDefaultPages');
        $this->register('navigationAdmin', 'Am_Navigation_Admin')
            ->addMethodCall('addDefaultPages');
        
        $this->register('invoice', 'Invoice')->setShared(false);
        
        $this->setServiceDefinition('TABLE', new sfServiceDefinition('Am_Table',
            array(new sfServiceReference('db'))))
            ->addMethodCall('setDi', array($this));
        $this->setServiceDefinition('RECORD', new sfServiceDefinition('Am_Record'))
            ->setShared(false); // new object created on each access !

        $this->setServiceDefinition('modules', new sfServiceDefinition('Am_Plugins', 
            array(new sfServiceReference('service_container'),
                'modules', APPLICATION_PATH, 'Bootstrap_%s', '%2$s', array('%s/Bootstrap.php'))))
            ->addMethodCall('setTitle', array('Enabled Modules'));
        $this->setServiceDefinition('plugins_protect', new sfServiceDefinition('Am_Plugins', 
            array(new sfServiceReference('service_container'), 
                'protect', APPLICATION_PATH . '/default/plugins/protect', 'Am_Protect_%s')))
            ->addMethodCall('setTitle', array('Integration'));
        $this->setServiceDefinition('plugins_payment', new sfServiceDefinition('Am_Plugins', 
            array(new sfServiceReference('service_container'), 
                'payment', APPLICATION_PATH . '/default/plugins/payment', 'Am_Paysystem_%s')))
            ->addMethodCall('setTitle', array('Payment'));
        $this->setServiceDefinition('plugins_misc', new sfServiceDefinition('Am_Plugins', 
            array(new sfServiceReference('service_container'), 
                'misc', APPLICATION_PATH . '/default/plugins/misc', 'Am_Plugin_%s')))
            ->addMethodCall('setTitle', array('Other'));
        $this->setServiceDefinition('plugins_storage', new sfServiceDefinition('Am_Plugins_Storage', 
            array(new sfServiceReference('service_container'), 
                'storage', APPLICATION_PATH . '/default/plugins/storage', 'Am_Storage_%s')))
            ->setFile('Am/Storage.php')
            ->addMethodCall('setTitle', array('File Storage'));
        $this->setServiceDefinition('plugins_tax', new sfServiceDefinition('Am_Plugins_Tax', 
            array(new sfServiceReference('service_container'), 
                'tax', APPLICATION_PATH . '/default/plugins/misc', 'Am_Invoice_Tax_%s')))
            ->setFile('Am/Invoice/Tax.php')
            ->addMethodCall('setTitle', array('Tax Plugins'));
        
        $this->register('cache', 'Zend_Cache_Core')
            ->addArgument(array('lifetime'=>3600, 'automatic_serialization' => true))
            ->addMethodCall('setBackend', array(new sfServiceReference('cacheBackend')));
        $this->register('cacheFunction', 'Zend_Cache_Frontend_Function')
            ->addArgument(array('lifetime'=>3600))
            ->addMethodCall('setBackend', array(new sfServiceReference('cacheBackend')));
        
        $this->register('app', 'Am_App')
            ->addArgument(new sfServiceReference('service_container'));

        $this->register('interval', 'Am_Interval');
    }
    
    function _neverCall_() // expose strings to translation
    {
        ___('Enabled Modules');
        ___('Payment');
        ___('Integration');
        ___('Other');
        ___('File Storage');
    }
    
    public function getService($id)
    {
        if (empty($this->services[$id]))
            switch ($id)
            {
                case 'time':
                    return time();
                case 'sqlDate':
                    return date('Y-m-d', $this->time);
                case 'sqlDateTime':
                    return date('Y-m-d H:i:s', $this->time);
                case 'dateTime':
                    $tz = new DateTimeZone(date_default_timezone_get());
                    $d = new DateTime('@'.$this->time, $tz);
                    $d->setTimezone($tz);
                    return $d;
                case 'plugins':
                    $plugins = new ArrayObject();
                    foreach (array(
                        $this->modules,
                        $this->plugins_payment,
                        $this->plugins_protect,
                        $this->plugins_misc,
                        $this->plugins_storage,
                    ) as $pl)
                        $plugins[$pl->getId()] = $pl;
                    $this->services[$id] = $plugins;
                    return $this->services[$id];
                default:
            }
        return parent::getService($id);
    }
    protected function getUserService()
    {
        $user = $this->getService('auth')->getUser();
        if (empty($user))
            throw new Am_Exception_AccessDenied(___("You must be authorized to access this area"));
        return $user;
    }
    protected function getAuthService()
    {
        if (!isset($this->services['auth']))
        {
            $ns = new Zend_Session_Namespace('amember_auth');
            if (Zend_Session::isWritable() && !empty($this->services['config']))
                $ns->setExpirationSeconds($this->config->get('login_session_lifetime', 120) * 60);
            $this->services['auth'] = new Am_Auth_User($ns, $this);
        }
        return $this->services['auth'];
    }
    protected function getAuthAdminService()
    {
        if (!isset($this->services['authAdmin']))
        {
            $ns = new Zend_Session_Namespace('amember_admin_auth');
            $ns->setExpirationSeconds(3600); // admin session timeout is 1 hour
            $this->services['authAdmin'] = new Am_Auth_Admin($ns, $this);
        }
        return $this->services['authAdmin'];
    }
    
    protected function getPluginsService()
    {
        return array(
            'modules' => $this->modules,
            'protect' => $this->plugins_protect,
            'payment' => $this->plugins_payment,
            'misc' => $this->plugins_misc,
            'storage' => $this->plugins_storage,
        );
    }
    public function getDbService()
    {
        static $v;
        if (!empty($v)) return $v;
        $config = $this->getParameter('db');
        try {
            $v = Am_Db::connect($config['mysql']);
        } catch (Am_Exception_Db $e) {
            if (APPLICATION_ENV != 'debug')
                amDie("Error establishing a database connection. Please contact site webmaster if this error does not disappear long time");
            else 
                throw $e;
        }
        return $v;
    }
    
    public function getLanguagesListUserService()
    {
        return $this->cacheFunction->call(array('Am_Locale','getLanguagesList'), array('user'));
    }
    public function getLanguagesListAdminService()
    {
        return $this->cacheFunction->call(array('Am_Locale','getLanguagesList'), array('admin'));
    }

    public function getCacheBackendService()
    {
        if (!isset($this->services['cacheBackend']))
        {
            $fileBackendOptions = array('cache_dir' => DATA_DIR . '/cache');
            if (extension_loaded('xcache') && ini_get('xcache.var_size')>0)
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('Xcache', array());
            elseif (is_writeable($fileBackendOptions['cache_dir']))
                $this->services['cacheBackend'] = Zend_Cache::_makeBackend('two-levels', array(
                    'slow_backend' => 'File',
                    'slow_backend_options' => $fileBackendOptions,
                    'fast_backend' => new Am_Cache_Backend_Array(),
                    'auto_refresh_fast_cache' => true,
                 ));
            else
                $this->services['cacheBackend'] = new Am_Cache_Backend_Null();
        }
        return $this->services['cacheBackend'];
    }
    
    function getViewPathService()
    {
        if (!isset($this->services['viewPath']))
        {
            if (APPLICATION_ENV == 'debug')
                $theme = $this->request->getFiltered('theme');
            if (empty($theme))
                $theme = $this->config->get('theme', 'default');
            
            if (APPLICATION_ENV == 'debug')
                $admin_theme = $this->request->getFiltered('admin_theme');
            if (empty($admin_theme))
                $admin_theme = $this->config->get('admin_theme', 'default');

            $ret = array(
                APPLICATION_PATH . '/default/views/',
            );

            // add module patches now
            foreach ($this->modules->getEnabled() as $module)
            {
                if (file_exists($path = APPLICATION_PATH . '/' . $module . '/views'))
                    $ret[] = $path;
            }

            if (($admin_theme != 'default') && defined('AM_ADMIN') && AM_ADMIN)
                $ret[] = APPLICATION_PATH . '/default/themes-admin/' . $admin_theme;

            if ($theme != 'default' && (!defined('AM_ADMIN') || !AM_ADMIN))
                $ret[] = $this->theme->getRootDir();
            $this->services['viewPath'] = $ret;
        }
        return $this->services['viewPath'];
    }
    
    function getThemeService()
    {
        if (!isset($this->services['theme']))
        {
            $theme = $this->config->get('theme', 'default');
            $admin_theme = $this->config->get('admin_theme', 'default');
            // create theme obj
            if (file_exists($fn = APPLICATION_PATH . '/default/themes/' . $theme . '/Theme.php'))
                include_once $fn;
            $class = class_exists($c = 'Am_Theme_' . ucfirst($theme), false) ? $c : 'Am_Theme';
            $this->services['theme'] = new $class($this, $theme, $this->config->get('themes.'.$theme, array()));
        }
        return $this->services['theme'];
    }
    
    
    
    //// redefines //////////////
    public function getServiceDefinition($id)
    {
        if (empty($this->definitions[$id]) && preg_match('/^([A-Za-z0-9_]+)Table$/', $id, $regs))
        {
            $class = ucfirst($id);
            if (class_exists($class, true) && is_subclass_of($class, 'Am_Table'))
            {
                $def = clone $this->getServiceDefinition('TABLE');
                $def->setClass($class);
                return $def;
            }
        }
        if (empty($this->definitions[$id]) && preg_match('/^([A-Za-z0-9_]+)Record$/', $id, $regs))
        {
            $class = ucfirst($regs[1]);
            if (class_exists($class, true) && is_subclass_of($class, 'Am_Record'))
            {
                $def = clone $this->getServiceDefinition('RECORD');
                $def->setClass($class);
                $def->addArgument(new sfServiceReference($regs[1] . 'Table'));
                return $def;
            }
        }
        return parent::getServiceDefinition($id);
    }
    
    /**
     * That must be last 'getInstance' shortcut in the code !
     * @return Am_Di
     */
    static function getInstance()
    {
        if (empty(self::$instance))
            self::$instance = new self;
        return self::$instance;
    }
    
    /**
     * for unit testing
     * @access private
     */
    static function _setInstance($instance)
    {
        self::$instance = $instance;
    }
}