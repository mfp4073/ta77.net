<?php

class Am_Form_Setup_Aff extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('aff');
        $this->setTitle(___('Affiliates'));
        $this->data['help-id'] = 'Setup/Affiliates';
    }
    function initElements()
    {
         $el = $this->addMagicSelect('aff.payout_methods', array (
           'multiple' => 'multiple'), array('help-id' => '#Accepted_Payout_methods'))
             ->setLabel(___('Accepted Payout methods'));
         $el->loadOptions(Am_Aff_PayoutMethod::getAvailableOptions());

         $this->setDefault('aff.cookie_lifetime', 365);
         $this->addElement('integer', 'aff.cookie_lifetime', null, array('help-id' => '#Affiliate_Options'))
             ->setLabel(___("Affiliate Cookie Lifetime\n" . 
                 "days to store cookies about referred affiliate"));
         
         $this->setDefault('aff.commission_days', 0);
         $this->addElement('integer', 'aff.commission_days')
             ->setLabel(___("User-Affiliate Relation Lifetime\n".
                 "how long (in days) calculate commission for referred affiliate (default: 0 - forever)"));

         $this->addElement('select', 'aff.signup_type', null, array('help-id' => '#Affiliate_Options'))
             ->setLabel(___("Affiliates Signup Type"))
             ->loadOptions(
                 array (
                   '' => ___('Default - user clicks a link to become affiliate'),
                   1 => ___('All new users automatically become affiliates'),
                   2 => ___('Only admin can enable user as an affiliate'),
                 )
         );

         $this->addElement('email_checkbox', 'aff.registration_mail')
             ->setLabel(___("Affiliate Registration E-Mail"));

         $this->addElement('email_checkbox', 'aff.mail_sale_admin', null, array('help-id' => '#Setting_Up_Commission_Notification_Emails'))
             ->setLabel(___("E-Mail Commission to Admin"));

         $this->addElement('email_checkbox', 'aff.mail_sale_user', null, array('help-id' => '#Setting_Up_Commission_Notification_Emails'))
             ->setLabel(___('E-Mail Commission to Affiliate'));

         $el = $this->addElement('select', 'aff.payout_day', null, array('help-id' => '#Affiliate_Payout_Options'))
            ->setLabel(___("Affiliates Payout Day\n". 
                "choose a day of month when payout is generated"));
         for ($i=1;$i<=28;$i++)
            $el->addOption(___("%d-th day", $i), $i . 'd');
         $wd = Zend_Registry::get('Am_Locale')->getWeekdayNames();
         for ($i=0;$i<7;$i++)
         {
            $el->addOption(___('Every %s', $wd[$i]), $i.'w'); 
         }

         $this->addElement('integer', 'aff.payout_min', null, array('help-id' => '#Affiliate_Payout_Options'))
            ->setLabel(___('Minimum Payout'));

//         $el = $this->addElement('select', 'aff.payout_delay')
//            ->setLabel(___('Delay Payout'));
//         $el->addOption('Display commissions to affilate immediately, but delay actual payout (default)', 0);
//         //$el->addOption('Delay payouts, and hide commissions from affiliates until it becomes payable', 1);
//         $el->addOption('Do not delay payouts (not recommended, high risk of fraud)', 2);

         $this->addElement('integer', 'aff.payout_delay_days', null, array('help-id' => '#Affiliate_Payout_Options'))
            ->setLabel(___('Delay Payout (days)', ''));
         
         $this->setDefault('aff.payout_delay_days', 30);

         $this->addElement('advcheckbox', 'aff.affiliate_can_view_details', null, array('help-id' => '#Affiliate_Payout_Options'))
            ->setLabel(___('Affiliate can view Sales Details'));
         
         $gr = $this->addGroup('', array('id' => 'commission'), array('help-id' => '#Affiliate_Payout_Options'))->setLabel(___('Default Commission'));
         if (Am_Di::getInstance()->affCommissionRuleTable->hasCustomRules())
         {
             $gr->addStatic()->setContent(
                 ___('Custom Commission Rules added'));
         } else {
             $rule = Am_Di::getInstance()->affCommissionRuleTable->findFirstBy(array(
                 'type' => AffCommissionRule::TYPE_GLOBAL,
                 'tier' =>0));
             $gr->addStatic()->setContent(___('Paid Signup (first customer payment)'));
             $first = $gr->addElement(new Am_Form_Element_AffCommissionSize('aff_comm[first]', null, 'first_payment'));
             $gr->addStatic()->setContent('&nbsp;&nbsp; ' . ___('Rebill'));
             $second = $gr->addElement(new Am_Form_Element_AffCommissionSize('aff_comm[recurring]', null, 'recurring'));
             $gr->addStatic()->setContent(
                 ' ' . ___('or'));
             if ($rule && !$this->isSubmitted())
             {
                 $first->getElementById('first_payment_c-0')->setValue($rule->first_payment_c);
                 $first->getElementById('first_payment_t-0')->setValue($rule->first_payment_t);
                 $second->getElementById('recurring_c-0')->setValue($rule->recurring_c);
                 $second->getElementById('recurring_t-0')->setValue($rule->recurring_t);
             }
         }
         $gr->addStatic()->setContent(
             ' <a href="'.REL_ROOT_URL.'/aff/admin-commission/p/config/index">' 
             . ___('Edit Custom Commission Rules')
             . '</a>'
         );
         $this->addElement('advcheckbox', 'aff.commission_include_tax')
            ->setLabel(___("Calculate affiliate commissions from totals including tax\n".
                "by default commission calculated from amounts before tax"));
         
    }
    public function beforeSaveConfig(Am_Config $before, Am_Config $after)
    {
        $arr = $after->getArray();
        
        if (empty($arr['aff_comm']))
            return;
        
        $this->rule = Am_Di::getInstance()->affCommissionRuleTable->findFirstBy(array(
            'type' => AffCommissionRule::TYPE_GLOBAL,
            'tier' => 0));
        if (empty($this->rule))
        {
            $this->rule = Am_Di::getInstance()->affCommissionRuleTable->createRecord();
            $this->rule->type = AffCommissionRule::TYPE_GLOBAL;
            $this->rule->tier = 0;
            $this->rule->comment = "Default Commmission";
        }
        foreach ($arr['aff_comm'] as $aa)
            foreach ($aa as $k => $v)
                $this->rule->set($k, $v);
        unset($arr['aff_comm']);
        
        $after->setArray($arr);
    }
    public function afterSaveConfig(Am_Config $before, Am_Config $after)
    {
        if (!empty($this->rule))
            $this->rule->save();
    }
}
