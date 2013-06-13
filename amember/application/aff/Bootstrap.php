<?php

class Bootstrap_Aff extends Am_Module
{
    const AFF_COMMISSION_AFTER_INSERT = 'affCommissionAfterInsert';
    const COOKIE_NAME = 'amember_aff_id';
    
    protected $last_aff_id;
    
    function init()
    {
        parent::init();
        $this->getDi()->userTable->customFields()->addCallback(array('Am_Aff_PayoutMethod', 'static_addFields'));

        $this->getDi()->uploadTable->defineUsage('affiliate', 'aff_banner', 'upload_id', UploadTable::STORE_FIELD, "Affiliate Marketing Material [%title%, %desc%]", '/aff/admin-banners/p/downloads/index');
        $this->getDi()->uploadTable->defineUsage('banners', 'aff_banner', 'upload_id', UploadTable::STORE_FIELD, "Affiliate Banner [%title%, %desc%]", '/aff/admin-banners/p/banners/index');
        $this->getDi()->uploadTable->defineUsage('banners', 'aff_banner', 'upload_big_id', UploadTable::STORE_FIELD, "Affiliate Banner [%title%, %desc%]", '/aff/admin-banners/p/banners/index');
    }

    function onSetupEmailTemplateTypes(Am_Event $event)
    {
        $event->addReturn(array(
                'id' => 'aff.mail_sale_user',
                'title' => 'Aff Mail Sale User',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array(),
            ), 'aff.mail_sale_user');
        $event->addReturn(array(
                'id' => 'aff.mail_sale_admin',
                'title' => 'Aff Mail Sale Admin',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ), 'aff.mail_sale_admin');
    }

