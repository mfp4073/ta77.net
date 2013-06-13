<?php

class Am_Form_Setup_Global extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('global');
        $this->setTitle(___('Global'))
        ->setComment('');
        $this->data['help-id'] = 'Setup/Global';
    }
    function validateCurl($val){
        if (!$val) return;
        exec("$val http://www.yahoo.com/ 2>&1", $out, $return);
        if ($return)
            return "Couldn't execute '$val http://www.yahoo.com/'. Exit code: $return, $out";
    }
    function initElements()
    {
        $this->addElement('text', 'site_title', array (
                'size' => 40,
        ), array('help-id' => '#Setup.2FEdit_Site_Title'))
        ->setLabel(___('Site Title'));
         
         
        $this->addElement('static', null, null, array('help-id' => '#Root_URL_and_License_Key'))->setContent(
                '<div><a href="' . Am_Controller::escape(REL_ROOT_URL) . '/admin-license" target="_top">'
                . ___('change')
                . '</a></div>')->setLabel(___('Root Url and License Keys'));
         
        $this->addText('flowplayer_license')->setLabel(___("FlowPlayer License Key\nyou may get your key in %smembers area%s",
                '<a href="http://www.amember.com/amember/member?flowplayer_key=1">', '</a>'))
                ->addRule('regex', ___('Value must be alphanumeric'), '/^[a-zA-Z0-9]*$/');

        $this->addElement('select', 'theme', null, array('help-id' => '#Setup.2FEdit_User_Pages_Theme'))
        ->setLabel(___('User Pages Theme'))
        ->loadOptions(Am_View::getThemes('user'));

        $this->addElement('select', 'admin_theme', null, array('help-id' => '#Setup.2FEdit_Admin_Pages_Theme'))
        ->setLabel(___('Admin Pages Theme'))
        ->loadOptions(Am_View::getThemes('admin'));
         
        $this->addSelect('plugins.tax', array('size' => 1))
        ->setLabel(___('Tax'))
        ->loadOptions(array(
                '' => ___('No Tax'),
                'global-tax' => ___('Global Tax'),
                'regional' => ___('Regional Tax'),
                'vat' => ___('EU VAT'),
                'gst' => ___('GST (Inclusive Tax)'),
        ));
         
        /*
         if (!extension_loaded("curl")){
        $el = $this->addElement('text', 'curl')
        ->setLabel(___('cURL executable file location', "you need it only if you are using payment processors<br />
                like Authorize.Net or PayFlow Pro<br />
                usually valid path is /usr/bin/curl or /usr/local/bin/curl"));
        $el->default = '/usr/bin/curl';
        $el->addRule('callback2', 'error', array($this, 'validateCurl'));
        }
        */
        $fs = $this->addElement('fieldset', '##02')
        ->setLabel(___('Signup Form Configuration'));

        //         $this->addElement('advcheckbox', 'generate_login')
        //             ->setLabel(___('Generate Login', 'should aMember generate username for customer?'));

        $this->setDefault('login_min_length', 5);
        $this->setDefault('login_max_length', 16);

        $loginLen = $fs->addGroup(null, null, array('help-id' => '#Setup.2FEdit_Username_Rules'))->setLabel(___('Username length'));
        $loginLen->addInteger('login_min_length', array('size'=>3))->setLabel('min');
        $loginLen->addStatic('')->setContent('<span>&mdash;</span>');
        $loginLen->addInteger('login_max_length', array('size'=>3))->setLabel('max');

        $fs->addElement('advcheckbox', 'login_disallow_spaces', null, array('help-id' => '#Setup.2FEdit_Username_Rules'))
        ->setLabel(___('Do not allow spaces in username'));

        $fs->addElement('advcheckbox', 'login_dont_lowercase', null, array('help-id' => '#Setup.2FEdit_Username_Rules'))
        ->setLabel(___("Do not lowercase username\n".
                "by default, aMember automatically lowercases entered username\n".
                "here you can disable this function"));

        //         $fs->addElement('advcheckbox', 'generate_pass')
        //             ->setLabel(___('Generate Password', 'should aMember generate password for customer?'));
        //
        $this->setDefault('pass_min_length', 6);
        $this->setDefault('pass_max_length', 25);
        $passLen = $fs->addGroup(null, null, array('help-id' => '#Setup.2FEdit_Password_Length'))->setLabel(___('Password Length'));
        $passLen->addInteger('pass_min_length', array('size'=>3))->setLabel('min');
        $passLen->addStatic('')->setContent('<span>&mdash;</span>');
        $passLen->addInteger('pass_max_length', array('size'=>3))->setLabel('max');

        $fs = $this->addElement('fieldset', '##03')
        ->setLabel(___('Miscellaneous'));

        $this->setDefault('admin.records-on-page', 10);
        $fs->addElement('text', 'admin.records-on-page')
        ->setLabel(___('Records per page (for grids)'));


        $this->setDefault('currency', 'USD');
        $currency = $fs->addElement('select', 'currency', array (
                'size' => 1,
        ), array('help-id' => '#Set_Up.2FEdit_Base_Currency'))
        ->setLabel(___("Base Currency\n".
                "base currency to be used for reports and affiliate commission.\n".
                "It could not be changed if there are any invoices in database")
        )
        ->loadOptions(Am_Currency::getFullList());
        if (Am_Di::getInstance()->db->selectCell("SELECT COUNT(*) FROM ?_invoice"))
            $currency->toggleFrozen(true);
    }
}

class Am_Form_Setup_Plugins extends Am_Form_Setup
{
    // list of cc plugins saved for special handling
    protected $plugins_cc = array();
    
