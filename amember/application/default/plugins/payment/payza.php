<?php

class Am_Paysystem_Payza extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';

    protected $defaultTitle = 'Payza (formerly AlertPay)';

    protected function getURL()
    {
        return $this->getConfig('testing') ?
            "https://sandbox.payza.com/sandbox/payprocess.aspx" :
            "https://secure.payza.com/checkout";
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('merchant', array('size' => 20))
            ->setLabel('Payza Account');
        $form->addAdvCheckbox('testing')
            ->setLabel('Sandbox testing');
    }

    public function getSupportedCurrencies()
    {
        //https://dev.payza.com/resources/references/currency-codes
        return array('AUD', 'BGN', 'CAD', 'CHF', 'CZK', 'DKK', 'EEK',
            'EUR', 'GBP', 'HKD', 'HUF', 'INR', 'LTL', 'MYR', 'MKD',
            'NOK', 'NZD', 'PLN', 'RON', 'SEK', 'SGD', 'USD', 'ZAR');
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Redirect($this->getURL());
        $a->ap_merchant = $this->getConfig('merchant');
        $a->ap_itemname = $invoice->getLineDescription();
        $a->ap_currency = $invoice->currency;
        $a->apc_1 = $invoice->public_id;

        $invoice->second_total > 0 ?
                $this->buildSubscriptionParams($a, $invoice) :
                $this->buildItemParams($a, $invoice);

        $a->ap_returnurl = $this->getReturnUrl();
        $a->ap_cancelurl = $this->getCancelUrl();

        $a->ap_ipnversion = 2;
        $a->ap_alerturl = $this->getPluginUrl('ipn');
        ;

        $result->setAction($a);
    }

    protected function buildSubscriptionParams(Am_Paysystem_Action_Redirect $a, Invoice $invoice)
    {
        $a->ap_purchasetype = 'subscription';

        $a->ap_trialamount = $invoice->first_total;
        $period = new Am_Period();
        $period->fromString($invoice->first_period);
        $a->ap_trialtimeunit = $this->translatePeriodUnit($period->getUnit());
        $a->ap_trialperiodlength = $period->getCount();

        $a->ap_amount = $invoice->second_total;
        $period = new Am_Period();
        $period->fromString($invoice->second_period);
        $a->ap_timeunit = $this->translatePeriodUnit($period->getUnit());
        $a->ap_periodlength = $period->getCount();

        $a->ap_periodcount = $invoice->rebill_times == IProduct::RECURRING_REBILLS ? 0 : $invoice->rebill_times;
    }

    protected function buildItemParams(Am_Paysystem_Action_Redirect $a, Invoice $invoice)
    {
        $a->ap_purchasetype = 'item';
        $a->ap_amount = $invoice->first_total;
    }

    protected function translatePeriodUnit($unit)
    {
        switch ($unit)
        {
            case Am_Period::DAY :
                return 'Day';
            case Am_Period::MONTH :
                return 'Month';
            case Am_Period::YEAR :
                return 'Year';
            default:
                throw new Am_Exception_InternalError(sprintf('Unknown period unit type [%s] in %s->%s', $unit, __CLASS__, __METHOD__));
        }
    }

    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    public function getReadme()
    {
        $ipn = $this->getPluginUrl('ipn');
return <<<CUT

    Payza payment plugin installation

1. In Payza control panel, enable IPN:
    - Login to your Payza account.
    - Click on “Main Menu”.
    - Under “Manage My Business”, click on “IPN Advanced Integration”.
    - Click on “IPN Setup”.
    - Enter your Transaction PIN and click on “Access”.
    - Click on the “Edit” icon for the respective business profiles.

    This is for Business accounts only. Ignore this step
    if you only have one business profile on your account

    - Enter the information:
        - For IPN Status, select “Enabled”.
        - For Alert URL, enter $ipn
        - For Enable IPN Version 2, select “Enabled”

    - Click on “Update” button.
2. Configure Payza plugin at aMember CP -> Setup -> Payza

CUT;

    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Payza($this, $request, $response, $invokeArgs);
    }

}

class Am_Paysystem_Transaction_Payza extends Am_Paysystem_Transaction_Incoming
{
    const INVALID_TOKEN = 'INVALID TOKEN';

    protected $ipnData = null;

    protected function getIPN2HandlerURL()
    {
        return $this->getPlugin()->getConfig('testing') ?
            "https://sandbox.Payza.com/sandbox/IPN2.ashx" :
            "https://secure.payza.com/ipn2.ashx";
    }

    public function validateSource()
    {
        $token = $this->request->getParam('token');

        $request = new Am_HttpRequest($this->getIPN2HandlerURL(), Am_HttpRequest::METHOD_POST);
        $request->addPostParameter('token', $token);
        $response = $request->send();

        $body = $response->getBody();

        if ($body == self::INVALID_TOKEN)
            throw new Am_Exception_Paysystem_TransactionInvalid(sprintf("Invalid Token [%s] passed.", $token));

        parse_str(urldecode($body), $this->ipnData);
        $this->log->add($this->ipnData);
        return true;
    }

    public function validateStatus()
    {
        return in_array($this->ipnData['ap_status'], array('Success', 'Subscription-Payment-Success'));
    }

    public function validateTerms()
    {
        return $this->ipnData['ap_amount'] == $this->invoice->first_total;
    }

    public function findInvoiceId()
    {
        return $this->ipnData['apc_1'];
    }

    public function getUniqId()
    {
        return $this->ipnData['ap_referencenumber'];
    }

}