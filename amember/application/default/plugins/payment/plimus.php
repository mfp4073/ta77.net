<?php

class Am_Paysystem_Plimus extends Am_Paysystem_Abstract {
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';
    
    protected $defaultTitle = 'Plimus';
    protected $defaultDescription = 'Credit Card Payment';
    
    const URL = "https://www.plimus.com/jsp/buynow.jsp";
    const TESTING_URL = "https://sandbox.plimus.com/jsp/buynow.jsp";
    const MODE_LIVE = 'live';
    const MODE_SANDBOX = 'sandbox';
    const MODE_TEST = 'test';
    public function _initSetupForm(Am_Form_Setup $form) {
        $s = $form->addSelect("testing")
             ->setLabel("test Mode Enabled");
        $s->addOption("Live account", self::MODE_LIVE);
        $s->addOption("Sandbox account", self::MODE_SANDBOX);
//        $s->addOption("Account in test mode", self::MODE_TEST);
    }

    public function init()
    {
        parent::init();
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('plimus_contract_id', "Plimus Contract ID", 
            "You must enter the contract id of Plimus product.<br/>Plimus contract must have the same settings as amember product."));
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result) {
        $a  = new Am_Paysystem_Action_Form((($this->getConfig('testing')==self::MODE_SANDBOX) ? self::TESTING_URL : self::URL));
        $a->contractId = $invoice->getItem(0)->getBillingPlanData("plimus_contract_id");
        $a->custom1 = $invoice->public_id;
        $a->member_id = $invoice->user_id;
        $a->currency = strtoupper($invoice->currency);
        $a->firstName = $invoice->getFirstName();
        $a->lastName = $invoice->getLastName();
        $a->email = $invoice->getEmail();
        $a->overridePrice = sprintf("%.2f",$invoice->first_total);
        $a->overrideRecurringPrice = sprintf("%.2f",$invoice->second_total);
        if($this->getConfig('testing') == self::MODE_TEST){
            $a->testMode=Y;
        }
        $a->filterEmpty();
        $result->setAction($a);
    }
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs) {
        switch($request->get("transactionType")){
            case Am_Paysystem_Transaction_Plimus::CHARGE : 
            case Am_Paysystem_Transaction_Plimus::RECURRING : 
            case Am_Paysystem_Transaction_Plimus::AUTH_ONLY : 
                return new Am_Paysystem_Transaction_Plimus_Charge($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Plimus::CANCELLATION : 
                return new Am_Paysystem_Transaction_Plimus_Cancellation($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Plimus::REFUND : 
                return new Am_Paysystem_Transaction_Plimus_Refund($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Plimus::CANCELLATION_REFUND : 
                return new Am_Paysystem_Transaction_Plimus_Cancellation_Refund($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Plimus::CONTRACT_CHANGE : 
                return new Am_Paysystem_Transaction_Plimus_Contract_Change($this, $request, $response,$invokeArgs);
            default : return null;
        }
        
    }

    public function getRecurringType() {
        return self::REPORTS_REBILL;        
    }

    
    function getReadme(){
        return <<<CUT
<b>Plimus payment plugin configuration</b>
Up to date instructions can be found at <a href='http://www.amember.com/docs/Plimus_Plugin_Configuration'>http://www.amember.com/docs/Plimus_Plugin_Configuration</a>
CUT;
    }
}


class Am_Paysystem_Transaction_Plimus extends Am_Paysystem_Transaction_Incoming{
    const REFUND = 'REFUND';
    const CHARGE = 'CHARGE';
    const RECURRING  = 'RECURRING';
    const AUTH_ONLY = 'AUTH_ONLY';
    const CANCELLATION_REFUND = 'CANCELLATION_REFUND';
    const CANCELLATION = 'CANCELLATION';
    const CONTRACT_CHANGE = 'CONTRACT_CHANGE';
    
    protected $ip  = array(
        array('62.216.234.196', '62.216.234.222'), 
        array('72.20.107.242', '72.20.107.250'), 
        array('209.128.93.97', '209.128.93.110'), 
        array('209.128.93.225', '209.128.93.255')
    );
    
   
    public function findInvoiceId(){
        return $this->request->get('custom1');
    }
    public function getUniqId() {
        return $this->request->get("referenceNumber");
    }
    
    public function validateSource() {
        $this->_checkIp($this->ip);
        if(($this->plugin->getConfig('testing') != Am_Paysystem_Plimus::MODE_TEST) && ($this->request->get('testMode') == 'Y')){
            throw new Am_Exception_Paysystem_TransactionInvalid("Received test IPN message but test mode is not enabled!");
        }
        return true;
    }
    
    public function validateStatus() {
        return true;
    }
    
    public function validateTerms() {
        return true;
    }
    
}

class Am_Paysystem_Transaction_Plimus_Charge extends Am_Paysystem_Transaction_Plimus{
    public function validateTerms() {
        $amount = ($this->invoice->currency == 'USD') ? $this->request->get('invoiceAmountUSD') : $this->request->get('invoiceChargeAmount'); 
        $message = $this->request->get('transactionType');
        return ($amount == (($message == self::CHARGE) || ($message == self::AUTH_ONLY) ? $this->invoice->first_total : $this->invoice->second_total)); 
    }
    public function processValidated() {
        $this->invoice->addPayment($this);
    }
}

class Am_Paysystem_Transaction_Plimus_Cancellation extends Am_Paysystem_Transaction_Plimus{
    public function processValidated() {
        $this->invoice->setCancelled(true);
    }
}

class Am_Paysystem_Transaction_Plimus_Refund extends Am_Paysystem_Transaction_Plimus{
    public function processValidated() {
        $this->invoice->addRefund($this, $this->getReceiptId(), $this->getAmount());
    }
}
class Am_Paysystem_Transaction_Plimus_Cancellation_Refund extends Am_Paysystem_Transaction_Plimus{
    public function processValidated() {
        $this->invoice->setCancelled(true);
        $this->invoice->addRefund($this, $this->getReceiptId(), $this->getAmount());
    }
}

class Am_Paysystem_Transaction_Plimus_Contract_Change  extends Am_Paysystem_Transaction_Plimus{
    public function processValidated() {
        throw new Am_Exception_Paysystem_NotImplemented("Not implemented");
    }
}

?>