    function __construct()
    {
        parent::__construct('plugins');
        $this->setTitle(___('Plugins'))
        ->setComment('');
        $this->data['help-id'] = 'Setup/Plugins';
    }
    function getPluginsList($folders)
    {
        $ret = array();
        foreach ($folders as $folder)
            foreach (scandir($folder) as $f)
            {
                if ($f[0] == '.') continue;
                $path = "$folder/$f";
                if (is_file($path) && preg_match('/^(.+)\.php$/', $f, $regs)) {
                    $ret[ $regs[1] ] = $regs[1];
                } elseif (is_dir($path)) {
                    if (is_file("$path/$f.php"))
                        $ret[$f] = $f;
                }
            }
        ksort($ret);
        return $ret;
    }
    function initElements()
    {
        /* @var $bootstrap Bootstrap */
        $modules =
        $this->addMagicSelect('modules', null, array('help-id' => '#Enabling.2FDisabling_Modules'))
        ->setLabel(___('Enabled Modules'));
        $this->setDefault('modules', array());
         
        foreach (Am_Di::getInstance()->modules->getAvailable() as $module)
        {
            $fn = APPLICATION_PATH . '/' . $module . '/module.xml';
            if (!file_exists($fn)) continue;
            $xml = simplexml_load_file($fn);
            if (!$xml) continue;
            $modules->addOption($module . ' - ' . $xml->desc, $module);
        }
         
        foreach (Am_Di::getInstance()->plugins as $type => $mgr)
        {
            if ($type == 'modules') continue;

            /* @var $mgr Am_Plugins */

            switch($type)
            {
                case 'payment' :
                    $help_id = '#Enabling.2FDisabling_Payment_Plugins';
                    break;
                case 'protect' :
                    $help_id = '#Enabling.2FDisabling_Integration_Plugins';
                    break;
                case 'misc' :
                    $help_id = '#Enabling.2FDisabling_Other_Plugins';
                    break;
                default :
                    $help_id = '';
                    break;
            }

            $el = $this->addMagicSelect('plugins.' . $type, null, array('help-id' => $help_id))
            ->setLabel(___('%s Plugins', ___($mgr->getTitle())));
            
            $paths = $mgr->getPaths();
            $plugins = self::getPluginsList($paths);
            if ($type == 'payment')
            {
                if (!Am_Di::getInstance()->modules->isEnabled('cc'))
                {
                        $this->plugins_cc = self::getPluginsList(array(APPLICATION_PATH . '/cc/plugins'));
                        $plugins = array_merge($plugins, $this->plugins_cc);
                        ksort($plugins);
                }
                
                array_remove_value($plugins, 'free');
                $el->setLabel($el->getLabel() . "\n" . ___("plugins that process credit cards on your website\n".
                        "will appear in the list once you enable [cc] module above"));
            } elseif ($type == 'storage') {
                $plugins = array('upload'=>'upload', 'disk'=>'disk') + $plugins;
            }

            $el->loadOptions($plugins);
        }
        $this->setDefault('plugins.payment', array());
        $this->setDefault('plugins.protect', array());
        $this->setDefault('plugins.misc', array());
        $this->setDefault('plugins.storage', array('upload', 'disk'));
    }
    public function beforeSaveConfig(Am_Config $before, Am_Config $after)
    {
        // Do the same for plugins;
        foreach(Am_Di::getInstance()->plugins as $type => $pm)
        {
            /* @var $pm Am_Plugins */
            $configKey = $type == 'modules' ? 'modules' : ('plugins.'.$type);
            $b = (array)$before->get($configKey);
            $a = (array)$after->get($configKey);
            $enabled = array_filter(array_diff($a, $b), 'strlen');
            $disabled = array_filter(array_diff($b, $a), 'strlen');
            foreach ($disabled as $plugin)
            {
                if ($pm->load($plugin))
                    try {
                    $pm->get($plugin)->deactivate();
                } catch(Exception $e) {
                    Am_Di::getInstance()->errorLogTable->logException($e);
                    trigger_error("Error during plugin [$plugin] deactivation: " . get_class($e). ": " . $e->getMessage(), E_USER_WARNING);
                }
                // Now clean config for plugin;
                $after->set($pm->getConfigKey($plugin), array());
            }
            foreach ($enabled as $plugin)
            {
                if (($type == 'payment') && !empty($this->plugins_cc[$plugin]) 
                    && !Am_Di::getInstance()->modules->isEnabled('cc'))
                { // we got a cc plugin enabled but cc module is not yet enabled!
                    $modules_cc = $after->get('modules', array());
                    $modules_cc[] = 'cc';
                    $modules_cc = array_unique($modules_cc);
                    $after->set('modules', $modules_cc);
                    continue;
                }
                if ($pm->load($plugin))
                {
                    $class = $pm->getPluginClassName($plugin);
                    try {
                        call_user_func(array($class, 'activate'), $plugin, $type);
                    } catch(Exception $e) {
                        Am_Di::getInstance()->errorLogTable->logException($e);
                        trigger_error("Error during plugin [$plugin] activattion: " . get_class($e). ": " . $e->getMessage(),E_USER_WARNING);
                    }
                }
            }
        }
        Am_Di::getInstance()->config->set('modules', $modules = $after->get('modules', array()));
        Am_Di::getInstance()->app->dbSync(true, $modules);
        $after->save();
    }
}

