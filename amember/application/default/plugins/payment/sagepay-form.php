<?php

class Am_Paysystem_SagepayForm extends Am_Paysystem_Abstract {
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';
    
    protected $defaultTitle = 'Sagepay Form';
    protected $defaultDescription = 'Pay by credit card';
    
    const TEST_URL = "https://test.sagepay.com/gateway/service/vspform-register.vsp";
    const LIVE_URL = "https://live.sagepay.com/gateway/service/vspform-register.vsp";

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('login')->setLabel(array('Your SagePay login', ''));
        $form->addPassword('pass')->setLabel(array('Your SagePay password', ''));
        $form->addAdvCheckbox('testing')->setLabel("Test Mode Enabled");        
    }
    public function init()
    {
        parent::init();
    }
    public function getSupportedCurrencies()
    {
        return array('AUD', 'CAD', 'CHF', 'DKK', 'EUR', 'GBP', 
            'HKD', 'IDR', 'JPY', 'LUF', 'NOK', 'NZD', 'SEK', 'SGD', 'TRL', 'USD');
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result) {
        $u = $invoice->getUser();
        $a  = new Am_Paysystem_Action_Form($this->getConfig('testing') ? self::TEST_URL : self::LIVE_URL);
        $a->VPSProtocol = '2.22';
        $a->TxType = 'PAYMENT';
        $a->Vendor = $this->getConfig('login');
        $vars = array(
            'VendorTxCode='.$invoice->public_id,
            'Amount='.$invoice->first_total,
            'Currency='.$invoice->currency,
            'Description='.$invoice->getLineDescription(),
            'SuccessURL='.$this->getPluginUrl('thanks'),
            'FailureURL='.$this->getCancelUrl(),
            'CustomerEmail='.$u->email,
            'VendorEmail='.$this->getDi()->config->get('admin_email'),
            'CustomerName='.$u->name_f . ' ' . $u->name_l,
        );
        $a->Crypt = base64_encode($this->sagepay_simple_xor(implode('&',$vars), $this->getConfig('pass')));
        $a->filterEmpty();
        $result->setAction($a);
    }
    public function sagepay_simple_xor($InString, $Key) {
        // Initialise key array
        $KeyList = array();
        // Initialise out variable
        $output = "";

        // Convert $Key into array of ASCII values
        for($i = 0; $i < strlen($Key); $i++){
            $KeyList[$i] = ord(substr($Key, $i, 1));
        }

        // Step through string a character at a time
        for($i = 0; $i < strlen($InString); $i++) {
            // Get ASCII code from string, get ASCII code from key (loop through with MOD), XOR the two, get the character from the result
            // % is MOD (modulus), ^ is XOR
            $output.= chr(ord(substr($InString, $i, 1)) ^ ($KeyList[$i % strlen($Key)]));
        }
        // Return the result
        return $output;
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
    }
    public function createThanksTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_SagePayForm_Thanks($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType()
    {
        return self::REPORTS_NOT_RECURRING;
    }    
}

class Am_Paysystem_Transaction_SagePayForm_Thanks extends Am_Paysystem_Transaction_Incoming
{

    public function __construct(Am_Paysystem_Abstract $plugin, Am_Request $request, Zend_Controller_Response_Http $response, $invokeArgs)
    {
        parent::__construct($plugin, $request, $response, $invokeArgs);
        $s = base64_decode(str_replace(" ", "+", $request->get("Crypt",$request->get("crypt"))));
        $s = $plugin->sagepay_simple_xor($s, $plugin->getConfig('pass'));
        parse_str($s, $this->vars);
    }

    public function getAmount()
    {
        return moneyRound($this->vars['Amount']);
    }
    
    public function getUniqId()
    {
        return $this->vars["VPSTxId"];
    }
    
    public function findInvoiceId(){
        return $this->vars["VendorTxCode"];
    }

    public function validateSource()
    {
        return true;
    }
    
    public function validateStatus()
    {
        return $this->vars['Status'] == 'OK';
    }
    public function validateTerms()
    {
        return true;
    }
    function getInvoice(){
        return $this->loadInvoice($this->findInvoiceId());
    }    
}