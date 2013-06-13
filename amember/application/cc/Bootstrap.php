<?php

class Bootstrap_Cc extends Am_Module
{
    public function init()
    {
        $this->getDi()->plugins_payment->addPath(dirname(__FILE__) . '/plugins');
    }
    function onSetupEmailTemplateTypes(Am_Event $event)
    {
        $event->addReturn(array(
                'id' => 'cc_rebill_failed',
                'title' => 'Cc Rebill Failed',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ), 'cc_rebill_failed');
        $event->addReturn(array(
                'id' => 'cc_rebill_failed_admin',
                'title' => 'Cc Rebill Failed Admin',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ), 'cc_rebill_failed_admin');
        $event->addReturn(array(
                'id' => 'cc_rebill_success',
                'title' => 'Cc Rebill Success',
                'mailPeriodic' => Am_Mail::USER_REQUESTED,
                'vars' => array('user'),
            ), 'cc_rebill_success');
    }
    public function onAdminWarnings(Am_Event $event)
    {
        $this->getDi()->plugins_payment->loadEnabled()->getAllEnabled();
        $setupUrl = REL_ROOT_URL . "/admin-setup";
        ///check for configuration problems
        $has_cc_fields = class_exists('Am_Paysystem_CreditCard', false);
        if ($has_cc_fields && !$this->getDi()->config->get('use_cron'))
        {
            $event->addReturn("Enable and configure external cron (<a href=\"$setupUrl/advanced\" target=_blank>aMember CP -> Setup -> Advanced</a>) if you are using credit card payment plugins");
            try
            {
                $crypt = $this->getDi()->crypt;
            } catch (Am_Exception_Crypt $e)
            {
                $event->addReturn("Encryption subsystem error: " . $e->getMessage());
            }
            //
            if (!extension_loaded("curl") && !$this->getDi()->config->get('curl'))
                $event->addReturn("You must <a href='$setupUrl/advanced'>enter cURL path into settings</a>, because your host doesn't have built-in cURL functions.");
        }
    }
    public function onHourly(Am_Event $event)
    {
        foreach ($this->getPlugins() as $ps)
            $ps->ccRebill($this->getDi()->sqlDate);
    }
    /** @return array of Am_Paysystem_CreditCard */
    public function getPlugins()
    {
        $this->getDi()->plugins_payment->loadEnabled();
        $ret = array();
        foreach ($this->getDi()->plugins_payment->getAllEnabled() as $ps)
            if ($ps instanceof Am_Paysystem_CreditCard)
                $ret[] = $ps;
        return $ret;
    }
    public function onUserAfterDelete(Am_Event_UserAfterDelete $event)
    {	
        $this->getDi()->ccRecordTable->deleteByUserId($event->getUser()->pk());
    }
    function onUserTabs(Am_Event_UserTabs $event)
    {
        if ($event->getUserId() > 0)
            $event->getTabs()->addPage(array(
                'id' => 'cc',
                'module' => 'cc',
                'controller' => 'admin',
                'action' => 'info-tab',
                'params' => array(
                    'user_id' => $event->getUserId(),
                ),
                'label' => ___('Credit Cards'),
                'order' => 900,
                'resource' => 'cc',
            ));
    }    
    function onAdminMenu(Am_Event $event)
    {
        $parent = $event->getMenu()->findBy('id', 'utilites');
        if (!$parent) $parent = $event->getMenu();
        $parent->addPage(array(
            'id' => 'ccrebills',
            'module' => 'cc',
            'controller' => 'admin-rebills',
            'label' => ___('Credit Card Rebills'),
            'resource' => 'cc',
        ));
        /* disabled  until real-life tested
        if (count($this->getPlugins()) > 1)
        {
            $parent->addPage(array(
                'id' => 'cc-change',
                'module' => 'cc',
                'controller' => 'admin',
                'action' => 'change-paysys',
                'label' => 'Change Paysystem',
            ));
        }
         * 
         */
    }        
    function onGetPermissionsList(Am_Event $event)
    {
        $event->addReturn(___("Can view/edit customer Credit Card information and rebills"), 'cc');
    }
    function onGetMemberLinks(Am_Event $event)
    {
        $user = $event->getUser();
        if ($user->status == User::STATUS_PENDING) return ; 
        foreach ($this->getPlugins() as $pl)
        {
            $link = $pl->getUpdateCcLink($user);
            if ($link)
                $event->addReturn(___("Update Credit Card Info"), $link);
        }
    }
}