    function onUserMerge(Am_Event $event)
    {
        $target = $event->getTarget();
        $source = $event->getSource();

        $this->getDi()->db->query('UPDATE ?_aff_click SET aff_id=? WHERE aff_id=?',
            $target->pk(), $source->pk());
        $this->getDi()->db->query('UPDATE ?_aff_commission SET aff_id=? WHERE aff_id=?',
            $target->pk(), $source->pk());
        $this->getDi()->db->query('UPDATE ?_aff_lead SET aff_id=? WHERE aff_id=?',
            $target->pk(), $source->pk());
        $this->getDi()->db->query('UPDATE ?_aff_payout_detail SET aff_id=? WHERE aff_id=?',
            $target->pk(), $source->pk());
        $this->getDi()->db->query('UPDATE ?_user SET aff_id=? WHERE aff_id=?',
            $target->pk(), $source->pk());
    }
    function onGetMemberLinks(Am_Event $e)
    {
        $u = $e->getUser();
        if (!$u->is_affiliate && !$this->getDi()->config->get('aff.signup_type'))
            $e->addReturn(___('Advertise our website to your friends and earn money'), 
                ROOT_URL . '/aff/aff/enable-aff');
    }
    function onGetUploadPrefixList(Am_Event $event)
    {
        $event->addReturn(array(
            Am_Upload_Acl::IDENTITY_TYPE_ADMIN => array(
                'affiliates' => Am_Upload_Acl::ACCESS_ALL
            ),
            Am_Upload_Acl::IDENTITY_TYPE_USER => Am_Upload_Acl::ACCESS_READ,
            Am_Upload_Acl::IDENTITY_TYPE_ANONYMOUS => Am_Upload_Acl::ACCESS_READ
        ), "banners");
        
        $event->addReturn(array(
            Am_Upload_Acl::IDENTITY_TYPE_ADMIN => array(
                'affiliates' => Am_Upload_Acl::ACCESS_ALL
            ),
            Am_Upload_Acl::IDENTITY_TYPE_AFFILIATE => Am_Upload_Acl::ACCESS_READ
        ), "affiliate");
    }
    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn("Can see affiliate info/make payouts", "affiliates");
    }
    function onUserMenu(Am_Event $event)
    {
        if (!$event->getUser()->is_affiliate) return;
        $event->getMenu()->addPage(
            array(
                'id'    => 'aff',
                'controller'   => 'aff',
                'module' => 'aff',
                'label' => ___('Affiliate Info'),
                'order' => 300,
                'pages' => array(
                    array(
                        'id'    => 'aff-links',
                        'controller' => 'aff',
                        'module' => 'aff',
                        'label' => ___('Get affiliate banners and links'),
                    ),
                    array(
                        'id'    => 'aff-stats',
                        'controller' => 'member',
                        'module' => 'aff',
                        'action' => 'stats',
                        'label' => ___('Review your affiliate statistics'),
                    ),
                    array(
                        'id'    => 'aff-payout-info',
                        'controller' => 'member',
                        'module' => 'aff',
                        'action' => 'payout-info',
                        'label' => ___('Update your commissions payout info'),
                    ),
                ),
            )
        );

    }
    function onAdminMenu(Am_Event $event)
    {
        $menu = $event->getMenu();
        $menu->addPage(array(
            'id' => 'affiliates',
            'uri' => '#',
            'label' => ___('Affiliates'),
            'resource' => "affiliates",
            'pages' => array_merge(array(
                array(
                    'id' => 'affiliates-payout',
                    'controller' => 'admin-payout',
                    'module' => 'aff',
                    'label' => ___("Review/Pay Affiliate Commission"),
                    'resource' => "affiliates",
                ),
                array(
                    'id' => 'affiliates-commission',
                    'controller' => 'admin-commission',
                    'module' => 'aff',
                    'label' => ___('Affiliate Clicks/Sales Statistics'),
                    'resource' => "affiliates",
                ),
                array(
                    'id' => 'affiliates-banners',
                    'controller' => 'admin-banners',
                    'module' => 'aff',
                    'label' => ___('Manage Banners and Text Links'),
                    'resource' => "affiliates",
                )
                
            ),
            !Am_Di::getInstance()->config->get('manually_approve') && (Am_Di::getInstance()->config->get('aff.signup_type')!=2) ? array() : array(array(
                    'id' => 'user-not-approved',
                    'controller' => 'admin-users',
                    'action'     => 'not-approved',
                    'label' => ___('Not Approved Affiliates'),
                    'resource' => 'grid_u',
                    'privilege' => 'browse',
            ))

            )
        ));
    }

    public function addPayoutInputs(HTML_QuickForm2_Container $fieldSet)
    {
        $el = $fieldSet->addSelect('aff_payout_type')
            ->setLabel(___('Affiliate Payout Type'))
            ->loadOptions(array_merge(array(''=>___('Not Selected'))));
        foreach (Am_Aff_PayoutMethod::getEnabled() as $method)
            $el->addOption($method->getTitle(), $method->getId());

        $fieldSet->addScript()->setScript('
/**** show only options for selected payout method */
$(function(){
$("#'.$el->getId().'").change(function()
{
    var selected = $("#'.$el->getId().'").val();
    $("option", $(this)).each(function(){
        var option = $(this).val();
        if(option == selected){
            $("input[name^=aff_"+option+"_]").closest(".row").show();
        }else{
            $("input[name^=aff_"+option+"_]").closest(".row").hide();
        }
    });
}).change();
});
/**** end of payout method options */
');

        foreach ($this->getDi()->userTable->customFields()->getAll() as $f)
            if (strpos($f->name, 'aff_')===0)
                $f->addToQf2($fieldSet);
    }

    public function onGridUserBeforeSave(Am_Event_Grid $event)
    {
        $input = $event->getGrid()->getForm()->getValue();
        if (!empty($input['_aff']))
        {
            $aff = $this->getDi()->userTable->findFirstByLogin($input['_aff'], false);
            if ($aff)
            {
                if ($aff->pk() == $event->getGrid()->getRecord()->pk())
                {
                    throw new Am_Exception_InputError("Cannot assign affiliate to himself");
                }
                $event->getGrid()->getRecord()->aff_id = $aff->pk();
            } else {
                throw new Am_Exception_InputError("Affiliate not found, username specified: " . Am_Controller::escape($input['_aff']));
            }
        }
    }
    public function onGridUserInitForm(Am_Event_Grid $event)
    {
        $fieldSet = $event->getGrid()->getForm()->addAdvFieldset('affiliate')->setLabel(___('Affiliate Program'));

        $user = $event->getGrid()->getRecord();
        $affHtml = "";
        if (!empty($user->aff_id))
        {
            try {
                $aff = $this->getDi()->userTable->load($user->aff_id);
                $url = new Am_View_Helper_UserUrl;
                $affHtml = sprintf('<a target="_blank" href="%s">"%s %s" &lt;%s&gt;</a>', 
                    Am_Controller::escape($url->userUrl($user->aff_id)),
                    $aff->name_f, $aff->name_l, $aff->email
                    );
                $fieldSet->addElement('static', '_aff')
                    ->setLabel(___('Referred Affiliate'))
                    ->setContent($affHtml);
            } catch (Am_Exception $e) {
                // ignore if affiliate was deleted
            }
        } else {
            $fieldSet->addElement('text', '_aff', array('placeholder' => 'Type username or e-mail'))
                ->setLabel(___('Referred Affiliate'));
            $fieldSet->addScript()->setScript(<<<CUT
    $("input#_aff-0").autocomplete({
        minLength: 2,
        source: window.rootUrl + "/admin-users/autocomplete"
    });
CUT
            );
        }

        $fieldSet->addElement('advradio', 'is_affiliate')
            ->setLabel(array(___('Is Affiliate?'), ___('customer / affiliate status')))
            ->loadOptions(array(
                '0'  => ___('No'),
                '1'  => ___('Both Affiliate and member'),
                '2'  => ___('Only Affiliate %s(rarely used)%s', '<i>', '</i>'),
             ));
        
        $this->addPayoutInputs($fieldSet);
    }
    function onUserTabs(Am_Event_UserTabs $event)
    {
        if ($event->getUserId() > 0) {
            $event->getTabs()->addPage(array(
                'id' => 'aff',
                'module' => 'aff',
                'controller' => 'admin',
                'action' => 'info-tab',
                'params' => array(
                    'user_id' => $event->getUserId(),
                ),
                'label' => ___('Affiliate Info'),
                'order' => 1000,
                'resource' => 'affiliates',
            ));
            $event->getTabs()->addPage(array(
                'id' => 'subaff',
                'module' => 'aff',
                'controller' => 'admin',
                'action' => 'subaff-tab',
                'params' => array(
                    'user_id' => $event->getUserId(),
                ),
                'label' => ___('Subaffiliate'),
                'order' => 1000,
                'resource' => 'affiliates',
            ));
        }
    }
    /**
     * if $_COOKIE is empty, find matches for user by IP address in aff_clicks table
     * @param Am_Event_UserBeforeInsert $event 
     */
    function onUserBeforeInsert(Am_Event_UserBeforeInsert $event)
    {
        // skip this code if running from aMember CP
        if (defined('AM_ADMIN') && AM_ADMIN) return;
        $aff_id = @$_COOKIE[self::COOKIE_NAME];
        if (empty($aff_id))
        {
            $aff_id = $this->getDi()->affClickTable->findAffIdByIp($_SERVER['REMOTE_ADDR']);
        }
        // remember for usage in onUserAfterInsert
        $this->last_aff_id = $aff_id;
        if ($aff_id > 0)
            $event->getUser()->aff_id = intval($aff_id);
        if (empty($event->getUser()->is_affiliate))
            $event->getUser()->is_affiliate = $this->getDi()->config->get('aff.signup_type') == 1 ? 1 : 0;
    }
    function onUserAfterInsert(Am_Event_UserAfterInsert $event)
    {
        // skip this code if running from aMember CP
        if (preg_match('/^(\d+)-(\d+)-(\d+)$/', $this->last_aff_id, $regs))
        {
            $this->getDi()->affLeadTable->log($regs[1], $regs[2], $event->getUser()->pk(), $this->decodeClickId($regs[3]));
        }
    }
    function onUserAfterDelete(Am_Event_UserAfterDelete $event) 
    {
        foreach (array('?_aff_click', '?_aff_commission', '?_aff_lead') as $table)
            $this->getDi()->db->query("DELETE FROM $table WHERE aff_id=?", $event->getUser()->user_id);
    }
    
    function onUserAfterUpdate(Am_Event_UserAfterUpdate $e){
        if($e->getUser()->is_approved && !$e->getOldUser()->is_approved && $e->getUser()->is_affiliate)
            $this->sendAffRegistrationEmail($e->getUser());
    }

    /**
     * Handle free signups
     * @todo handle free signups
     */
    function onInvoiceStarted(Am_Event_InvoiceStarted $event) 
    {
        $invoice = $event->getInvoice();
        $isFirst = !$this->getDi()->db->selectCell("SELECT COUNT(*)
            FROM ?_invoice
            WHERE user_id=?
            AND invoice_id<>?
            AND tm_started IS NOT NULL",
            $invoice->user_id, $invoice->pk());

        if (($invoice->first_total == 0) &&
            ($invoice->second_total == 0) &&
            $isFirst)
        {
            $this->getDi()->affCommissionRuleTable->processPayment($invoice);
        }
    }
    
    /**
     * Handle payments
     */
    function onPaymentAfterInsert(Am_Event_PaymentAfterInsert $event)
    {
        $this->getDi()->affCommissionRuleTable->processPayment($event->getInvoice(), $event->getPayment());
    }
    
    /**
     * Handle refunds
     */
    function onRefundAfterInsert(Am_Event $event)
    {
        $this->getDi()->affCommissionRuleTable->processRefund($event->getInvoice(), $event->getRefund());
    }
    
    function onAffCommissionAfterInsert(Am_Event $event)
    {
        /* @var $commission AffCommission */
        $commission = $event->getCommission();
        if ($commission->record_type == AffCommission::VOID) return; // void
        if ($this->getConfig('mail_sale_admin'))
        {
            if ($et = Am_Mail_Template::load('aff.mail_sale_admin'))
                $et->setPayment($commission->getPayment())
                   ->setInvoice($invoice=$commission->getInvoice())
                   ->setAffiliate($commission->getAff())
                   ->setUser($invoice->getUser())
                   ->setCommission($commission->amount)
                   ->setTier($commission->tier + 1)
                   ->setProduct($this->getDi()->productTable->load($commission->product_id, false))
                   ->sendAdmin();
        }
        if ($this->getConfig('mail_sale_user'))
            if ($et = Am_Mail_Template::load('aff.mail_sale_user'))
                $et->setPayment($commission->getPayment())
                   ->setInvoice($invoice=$commission->getInvoice())
                   ->setAffiliate($commission->getAff())
                   ->setUser($invoice->getUser())
                   ->setCommission($commission->amount)
                   ->setTier($commission->tier + 1)
                   ->setProduct($this->getDi()->productTable->load($commission->product_id, false))
                   ->send($commission->getAff());
    }
    // utility functions
    function setCookie(User $aff, /* AffBanner */ $banner, $aff_click_id = null)
    {
        $tm = $this->getDi()->time + $this->getDi()->config->get('aff.cookie_lifetime', 30) * 3600*24;
        $val = $aff->pk();
        $val .= '-' . ($banner?$banner->pk():"0");
        if ($aff_click_id)
            $val .= '-' . $this->encodeClickId($aff_click_id);
        Am_Controller::setCookie(self::COOKIE_NAME, $val, $tm, '/', $_SERVER['HTTP_HOST']);
    }
    function encodeClickId($id)
    {
        // we use only part of key to don't give attacker enough results to guess key
        $key = crc32(substr($this->getDi()->app->getSiteKey(), 1, 9)) % 100000;
        return $id + $key;
    }
    function decodeClickId($id)
    {
        $key = crc32(substr($this->getDi()->app->getSiteKey(), 1, 9)) % 100000;
        return $id - $key;
    }
    /**
     * run payouts when scheduled
     */
    function onDaily(Am_Event $event)
    {
        $delay = $this->getConfig('payout_day');
        if (!$delay) return;
        list($count, $unit) = preg_split('/(\D)/', $delay, 2, PREG_SPLIT_DELIM_CAPTURE);
        switch ($unit)
        {
            case 'd': 
                if ($count != (int)date('d', amstrtotime($event->getDatetime())))
                    return;
                break;
            case 'w':
                $w = date('w', amstrtotime($event->getDatetime()));
                if ($count != $w)
                    return;
                break;
            default : return; // wtf?
        }
        $this->getDi()->affCommissionTable->runPayout(sqlDate($event->getDatetime()));
    }
    
    function onBuildDemo(Am_Event $event)
    {
        $user = $event->getUser();
        $user->is_affiliate = 1;
        $user->aff_payout_type = 'check';
        if (rand(0,10)<4)
        {
            $user->aff_id = $this->getDi()->db->selectCell("SELECT `id` 
                FROM ?_data 
                WHERE `table`='user' AND `key`='demo-id' AND `value`=?
                LIMIT ?d, 1", 
                $event->getDemoId(), rand(0, $event->getUsersCreated()));
        }
    }
    function onSavedFormTypes(Am_Event $event)
    {
        $event->getTable()->addTypeDef(array(
            'type' => 'aff',
            'class' => 'Am_Form_Signup_Aff',
            'title' => ___('Affiliate Signup Form'),
            'defaultTitle' => ___('Affiliate Signup Form'),
            'defaultComment' => '',
            'generateCode' => false,
            'urlTemplate'  => 'aff/signup',
            'isSingle' => true,
            'noDelete' => true,
        ));
    }
    
    function onLoadReports()
    {
        include_once APPLICATION_PATH . '/aff/library/Reports.php';
    }
    
    function sendAffRegistrationEmail(User $user){
            if ($et = Am_Mail_Template::load('aff.registration_mail', $user->lang))
            {
                $et->setUser($user);
                $et->password = $user->getPlaintextPass();
                $et->send($user);
            }                        
    }

    function onDbUpgrade(Am_Event $e)
    {
        if (version_compare($e->getVersion(), '4.2.6') < 0)
        {
            echo "Convert commission rule type...";
            if (ob_get_level()) ob_end_flush();
            $this->getDi()->db->query("UPDATE ?_aff_commission_rule SET type=?, tier=? WHERE type=?", 'global', 0, 'global-1');
            $this->getDi()->db->query("UPDATE ?_aff_commission_rule SET type=?, tier=? WHERE type=?", 'global', 1, 'global-2');
            echo "Done<br>\n";
        }
    }
}