<?php

class Am_Paysystem_Verotel extends Am_Paysystem_Abstract {
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';
    
    protected $defaultTitle = 'Verotel';
    protected $defaultDescription = 'Credit Card Payment';
    
    const URL = "https://secure.verotel.com/cgi-bin/vtjp.pl";

    public function _initSetupForm(Am_Form_Setup $form) {
        $form->addInteger('merchant_id',array('size'=>20,'maxlength'=>20))
            ->setLabel('Your Verotel Merchant ID#');
        $form->addInteger('site_id')
            ->setLabel('Verotel Site Id');
    }
    
    public function isConfigured() {
        return strlen($this->getConfig('merchant_id'));
    }

    public function init()
    {
        parent::init();
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('verotel_id', "VeroTel Site ID", 
            ""));
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result) {
        $a  = new Am_Paysystem_Action_Redirect(self::URL);
        $a->verotel_id = $this->getConfig('merchant_id');
        $a->verotel_product = $invoice->getItem(0)->getBillingPlanData("verotel_id") ?  $invoice->getItem(0)->getBillingPlanData("verotel_id") : $this->getConfig('site_id');
        $a->verotel_website = $invoice->getItem(0)->getBillingPlanData("verotel_id") ?  $invoice->getItem(0)->getBillingPlanData("verotel_id") : $this->getConfig('site_id');
        $a->verotel_usercode = $invoice->getLogin();
        $a->verotel_passcode = 'FromSignupForm';//$invoice->getUser()->getPlaintextPass();
        $a->verotel_custom1 = $invoice->public_id;        
        $a->filterEmpty();
        $result->setAction($a);
    }
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs) {
        $res = split(":", $request->get('vercode'));
        switch($request->get('trn', @$res[3])){
            case Am_Paysystem_Transaction_Verotel::ADD : 
            case Am_Paysystem_Transaction_Verotel::REBILL : 
                return new Am_Paysystem_Transaction_Verotel_Charge($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Verotel::DELETE : 
                return new Am_Paysystem_Transaction_Verotel_Cancellation($this, $request, $response,$invokeArgs);
            case Am_Paysystem_Transaction_Verotel::MODIFY : 
                return new Am_Paysystem_Transaction_Verotel_Modify($this, $request, $response,$invokeArgs);
            default : 
                print "ERROR";
                exit();
        }
        
    }

    public function getRecurringType() {
        return self::REPORTS_REBILL;        
    }

    
    function getReadme(){
        return <<<CUT
<b>Verotel payment plugin configuration</b>

Configure your Verotel Account - contact verotel support and ask
them to set:
Remote User Management script URL to
%root_url%/payment/verotel/ipn

Run a test transaction to ensure everthing is working correctly.
CUT;
    }
}


class Am_Paysystem_Transaction_Verotel extends Am_Paysystem_Transaction_Incoming{
    const ADD = 'add';
    const REBILL = 'rebill';
    const MODIFY  = 'modify';
    const DELETE = 'delete';
    
    protected $vercode;
    protected $ip  = array(
        '195.20.32.202'
    );

    public function __construct(Am_Paysystem_Abstract $plugin, Am_Request $request, Zend_Controller_Response_Http $response, $invokeArgs)
    {
        $this->vercode = split(":", $request->get('vercode'));
        parent::__construct($plugin, $request, $response, $invokeArgs);
    }

    public function findInvoiceId(){
        return $this->request->get('custom1',@$this->vercode[5]);
    }
    public function getUniqId() {
        return $this->request->get("trn_id",@$this->vercode[6]);
    }
    
    public function validateSource() {
        $this->_checkIp($this->ip);
        return true;
    }
    
    public function validateStatus() {
        return true;
    }
    
    public function validateTerms() {
        return true;
    }
    
    public function processValidated() {
        print "APPROVED";
    }    
}

class Am_Paysystem_Transaction_Verotel_Charge extends Am_Paysystem_Transaction_Verotel{
    //uncomment to allow users to change product on verotel site
    /*public function validateTerms() {
        if($this->invoice->isFirstPayment())
        {
            if(doubleval($this->request->get("amount")) == $this->invoice->first_total ) return true;
            if($bp = Am_Di::getInstance()->billingPlanTable->findFirstBy(array('first_price' => doubleval($this->request->get("amount")))))
            {
                Am_Di::getInstance()->db->query("DELETE from ?_invoice_item where invoice_id=?",$this->invoice->invoice_id);
                $this->invoice->add(Am_Di::getInstance()->productTable->load($bp->product_id),1);
                $this->invoice->calculate();
                $this->invoice->update();
                return true;
            }
            return false;
        }
        else return true;
    }*/
    public function processValidated() {
        $this->invoice->addPayment($this);
        parent::processValidated();
    }
}

class Am_Paysystem_Transaction_Verotel_Cancellation extends Am_Paysystem_Transaction_Verotel{
    public function processValidated() {
        $this->invoice->setCancelled(true);
        parent::processValidated();
    }
}

class Am_Paysystem_Transaction_Verotel_Modify extends Am_Paysystem_Transaction_Verotel{
    public function processValidated() {
        parent::processValidated();
    }
}