class Am_Form_Setup_Email extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('email');
        $this->setTitle(___('E-Mail'))
        ->setComment('');
        $this->data['help-id'] = 'Setup/Email';
    }

    function checkSMTPHost($val){
        $res = ($val['email_method'] == 'smtp') ?
        (bool)strlen($val['smtp_host']) : true;

        if (!$res) {
            $elements = $this->getElementsByName('smtp_host');
            $elements[0]->setError('SMTP Hostname is required if you have enabled SMTP method');
        }

        return $res;
    }

    function initElements()
    {
        $this->addElement('text', 'admin_email', array (
                'size' => 50,
        ), array('help-id' => '#Email_Address_Configuration'))
        ->setLabel(___("Admin E-Mail Address\n".
                "used to send email notifications to admin\n".
                "and as default outgoing address")
        )
        ->addRule('callback', ___('Please enter valid e-mail address'), array('Am_Validate', 'email'));

        $this->addElement('text', 'admin_email_from', array (
                'size' => 50,
        ), array('help-id' => '#Email_Address_Configuration'))
        ->setLabel(___(
                "Outgoing Email Address\n".
                "used as From: address for sending e-mail messages\n".
                "to customers. If empty, [Admin E-Mail Address] is used"
        ))
        ->addRule('callback', ___('Please enter valid e-mail address'), array('Am_Validate', 'empty_or_email'));

        $this->addElement('text', 'admin_email_name', array (
                'size' => 40,
        ), array('help-id' => '#Email_Address_Configuration'))
        ->setLabel(___(
                "E-Mail Sender Name\n" .
                "used to display name of sender in outgoing e-mails"
        ));

        $fs = $this->addElement('fieldset', '##19')
        ->setLabel(___('E-Mail System Configuration'));

        $fs->addElement('select', 'email_method', null, array('help-id' => '#Email_System_Configuration'))
        ->setLabel(___(
                "Email Sending method\n" .
                "PLEASE DO NOT CHANGE if emailing from aMember works"))
                ->loadOptions(array(
                        'mail' => ___('Internal PHP mail() function (default)'),
                        'smtp' => ___('SMTP'),
                        'ses' => ___('Amazon SES'),
                        'disabled' => ___('Disabled')
                ));

        $fs->addElement('text', 'smtp_host', array('size'=>40), array('help-id' => '#SMTP_Mail_Settings'))
        ->setLabel(___('SMTP Hostname'));
        $this->addRule('callback', ___('SMTP Hostname is required if you have enabled SMTP method'), array($this, 'checkSMTPHost'));
         
        $fs->addElement('integer', 'smtp_port', null,  array('help-id' => '#SMTP_Mail_Settings'))
        ->setLabel(___('SMTP Port'));
        $fs->addElement('select', 'smtp_security', null,  array('help-id' => '#SMTP_Mail_Settings'))
        ->setLabel(___('SMTP Security'))
        ->loadOptions(array(
                ''     => 'None',
                'ssl'  => 'SSL',
                'tls'  => 'TLS',
        ));
        $fs->addElement('text', 'smtp_user', array('size'=>40, 'autocomplete'=>'off'),  array('help-id' => '#SMTP_Mail_Settings'))
        ->setLabel(___('SMTP Username'));
        $fs->addElement('password', 'smtp_pass', array('size'=>40, 'autocomplete'=>'off'),  array('help-id' => '#SMTP_Mail_Settings'))
        ->setLabel(___('SMTP Password'));

        $fs->addElement('text', 'ses_id', array('size'=>40))
        ->setLabel(___('Amazon SES Access Id'));
        $fs->addElement('password', 'ses_key', array('size'=>40))
        ->setLabel(___('Amazon SES Secret Key'));
         
        $test = ___("Test E-Mail Settings");
        $em = ___("E-Mail Address to Send to");
        $se = ___("Send Test E-Mail");
        $fs->addStatic('email_test', null,  array('help-id' => '#Test_Email_Settings'))->setContent(<<<CUT
&nbsp;&nbsp;&nbsp;
<span style="color: red; font-weight: bold;">$test</span>
$em <span class='admin-help'><a href='http://v4.amember.com/docs/Setup/Email#Test_Email_Settings' target='_blank'><sup>?</sup></a></span><input type='text' name='email' size=30 />
<input type='button' name='email_test_send' value='$se' />
<div id='email-test-result' style='display:none'></div>
CUT
        );

        $se = ___("Sending Test E-Mail...");
        $this->addElement('script')->setScript(<<<CUT
$(function(){
    $("#row-email_test-0 .element-title").hide();
    $("#row-email_test-0 .element").css({ 'margin-left' : '0px'});

    $("input[name='email_test_send']").click(function(){
        var btn = $(this);
        var vars = btn.parents('form').serialize();

        var dialogOpts = {
              modal: true,
              bgiframe: true,
              autoOpen: true,
              height: 400,
              width: 600,
              draggable: true,
              resizeable: true
           };

        var savedVal = btn.val();
        btn.val("$se").prop("disabled", "disabled");

        $.post(window.rootUrl + "/admin-email/test", btn.parents("form").serialize(), function(data){
            $("#email-test-result").html(data).dialog(dialogOpts);
            btn.val(savedVal).prop("disabled", "");
        });

    });

    $("#email_method-0").change(function(){
        var is_smtp = $(this).val() == 'smtp';
        $(".row[id*='smtp_']").toggle(is_smtp);
        var is_ses = $(this).val() == 'ses';
        $(".row[id*='ses_']").toggle(is_ses);
    }).change();
});
CUT
        );

        $this->setDefault('email_log_days', 0);
        $fs->addElement('text', 'email_log_days', array (
                'size' => 6,
        ), array('help-id' => '#Outgoing_Messages_Log'))
        ->setLabel(___('Log Outgoing E-Mail Messages for ... days'));

        $fs->addElement('advcheckbox', 'email_queue_enabled', null, array('help-id' => '#Using_the_Email_Throttle_Queue'))
        ->setLabel(___('Use E-Mail Throttle Queue'));
        $fs->addScript()->setScript(<<<CUT
$(function(){
    $("#email_queue_enabled-0").change(function(){
        $("#email_queue_period-0").closest(".row").toggle(this.checked);
        $("#email_queue_limit-0").closest(".row").toggle(this.checked);
    }).change();
});
CUT
        );

        $fs->addElement('select', 'email_queue_period')
        ->setLabel(___(
                "Allowed E-Mails Period\n" .
                "choose if your host is limiting e-mails per day or per hour"))
                ->loadOptions(
                        array (
                                3600 => 'Hour',
                                86400 => 'Day',
                        )
                );

        $this->setDefault('email_queue_limit', 100);
        $fs->addInteger('email_queue_limit', array (
                'size' => 6,
        ))
        ->setLabel(___(
                "Allowed E-Mails Count\n" .
                "enter number of emails allowed within the period above"));

        $fs = $this->addElement('fieldset', '##10')
        ->setLabel(___('Validation messages to customer'));

        $fs->addElement('email_link', 'verify_email_signup', null, array('help-id' => '#Validation_Message_Configuration'))
        ->setLabel(___("Verify E-Mail Address On Signup Page\n".
            "e-mail verification may be enabled for each signup form separately\n".
            "at aMember CP -> Forms Editor -> Edit, click \"configure\" on E-Mail brick"));
         
        $fs->addElement('email_link', 'verify_email_profile', null, array('help-id' => '#Validation_Message_Configuration'))
        ->setLabel(___("Verify New E-Mail Address On Profile Page\n".
            "e-mail verification for profile form may be enabled\n".
            "at aMember CP -> Forms Editor -> Edit, click \"configure\" on E-Mail brick"));

        $fs = $this->addElement('fieldset', '##11')
        ->setLabel(___('Signup Messages'));
         
        $fs->addElement('email_checkbox', 'registration_mail')
        ->setLabel(___("Send Registration E-Mail\n".
                "once customer completes signup form (before payment)"));
         
        $fs = $this->addElement('fieldset', '##12')
        ->setLabel(___("Pending Invoice Notification Rules"));

        $fs->addElement(new Am_Form_Element_PendingNotificationRules('pending_to_user'))
        ->setLabel(___("Pending invoice notifications to user\n".
                "only one email will be send for each defined day.\n".
                "all email for specific day will be selected and conditions will be checked.\n".
                "First email with matched condition will be send and other ignored"));

        $fs->addElement(new Am_Form_Element_PendingNotificationRules('pending_to_admin'))
        ->setLabel(___("Pending invoice notifications to admin\n".
                "only one email will be send for each defined day.\n".
                "all email for specific day will be selected and conditions will be checked.\n".
                "First email with matched condition will be send and other ignored"));

        $fs = $this->addElement('fieldset', '##13')
        ->setLabel(___('Messages to customer after payment'));
         
        $fs->addElement('email_checkbox', 'send_signup_mail', null, array('help-id' => '#Email_Messages_Configuration'))
        ->setLabel(___("Send Signup E-Mail\n".
                "once FIRST subscripton is completed"));

        $fs->addElement('email_checkbox', 'send_payment_mail', null, array('help-id' => '#Email_Messages_Configuration'))
        ->setLabel(___("E-Mail Payment Receipt to user\n".
                'every time payment is received'));

        $fs->addElement('email_checkbox', 'send_payment_admin', null, array('help-id' => '#Email_Messages_Configuration'))
        ->setLabel(___("Admin Payment Notifications\n".
                "to admin once payment is received"));

        $fs->addElement('email_checkbox', 'send_free_payment_admin', null, array('help-id' => '#Email_Messages_Configuration'))
        ->setLabel(___("Admin Free Subscription Notifications\n".
                "to admin once free signup is completed"));

        $fs = $this->addElement('fieldset', '##15')
        ->setLabel(___('E-Mails by User Request'));

        $fs->addElement('email_checkbox', 'mail_cancel_member', null, array('help-id' => '#Forgotten_Password_Templates'))
        ->setLabel(array(___('Send Cancel Notifications to User'), ___('send email to member when he cancels recurring subscription.')));

        $fs->addElement('email_checkbox', 'mail_cancel_admin', null, array('help-id' => '#Forgotten_Password_Templates'))
        ->setLabel(array(___('Send Cancel Notifications to Admin'), ___('send email to admin when recurring subscription<br />cancelled by member.')));

        $fs->addElement('email_link', 'send_security_code', null, array('help-id' => '#Forgotten_Password_Templates'))
        ->setLabel(___("Remind Password to Customer"));

        $fs->addElement('email_checkbox', 'changepass_mail')
        ->setLabel(array(___('Change Password Notification'), ___('send email to user after password change')));

        if($this->haveCronRebillPlugins())
        {
        
            $fs = $this->addElement('fieldset', '##17')
            ->setLabel(___('E-Mail Messages on rebilling event', ''));
            
            $fs->addElement('email_checkbox', 'cc.admin_rebill_stats')
            ->setLabel(___('Send Credit Card Rebill Stats to Admin', 'Credit Card Rebill Stats willbe sent to Admin daily<br/>
                    
                    It works for payment processors like Authorize.Net and PayFlow Pro only
                    '));
            
            $fs->addElement('email_checkbox', 'cc.rebill_failed')
            ->setLabel(___('Credit Card Rebill Failed', 'if credit card rebill failed, user will receive<br/>
                    the following e-mail message.<br/>
                    It works for payment processors like Authorize.Net and PayFlow Pro only
                    '));

            $fs->addElement('email_checkbox', 'cc.rebill_success')
            ->setLabel(___('Credit Card Rebill Successfull', 'if credit card rebill was sucessfull, user will receive<br/>
                    the following e-mail message.<br/>
                    It works for payment processors like Authorize.Net and PayFlow Pro only
                    '));

            if($this->haveStoreCreditCardPlugins())
            {
                $gr = $fs->addGroup()
                    ->setLabel(___('Credit Card Expiration Notice', 'if saved customer credit card expires soon, user will receive<br/>
                        the following e-mail message.<br/>
                        It works for payment processors like Authorize.Net and PayFlow Pro only
                        '));
;
                $gr->addElement('email_checkbox', 'cc.card_expire');
                $gr->addHTML()->setHTML(" ".___('Send message'));
                $gr->addText('cc.card_expire_days', array('size'=>2, 'value'=>5));
                $gr->addHTML()->setHTML(___('days before rebilling'));
                
            }
        }
        $fs = $this->addElement('fieldset', '##16')
        ->setLabel(___('E-Mails by Admin Request'));
         
        $fs->addElement('email_link', 'send_security_code_admin', null, array('help-id' => '#Forgotten_Password_Templates'))
        ->setLabel(___('Remind Password to Admin'));
         
         
        $fs = $this->addElement('fieldset', '##18')
        ->setLabel(___('Miscellaneous'));

        $fs->addElement('advcheckbox', 'disable_unsubscribe_link', null, array('help-id' => '#Miscellaneous_Email_Settings'))
        ->setLabel(___('Do not include Unsubscribe Link into e-mails'));

        $this->addScript()->setScript(<<<CUT
$(function(){
    $('input[type=checkbox][name=disable_unsubscribe_link]').change(function(){
        $('#row-unsubscribe_html-0, #row-unsubscribe_txt-0').toggle(!this.checked)
    }).change();
})
CUT
        );

        $fs->addTextarea('unsubscribe_html', array('cols'=>70, 'rows'=>6),  array('help-id' => '#Miscellaneous_Email_Settings'))
        ->setLabel(___("HTML E-Mail Unsubscribe Link\n" .
                "%link% will be replaced to actual unsubscribe URL"));
        $this->setDefault('unsubscribe_html', Am_Mail::UNSUBSCRIBE_HTML);

        $fs->addTextarea('unsubscribe_txt', array('cols'=>70, 'rows'=>6),  array('help-id' => '#Miscellaneous_Email_Settings'))
        ->setLabel(___("Text E-Mail Unsubscribe Link\n" .
                "%link% will be replaced to actual unsubscribe URL"));
        $this->setDefault('unsubscribe_txt', Am_Mail::UNSUBSCRIBE_TXT);

        $fs->addElement('advcheckbox', 'disable_unsubscribe_block', null, array('help-id' => '#Miscellaneous_Email_Settings'))
        ->setLabel(___('Do not show Unsubscribe Block on Member page'));

        $fs->addElement('text', 'copy_admin_email', array (
                'size' => 50,
        ), array('help-id' => '#Miscellaneous_Email_Settings'))
        ->setLabel(___("Send copy of all Admin notifications", 'will be used to send copy of email notifications to admin<br/>
                you can specify more then one email separated by comma: <br/>

                test@email.com,test1@email.com,test2@email.com
                '))
                ->addRule('callback', 'Please enter valid e-mail address', array('Am_Validate', 'empty_or_email'));
    }
    
    function haveCronRebillPlugins(){
        foreach(Am_Di::getInstance()->plugins_payment->getAllEnabled() as $p)
        {
            if($p->getRecurringType() == Am_Paysystem_Abstract::REPORTS_CRONREBILL)
                return true;
               
        }
    }
    function haveStoreCreditCardPlugins(){
        foreach(Am_Di::getInstance()->plugins_payment->getAllEnabled() as $p)
        {
            if($p->storesCcInfo())
                return true;
               
        }
    }
    
}

class Am_Form_Setup_Pdf extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('pdf');
        $this->setTitle(___('PDF Invoice'))
        ->setComment('');
        $this->data['help-id'] = 'Setup/PDF Invoice';
    }
    function initElements()
    {

        $this->addElement('advcheckbox', 'send_pdf_invoice', null, array('help-id' => '#Enabling_PDF_Invoices'))
        ->setLabel(___(
                "Enable PDF invoice\n" .
                "attach invoice file (.pdf) to Payment Receipt email"));

        $this->addElement('text', 'invoice_filename', array('size'=>30))
        ->setLabel(___('Filename for invoice') . "\n" . ___('%public_id% will be replaced with real public id of invoice, %receipt_id% will be replaced with payment receipt'));

        $this->setDefault('invoice_filename', 'amember-invoice-%public_id%.pdf');

        $this->addElement('advradio', 'invoice_format', null, array('help-id' => '#PDF_Invoice_Format'))
        ->setLabel(___('Paper format'))
        ->loadOptions(array(
                Am_Pdf_Invoice::PAPER_FORMAT_LETTER => ___('USA (Letter)'),
                Am_Pdf_Invoice::PAPPER_FORMAT_A4 => ___('European (A4)')
        ));

        $this->setDefault('invoice_format', Am_Pdf_Invoice::PAPER_FORMAT_LETTER);


        $this->addAdvcheckbox('invoice_include_access')
        ->setLabel(___('Include access periods to PDF invoice'));

        $this->addAdvcheckbox('invoice_do_not_include_terms')
        ->setLabel(___('Do not include subscription terms to PDF invoice'));
        
        $upload = $this->addElement('upload', 'invoice_custom_template',
                array(), array('prefix'=>'invoice_custom_template', 'help-id' => '#PDF_Invoice_Template')
        )->setLabel(___("Custom PDF template for invoice (optional)")
        )->setAllowedMimeTypes(array(
                'application/pdf'
        ));
         
        $this->setDefault('invoice_custom_template', '');

        $jsOptions = <<<CUT
{
    onChange : function(filesCount) {
        if (filesCount) {
            $('fieldset#template-custom-settings').show();
            $('fieldset#template-generated-settings').hide();
        } else {
            $('fieldset#template-custom-settings').hide();
            $('fieldset#template-generated-settings').show();
        }
    }
}
CUT;

        $upload->setJsOptions(
                $jsOptions
        );


        $fsCustom = $this->addElement('fieldset', 'template-custom')
        ->setLabel(___('Custom template settings'))
        ->setId('template-custom-settings');

        $this->setDefault('invoice_skip', 150);
        $fsCustom->addElement('text', 'invoice_skip')
        ->setLabel(___(
                "Top margin\n".
                "How much [pt] skip from top of template before start to output invoice\n".
                "1 pt = 0.352777 mm"));

        $fsGenerated = $this->addElement('fieldset', 'template-generated')
        ->setLabel(___("Auto-generated template settings"))
        ->setId('template-generated-settings');

        $invoice_logo = $fsGenerated->addElement('upload', 'invoice_logo', array(),
                array('prefix'=>'invoice_logo', 'help-id' => '#Company_Logo_for_Invoice')
        )->setLabel(___("Company Logo for Invoice\n".
                "it must be png/jpeg/tiff file (%s)", '200&times;100 px'))
                ->setAllowedMimeTypes(array(
                        'image/png', 'image/jpeg', 'image/tiff'
                ));
         
        $this->setDefault('invoice_logo', '');

        $fsGenerated->addElement('textarea', 'invoice_contacts', array (
                'rows' => 5, 'style'=>"width:90%"
        ), array('help-id' => '#Invoice_Contact_Information'))
        ->setLabel(___("Invoice Contact information\n" .
                "included at top"));

        $fsGenerated->addElement('textarea', 'invoice_footer_note', array (
                'rows' => 5, 'style'=>"width:90%"
        ), array('help-id' => '#Invoice_Footer_Note'))
        ->setLabel(___("Invoice Footer Note\n" .
                "will be included at bottom"));

        $script = <<<CUT
(function($){
    $(function() {
        function change_template_type(obj) {
            if ($(obj).val()) {
                $('fieldset#template-custom-settings').show();
                $('fieldset#template-generated-settings').hide();
            } else {
                $('fieldset#template-custom-settings').hide();
                $('fieldset#template-generated-settings').show();
            }
        }

        change_template_type($('input[name=invoice_custom_template]'));

        if (!$('input[name=send_pdf_invoice]').prop('checked')) {
            $('input[name=send_pdf_invoice]').closest('.row').nextAll().not('script').hide();
            $('input[name=send_pdf_invoice]').closest('form').find('input[type=submit]').closest('.row').show();
        }

        $('input[name=send_pdf_invoice]').bind('change', function(){
            if (!$(this).prop('checked')) {
                $(this).closest('.row').nextAll().not('script').hide()
                $(this).closest('form').find('input[type=submit]').closest('.row').show();
            } else {
                $(this).closest('.row').nextAll().not('script').show();
                change_template_type($('input[name=invoice_custom_template]'));
            }
        })
    });
})(jQuery)
CUT;
        $this->addElement('script', 'script')
        ->setScript(
                $script
        );

        $gr = $this->addAdvFieldset('invoice_custom_font')
                ->setLabel(___('Advanced'));

        $gr->addElement('upload', 'invoice_custom_ttf',
                array(), array('prefix'=>'invoice_custom_ttf')
        )->setLabel(___("Custom Font for Invoice (optional)")."\n".
            ___("Useful for invoices with non-Latin symbols")."\n".
            ___("when there is a problem with displaying such symbols in the PDF invoice.")."\n".
            ___("Please upload .ttf file only.")
        );
        $this->setDefault('invoice_custom_ttf', '');
        $gr->addElement('upload', 'invoice_custom_ttfbold',
                array(), array('prefix'=>'invoice_custom_ttfbold')
        )->setLabel(___("Custom Bold Font for Invoice (optional)")."\n".
            ___("Useful for invoices with non-Latin symbols")."\n".
            ___("when there is a problem with displaying such symbols in the PDF invoice.")."\n".
            ___("Please upload .ttf file only.")
        );
        $this->setDefault('invoice_custom_ttfbold', '');
    }
}

class Am_Form_Setup_VideoPlayer extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('video-player');
        $this->setTitle(___('Video Player'))
        ->setComment('');
    }
    function initElements()
    {
        $this->setupElements($this, 'flowplayer.');

        $this->setDefault('flowplayer.width', 520);
        $this->setDefault('flowplayer.height', 330);
        $this->setDefault('flowplayer.autoBuffering', 0);
        $this->setDefault('flowplayer.bufferLength', 3);
        $this->setDefault('flowplayer.autoPlay', 1);
        $this->setDefault('flowplayer.scaling', 'scale');

    }

    public function setupElements(Am_Form $form, $prefix = null) {
        $form->addElement('text', $prefix . 'width')
        ->setLabel(___('Default Width'));

        $form->addElement('text', $prefix . 'height')
        ->setLabel(___('Default Height'));

        $form->addElement('select', $prefix . 'autoPlay')
        ->setLabel(array(___('Auto Play'), ___('whether the player should start playback immediately upon loading')))
        ->loadOptions(array(
                0 => ___('No'),
                1 => ___('Yes')
        ));

        $form->addElement('select', $prefix . 'autoBuffering')
        ->setLabel(array(___('Auto Buffering'), ___('whether loading of clip into player\'s memory should begin straight away. When this is true and autoPlay is false then the clip will automatically stop at the first frame of the video.')))
        ->loadOptions(array(
                0 => ___('No'),
                1 => ___('Yes')
        ));

        $form->addElement('text', $prefix . 'bufferLength')
        ->setLabel(array(___('Buffer Length'), ___('The amount of video data (in seconds) which should be loaded into Flowplayer\'s memory in advance of playback commencing.')));

        $form->addElement('select', $prefix . 'scaling')
        ->setLabel(array(___('Scaling'), ___('Setting which defines how video is scaled on the video screen. Available options are:

                <strong>fit</strong>: Fit to window by preserving the aspect ratio encoded in the file\'s metadata.
                <strong>half</strong>: Half-size (preserves aspect ratio)
                <strong>orig</strong>: Use the dimensions encoded in the file. If the video is too big for the available space, the video is scaled using the \'fit\' option.
                <strong>scale</strong>: Scale the video to fill all available space. Ignores the dimensions in the metadata. This is the default setting.
                ')))
                ->loadOptions(array(
                        'fit' => 'fit',
                        'half' => 'half',
                        'orig' => 'orig',
                        'scale' => 'scale'
                ));
    }
}

class Am_Form_Setup_Advanced extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('advanced');
        $this->setTitle(___('Advanced'))
        ->setComment('');
        $this->data['help-id'] = 'Setup/Advanced';
    }

    function checkBackupEmail($val){
        $res = $val['email_backup_frequency'] ?
        Am_Validate::email($val['email_backup_address']) : true;

        if (!$res) {
            $elements = $this->getElementsByName('email_backup_address');
            $elements[0]->setError(___('This field is required'));
        }

        return $res;
    }

    function initElements()
    {
        $this->addElement('advcheckbox', 'use_cron', null, array('help-path' => 'Cron'))
        ->setLabel(___("Use External Cron"));
         
        $gr = $this->addGroup(null, null, array('help-id' => '#Configuring_Advanced_Settings'))->setLabel(___(___('Maintenance Mode'), ___('put website offline, making it available for admins only')));
        $gr->addCheckbox('', array('id' => 'maint_checkbox',
                'data-text' => ___('Site is temporarily disabled for maintenance')));
        $gr->addTextarea('maintenance', array('id' => 'maint_textarea', 'rows'=>3, 'cols'=>80));
        $gr->addScript()->setScript(<<<CUT
$(function(){
    var checkbox = $('#maint_checkbox');
    var textarea = $('#maint_textarea');
    $('#maint_checkbox').click(function(){
        textarea.toggle(checkbox.prop('checked'));
        if (textarea.is(':visible'))
        {
            textarea.val(checkbox.data('text'));
        } else {
            checkbox.data('text', textarea.val());
            textarea.val('');
        }
    });
    checkbox.prop('checked', !!textarea.val());
    textarea.toggle(checkbox.is(':checked'));
});
CUT
        );
         
        $gr = $this->addGroup(null, null, array('help-id'=>'#Configuring_Advanced_Settings'))->setLabel(___("Clear Access Log"));
        $gr->addElement('advcheckbox', 'clear_access_log', null, array('help-id' => '#Configuring_Advanced_Settings'));
        $gr->addStatic()->setContent(sprintf('<span class="clear_access_log_days">%s</span>', ___("after")));
        $gr->addElement('integer', 'clear_access_log_days', array('class'=>'clear_access_log_days'));
        $gr->addStatic()->setContent(sprintf('<span class="clear_access_log_days">%s</span>', ___("days")));

        $this->setDefault('clear_access_log_days', 7);

        $this->addScript()->setScript(<<<CUT
$(function(){
    $('input[name=clear_access_log]').change(function(){
        $('.clear_access_log_days').toggle(this.checked);
    }).change();
})

CUT
            );

        $gr = $this->addGroup()->setLabel(___("Clear Incomplete Invoices"));
        $gr->addElement('advcheckbox', 'clear_inc_payments');
        $gr->addStatic()->setContent(sprintf('<span class="clear_inc_payments_days">%s</span>', ___("after")));
        $gr->addElement('integer', 'clear_inc_payments_days', array('class'=>'clear_inc_payments_days'));
        $gr->addStatic()->setContent(sprintf('<span class="clear_inc_payments_days">%s</span>', ___("days")));

        $this->setDefault('clear_inc_payments_days', 7);

        $this->addScript()->setScript(<<<CUT
$(function(){
    $('input[name=clear_inc_payments]').change(function(){
        $('.clear_inc_payments_days').toggle(this.checked);
    }).change();
})

CUT
            );

        $this->setDefault('multi_title', ___('Membership'));
        $this->addElement('text', 'multi_title', null, array('help-id' => '#Configuring_Advanced_Settings'))
        ->setLabel(___("Multiple Order Title\n".
                "when user ordering multiple products,\n".
                "display the following on payment system\n".
                "instead of product name"));

        $fs = $this->addElement('fieldset', '##3')
        ->setLabel(___('E-Mail Database Backup'));
         
        $fs->addElement('select', 'email_backup_frequency', null, array('help-id' => '#Enabling.2FDisabling_Email_Database_Backup'))
        ->setLabel(___('Email Backup Frequency'))
        ->setId('select-email-backup-frequency')
        ->loadOptions(array(
                '0' => 'Disabled',
                'd' => 'Daily',
                'w' => 'Weekly'
        ));
         
        $di = Am_Di::getInstance();
        $backUrl = $di->config->get('root_url') . '/backup/cron/k/' . $di->app->getSiteHash('backup-cron', 10);
         
        $text = ___("It is required to setup a cron job to trigger backup generation");
        $html = <<<CUT
<div id="email-backup-note-text">
</div>
<div id="email-backup-note-text-template" style="display:none">
    $text <br />
    <strong>%EXECUTION_TIME% /usr/bin/curl $backUrl</strong><br />
</div>
CUT;
         
        $fs->addHtml('email_backup_note')->setHtml($html);
         
        $fs->addElement('text', 'email_backup_address')
        ->setLabel(___('E-Mail Backup Address'));
         
        $this->addRule('callback', 'Email is required if you have enabled Email Backup Feature', array($this, 'checkBackupEmail'));
         
        $script = <<<CUT
(function($) {
    function toggle_frequency() {
        if ($('#select-email-backup-frequency').val() == '0') {
            $("input[name=email_backup_address]").closest(".row").hide();
        } else {
            $("input[name=email_backup_address]").closest(".row").show();
        }

        switch ($('#select-email-backup-frequency').val()) {
            case 'd' :
                $('#email-backup-note-text').empty().append(
                    $('#email-backup-note-text-template').html().
                        replace(/%FREQUENCY%/, 'daily').
                        replace(/%EXECUTION_TIME%/, '15 0 * * *')
                )
                $('#email-backup-note-text').closest('.row').show();
                break;
            case 'w' :
                $('#email-backup-note-text').empty().append(
                    $('#email-backup-note-text-template').html().
                        replace(/%FREQUENCY%/, 'weekly').
                        replace(/%EXECUTION_TIME%/, '15 0 * * 1')
                )
                $('#email-backup-note-text').closest('.row').show();
                break;
            default:
                $('#email-backup-note-text').closest('.row').hide();
        }
    }

    toggle_frequency();

    $('#select-email-backup-frequency').bind('change', function(){
        toggle_frequency();
    })

})(jQuery)
CUT;
         
        $this->addScript('script-backup')->setScript($script);
         
        $fs = $this->addElement('fieldset', '##5')
        ->setLabel(___('Advanced Options'));
        $fs->addElement('advcheckbox', 'profile_changed', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(___("Send Notification to admin when profile is changed\n".
                "admin will receive an email if user has changed profile\n"
        ));
         
        $fs->addElement('email_checkbox', 'manually_approve', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(___('Manually Approve New Members', ___('manually approve all new members (first payment)').'<br />'.
                ___('don\'t enable it if you have huge members base already').'<br />'.
                ___('- all old members become not-approved')
        ));
        $fs->addElement('email_link', 'manually_approve_admin', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(___("Send Approval Notification to admin\n".
                "for new members"));
        $fs->addElement('advcheckbox', 'manually_approve_invoice', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(___('Manually Approve New Invoices'), ___('manually approve all new invoices'));
        $fs->addMagicSelect('manually_approve_invoice_products', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(array(
                ___('Require Approval Only If Invoice Has These Products'),
                ___('By default each invoice will be set as "Not Apprved" ').
                ___('although you can enable this functionality only for selected products')
        ))
        ->loadOptions(
                Am_Di::getInstance()->productTable->getOptions()
        );
         
        $fs->addElement('email_link', 'invoice_approval_wait_admin', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel('"Require Approval" notification to admin');
         
        $fs->addElement('email_link', 'invoice_approval_wait_user', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel('"Require Approval" notification to user');
         
        $fs->addElement('email_link', 'invoice_approved_user', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel('Invoice approved notification for user.');
         
        $fs->addScript()->setScript(<<<CUT
$(function(){
    $("#manually_approve_invoice-0").change(function(){
        $("#manually_approve_invoice_products-0").closest(".row").toggle(this.checked);
        $("#row-invoice_approval_wait_admin-0").toggle(this.checked);
        $("#row-invoice_approval_wait_user-0").toggle(this.checked);
    }).change();
});
CUT
        );
         
        $fs->addElement('advcheckbox', 'dont_check_updates', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(___("Disable checking for aMember updates"));

        $fs->addElement('advcheckbox', 'quickstart-disable', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(___("Disable QuickStart wizard"));
         
        $fs->addElement('advcheckbox', 'am3_urls', null, array('help-id' => '#Configuring_Advanced_Options'))
        ->setLabel(___("Use aMember3 compatible urls\n".
                "Enable old style urls (ex.: signup.php, profile.php)\n".
                "Usefull only after upgrade from aMember v3 to keep old links working.\n"
        ));
         
         
        if(!ini_get('suhosin.session.encrypt'))
            $fs->addSelect('session_storage', null, array('help-id' => '#Configuring_Advanced_Options'))
            ->setLabel(___("Session Storage"))
            ->loadOptions(array(
                    'db' => ___('aMember Database (default)'),
                    'php' => ___('Standard PHP Sessions'),
            ));
        else
            $fs->addHTML('session_storage')
            ->setLabel(___("Session Storage"))
            ->setHTML('<b>'.___('Standard PHP Sessions').'</b> <i>'.___("Can't be changed because your server have suhosin extension enabled")."</i>");
         

        $js = <<<CUT
(function() {
//    function toggle_clear_access_log() {
//        $('.clear_access_log_days, #clear_access_log_days-0').toggle(!$('input[name=clear_access_log]').prop('checked'));
//    }
//    $(function(){
//        toggle_clear_access_log();
//        $('input[name=clear_access_log]').change(function(){
//            toggle_clear_access_log()
//        })
//    })
})(jQuery)
CUT;

        $this->addScript('script')->setScript($js);
    }
}

class Am_Form_Setup_Loginpage extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('loginpage');
        $this->setTitle(___('Login Page'))
        ->setComment('');
        $this->data['help-id'] = 'Setup/Login_Page';
    }
    function initElements()
    {
        $gr = $this->addGroup(null, null, array('help-id' => '#Login_Page_Options'))
        ->setLabel(___("Redirect after login\n".
                "where customer redirected after successful\n".
                "login at %s", '<b>'.ROOT_URL . '/login</b>'));
        $sel = $gr->addSelect('protect.php_include.redirect_ok',
                array('size' => 1, 'id' => 'redirect_ok-sel'), array('options' => array(
                        'first_url' => ___('First available protected url'),
                        'last_url' => ___('Last available protected url'),
                        'member' => ___('Membership Info Page'),
                        'url' => ___('Fixed Url'),
                )));
        $txt = $gr->addText('protect.php_include.redirect_ok_url',
                array('size' => 40, 'style'=>'display:none', 'id' => 'redirect_ok-txt'));
        $this->setDefault('protect.php_include.redirect_ok_url', ROOT_URL);
        $gr->addScript()->setScript(<<<CUT
$(function(){
    $("#redirect_ok-sel").change(function(){
        $("#redirect_ok-txt").toggle($(this).val() == 'url');
    }).change();
});
CUT
        );

        $this->addElement('text', 'protect.php_include.redirect', 'size=40', array('help-id' => '#Login_Page_Options'))
        ->setLabel(___("Redirect after logout\n".
                "enter full URL, starting from http://\n".
                "keep empty for redirect to site homepage"));

        $this->addElement('advcheckbox', 'protect.php_include.remember_login', null, array('help-id' => '#Login_Page_Options'))
        ->setLabel(___("Remember Login\n".
                "remember username/password in cookies"));

        $this->addElement('advcheckbox', 'protect.php_include.remember_auto', null, array('help-id' => '#Login_Page_Options'))
        ->setLabel(___("Always Remember\n".
                "if set to Yes, don't ask customer - always remember"));

        $this->setDefault('protect.php_include.remember_period', 60);
        $this->addElement('integer', 'protect.php_include.remember_period', null, array('help-id' => '#Login_Page_Options'))
        ->setLabel(___("Remember period\n" .
                "cookie will be stored for ... days"));

        $this->addElement('advcheckbox', 'auto_login_after_signup', null, array('help-id' => '#Login_Page_Options'))
        ->setLabel(___('Automatically login customer after signup'));

        $this->addElement('advcheckbox', 'allow_auth_by_savedpass')
        ->setLabel(___('Allow to use password hash from 3ty part scripts to authenticate user in aMember'));
         
        $this->setDefault('login_session_lifetime', 120);
        $this->addElement('integer', 'login_session_lifetime', null, array('help-id' => '#Login_Page_Options'))
        ->setLabel(___("User session lifetime (minutes)\n".
                "default - 120"));

        $gr = $this->addGroup(null, null, array('help-id' => '#Account_Sharing_Prevention'))
        ->setLabel(___("Account Sharing Prevention"));

        $gr->addStatic()->setContent(___('if customer uses more than'));
        $gr->addElement('integer', 'max_ip_count', array('size' => 4));
        $gr->addStatic()->setContent(___('IP within'));
        $gr->addElement('integer', 'max_ip_period', array('size' => 5));
        $gr->addStatic()->setContent(___('minutes deny access for user and %sdo the following%s', '<br />', '<br /><br />'));
        $ms=$gr->addMagicSelect('max_ip_actions')
        ->loadOptions(
                array (
                        'disable-user' => 'Disable customer account',
                        'email-admin' => 'Email admin regarding account sharing',
                        'email-user' => 'Email user regarding account sharing',
                )
        );
        $ms->setJsOptions('{onChange:function(val){
                $("#max_ip_actions_admin").toggle(val.hasOwnProperty("email-admin"));
                $("#max_ip_actions_user").toggle(val.hasOwnProperty("email-user"));
    }}');
        $gr->addStatic()->setContent('<br/><div id="max_ip_actions_admin" style="display:none;">');
        $gr->addElement('email_link', 'max_ip_actions_admin')
        ->setLabel(___('Email admin regarding account sharing'));
        $gr->addStatic()->setContent('<div>'.___('Admin notification').'</div><br/></div><div id="max_ip_actions_user" style="display:none;">');
        $gr->addElement('email_link', 'max_ip_actions_user')
        ->setLabel(___('Email user regarding account sharing'));
        $gr->addStatic()->setContent('<div>'.___('User notification').'</div><br/></div>');
        $gr->addSelect('max_ip_octets')->loadOptions(array(
            0 => ___('Count all IP as different'),
            1 => ___('Use first %d IP address octets to determine different IP (%s)', 3, '123.32.22.xx'),
            2 => ___('Use first %d IP address octets to determine different IP (%s)', 2, '123.32.xx.xx'),
            3 => ___('Use first %d IP address octets to determine different IP (%s)', 1, '123.xx.xx.xx'),
        ));
        
        $gr = $this->addGroup(null, null, array('help-id' => '#Bruteforce_Protection'))
        ->setLabel(___('Bruteforce Protection'));
        $this->setDefault('bruteforce_count', '5');
        $gr->addStatic()->setContent(___('if user enters wrong password'));
        $gr->addElement('integer', 'bruteforce_count', array('size' => 4));
        $gr->addStatic()->setContent(___('times within'));
        $this->setDefault('bruteforce_delay', '120');
        $gr->addElement('integer', 'bruteforce_delay', array('size'=>5));
        $gr->addStatic()->setContent(___('seconds, he will be forced to wait until next try'));
         
        $this->addElement('advcheckbox', 'skip_index_page')
        ->setLabel(array(___('Skip Index Page if user is logged-in'),
                ___('When logged-in user try to access /amember/index page, he will be redirected to /amember/member')));
         
        $this->addElement('advcheckbox', 'other_domains_redirect')
        ->setLabel(array(
                ___(
                        "Allow redirects to other domains\n".
                        "By default aMember does not allow to redirect to foreign domain names via 'amember_redirect_url' parameter.\n".
                        "These redirects are only allowed for urls within your domain name.\n".
                        "This is restricted to avoid potential security issues.\n"
                )));
         
    }
}

class Am_Form_Setup_Language extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('language');
        $this->setTitle(___('Languages'));
        $this->data['help-id'] = 'Setup/Languages';
    }
    function initElements()
    {
        $this->addElement('advcheckbox', 'lang.display_choice', null, array('help-id' => '#Enabling.2FDisabling_Language_Choice_Option'))
        ->setLabel(___('Display Language Choice'));
        $list = Am_Di::getInstance()->languagesListUser;

        $sel = $this->addElement('select', 'lang.enabled', array('multiple'=> 'multiple', 'class' => 'magicselect'), array('help-id' => '#Selecting_Languages_to_Offer'))
        ->setLabel(___("Available Locales\ndefines both language and date/number formats"));
        $sel->loadOptions($list);

        $this->setDefault('lang.default', 'en');
        $sel = $this->addElement('select', 'lang.default', array(), array('help-id' => '#Selecting.2FEditing_Default_Language'))
        ->setLabel(___('Default Locale'));
        $sel->loadOptions(array('' => '== '.___('Please Select').' ==') + $list);
    }
}

class Am_Form_Setup_Theme extends Am_Form_Setup
{
    protected $themeId;
    public function __construct($themeId)
    {
        $this->themeId = $themeId;
        parent::__construct('themes-'.$themeId);
    }
    public function prepare()
    {
        parent::prepare();
        $this->addFieldsPrefix('themes.'.$this->themeId.'.');
    }
}

class Am_Form_Setup_Recaptcha extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('recaptcha');
        $this->setTitle(___('ReCaptcha'));
    }
    function initElements()
    {
        $this->addText("recaptcha-public-key", array('size'=>50), array('help-id' => 'Setup/ReCaptcha'))->setLabel("<a href='http://www.google.com/recaptcha' target='_blank'>ReCaptcha</a> Public Key")
        ->addRule('required', 'This field is required');
        $this->addText("recaptcha-private-key", array('size'=>50), array('help-id' => 'Setup/ReCaptcha'))->setLabel("<a href='http://www.google.com/recaptcha' target='_blank'>ReCaptcha</a> Private Key")
        ->addRule('required', 'This field is required');
        $this->addSelect('recaptcha-theme', '', array(
            'options' => array(
                'clean' =>  'clean',
                'white' =>  'white',
                'red'   =>  'red',
                'blackglass'    =>  'blackglass'
                )
            ))->setLabel('ReCaptcha theme');
    }

    function getReadme(){
        return <<<CUT
<b>reCaptcha configuration</b>
Complete instructions can be found here:
<a href='http://www.amember.com/docs/Setup/ReCaptcha' target='_blank'>http://www.amember.com/docs/Setup/ReCaptcha</a>

Use Forms Editor in order to add recaptcha field to signup/renewal page:
<a href='%root_url%/admin-saved-form'>%root_url%/admin-saved-form</a>

CUT;

    }
}
