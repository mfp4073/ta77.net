<?php

abstract class Am_Aff_PayoutMethod
{
    static private $enabled = array();

    public function getId()
    {
        return lcfirst(str_ireplace('Am_Aff_PayoutMethod_', '', get_class($this)));
    }
    public function getTitle()
    {
        return ucfirst(str_ireplace('Am_Aff_PayoutMethod_', '', get_class($this)));
    }
    /**
     * Generate and send file or make actual payout if possible
     */
    abstract function addFields(Am_CustomFieldsManager $m);
    
    
    protected function sendCsv($filename, array $rows, Zend_Controller_Response_Http $response, $delimiter = "\t")
    {
        $response
            ->setHeader('Cache-Control', 'maxage=3600')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Content-type', 'application/csv')
            ->setHeader('Content-Disposition', 'attachment; filename='.$filename);
        foreach ($rows as & $r)
        {
            if (is_array($r))
            {
                $out = "";
                foreach ($r as $s)
                    $out .= ($out ? $delimiter : "") . '"'. str_replace('"', "'", $s) . '"';
                $out .= "\r\n";
                $r = $out;
            }
        }
        $response->appendBody(implode("", $rows));
    }
    
    abstract function export(AffPayout $payout, Am_Query $details, Zend_Controller_Response_Http $response);

    static function static_addFields()
    {
        $fieldsManager = Am_Di::getInstance()->userTable->customFields();
        foreach (self::getEnabled() as $o)
            $o->addFields($fieldsManager);
    }
    /** @return Am_Aff_PayoutMethod[] */
    static function getEnabled()
    {
        if (!self::$enabled)
        foreach (Am_Di::getInstance()->config->get('aff.payout_methods', array()) as $methodName)
        {
            $className = __CLASS__ . '_' . ucfirst($methodName);
            if (!class_exists($className)) continue;
            $o = new $className;
            self::$enabled[$o->getId()] = $o;
        }
        return self::$enabled;
    }
    static function getAvailableOptions()
    {
        $ret = array();
        foreach (get_declared_classes() as $className)
            if (strpos($className, __CLASS__ . '_')===0)
            {
                $o = new $className;
                $ret[$o->getId()] = $o->getTitle();
            }
        return $ret;
    }
    static function getEnabledOptions()
    {
        $ret = array();
        foreach (self::getEnabled() as $o)
            $ret[$o->getId()] = $o->getTitle();
        return $ret;
    }
}

class Am_Aff_PayoutMethod_Paypal extends Am_Aff_PayoutMethod
{
    public function export(AffPayout $payout, Am_Query $details, Zend_Controller_Response_Http $response)
    {
        $q = $details->query();
        while ($d = $payout->getDi()->db->fetchRow($q))
        {
            $d = $payout->getDi()->affPayoutDetailTable->createRecord($d);
            /* @var $d AffPayoutDetail */
            $aff = $d->getAff();
            $rows[] = array(
                $aff->data()->get('aff_paypal_email'),
                moneyRound($d->amount),
                Am_Currency::getDefault(),
                $aff->user_id,
                "Affiliate commission to " . amDate($payout->thresehold_date),
            );
        }
        $this->sendCsv("paypal-commission-".$payout->payout_id.".txt", $rows, $response);
    }
    public function addFields(Am_CustomFieldsManager $m) {
        $m->add(new Am_CustomFieldText('aff_paypal_email', ___('Affiliate Payout - Paypal E-Mail address'), ___('for affiliate commission payouts')))->size = 40;
    }
}

class Am_Aff_PayoutMethod_Check extends Am_Aff_PayoutMethod
{
    public function getTitle() {
        return "Offline Check";
    }
    public function export(AffPayout $payout, Am_Query $details, Zend_Controller_Response_Http $response)
    {
        $q = $details->query();
        $rows = array(array(
            "Check Payable To",
            "Street",
            "City", 
            "State",
            "Country",
            "ZIP",
            "Amount", 
            "Currency",
            "Comment",
            "Username",
        ));
        while ($d = $payout->getDi()->db->fetchRow($q))
        {
            $d = $payout->getDi()->affPayoutDetailTable->createRecord($d);
            /* @var $d AffPayoutDetail */
            $aff = $d->getAff();
            
            $rows[] = array(
                $aff->data()->get('aff_check_payable_to'),
                $aff->data()->get('aff_check_street'),
                $aff->data()->get('aff_check_city'),
                $aff->data()->get('aff_check_state'),
                $aff->data()->get('aff_check_country'),
                $aff->data()->get('aff_check_zip'),
                moneyRound($d->amount),
                Am_Currency::getDefault(),
                "Affiliate commission to " . amDate($payout->thresehold_date),
                $aff->login,
            );
        }
        $this->sendCsv("check-commission-".$payout->payout_id.".csv", $rows, $response);
    }
    public function addFields(Am_CustomFieldsManager $m) {
        $m->add(new Am_CustomFieldText('aff_check_payable_to', 'Affiliate Check - Payable To'))->size = 40;
        $m->add(new Am_CustomFieldText('aff_check_street', 'Affiliate Check - Street Address'))->size = 40;
        $m->add(new Am_CustomFieldText('aff_check_city', 'Affiliate Check - City'))->size = 40;
        $m->add(new Am_CustomFieldText('aff_check_country', 'Affiliate Check - Country'));
        $m->add(new Am_CustomFieldText('aff_check_state', 'Affiliate Check - State'));
        $m->add(new Am_CustomFieldText('aff_check_zip', 'Affiliate Check - ZIP Code'))->size = 10;
    }
}

class Am_Aff_PayoutMethod_Moneybookers extends Am_Aff_PayoutMethod
{
    public function export(AffPayout $payout, Am_Query $details, Zend_Controller_Response_Http $response)
    {
        $q = $details->query();
        $rows = array(array(
            "Moneybookers E-Mail",
            "Amount", 
            "Currency",
            "Comment",
            "Username",
        ));
        while ($d = $payout->getDi()->db->fetchRow($q))
        {
            $d = $payout->getDi()->affPayoutDetailTable->createRecord($d);
            /* @var $d AffPayoutDetail */
            $aff = $d->getAff();
            
            $rows[] = array(
                $aff->data()->get('aff_moneybookers_email'),
                moneyRound($d->amount),
                Am_Currency::getDefault(),
                "Affiliate commission to " . amDate($payout->thresehold_date),
                $aff->login,
            );
        }
        $this->sendCsv("check-commission-".$payout->payout_id.".csv", $rows, $response);
    }
    public function addFields(Am_CustomFieldsManager $m) {
        $m->add(new Am_CustomFieldText('aff_moneybookers_email', ___('Affiliate Payout - Moneybookers Account ID')))->size = 40;
    }
}

class Am_Aff_PayoutMethod_Propay extends Am_Aff_PayoutMethod
{
    public function export(AffPayout $payout, Am_Query $details, Zend_Controller_Response_Http $response)
    {
        $q = $details->query();
        while ($d = $payout->getDi()->db->fetchRow($q))
        {
            $d = $payout->getDi()->affPayoutDetailTable->createRecord($d);
            /* @var $d AffPayoutDetail */
            $aff = $d->getAff();
            $rows[] = array(
                $aff->data()->get('aff_propay_email'),
                moneyRound($d->amount),
                Am_Currency::getDefault(),
                $aff->user_id,
                "Affiliate commission to " . amDate($payout->thresehold_date),
            );
        }
        $this->sendCsv("propay-commission-".$payout->payout_id.".txt", $rows, $response);
    }
    public function addFields(Am_CustomFieldsManager $m) {
        $m->add(new Am_CustomFieldText('aff_propay_email', ___('Affiliate Payout - Propay E-Mail address'), ___('for affiliate commission payouts')))->size = 40;
    }
}

