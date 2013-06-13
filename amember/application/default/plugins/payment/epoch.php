<?php

class Am_Paysystem_Epoch extends Am_Paysystem_Abstract{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';

    protected $defaultTitle = 'Epoch';
    protected $defaultDescription = 'Pay by credit card/debit card';
    
    const URL = 'https://wnu.com/secure/fpost.cgi';
    
    const NO = 0; 
    const YES = 1; 
    
    protected $_canResendPostback = true;
   
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText("co_code")->setLabel(array('Company code', 'Three (3) alphanumeric ID assigned by Epoch (Company code does not change)'));
        $form->addSelect("testing", array(), array('options' => array(
                self::NO=>'No',
                self::YES =>'Yes' 
            )))->setLabel(array('Testing', 'enable/disable payments with test credit cars ask Epoch  support for test credit card numbers'));
        
        $form->addSelect("ach_form", array(), array('options' => array(
                self::NO=>'No',
                self::YES =>'Yes' 
            )))->setLabel(array('Enable ACH Flag', 'If this field is passed in it will enable online check (ACH) processing. Online check processing is only valid for US.'));
        
    }
    
    public function init()
    {
        parent::init();
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('epoch_product_id', "Epoch Product ID",
                    "you must create the same product<br />in Epoch and enter its number here"));
        
    }
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Form(self::URL);
        $a->co_code =   $this->getConfig('co_code');
		$a->pi_code  =   $invoice->getItem(0)->getBillingPlanData('epoch_product_id');
        $a->reseller    =   'a';
        $a->zip =   $invoice->getZip();
        $a->email   =   $invoice->getEmail();
        $a->country =   $invoice->getCountry();
        $a->no_userpass =   self::YES;
        $a->name    =   $invoice->getName();
        $a->street  =   $invoice->getStreet();
        $a->phone   =   $invoice->getPhone();
        $a->city    =   $invoice->getCity();
        $a->state   =   $invoice->getState();
        $a->pi_returnurl =  $this->getPluginUrl("thanks");
        $a->response_post   =   self::YES;
        $a->x_payment_id    =   $invoice->public_id;
        if($this->getConfig('ach_form') == self::YES) $a->ach_form = self::YES;
        $result->setAction($a);
        
    }
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Epoch_IPN($this, $request, $response, $invokeArgs);
    }
    
    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    function getReadme(){
        $root_url = $this->getDi()->config->get('root_url');
        return <<<CUT

<b>Epoch payment plugin</b>
----------------------------------------------------------------------

 - Set up products with the same settings as you have defined in 
   aMember. 
   Then enter PayCom Product IDs into corresponding field in aMember 
   Product settings (aMember Cp -> Manage Products->Edit product -> Billing terms)
   
 - Set up the data postback URL to 
   {$root_url}/payment/paycom/ipn
CUT;
    }
    public function thanksAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        $log = $this->logRequest($request);
        $transaction = new Am_Paysystem_Transaction_Epoch_Thanks($this, $request, $response, $invokeArgs);
        $transaction->setInvoiceLog($log);
        try {
            $transaction->process();
        } catch (Exception $e) {
            throw $e;
            $this->getDi()->errorLogTable->logException($e);
            throw Am_Exception_InputError(___("Error happened during transaction handling. Please contact website administrator"));
        }
        $log->setInvoice($transaction->getInvoice())->update();
        $this->invoice = $transaction->getInvoice();
        $response->setRedirect($this->getReturnUrl());
    }
    
}

class Am_Paysystem_Transaction_Epoch_IPN extends Am_Paysystem_Transaction_Incoming{
    protected $ip = array(array('208.236.105.0', '208.236.105.255'),
			    array('65.17.248.0', '65.17.248.255'));
    
    public function getUniqId()
    {
        return $this->request->get('order_id');
    }
    
    function findInvoiceId()
    {
        return $this->request->get("x_payment_id");
        
    }
    public function validateSource()
    {
        $this->_checkIp($this->ip);
        return true;
    }
    public function validateStatus()
    {
        if(substr($this->request->get('ans'),0,1) != 'Y')
            throw new Am_Exception_Paysystem_TransactionInvalid('Transaction declined!');
        if((strstr($this->request->get('ans'), 'YGOODTEST') !== false) && ($this->getPlugin()->getConfig('testing') != Am_Paysystem_Epoch::YES))
            throw new Am_Exception_Paysystem_TransactionInvalid("Received test result but test mode is not enabled!");
        
        return true;
    }
    public function validateTerms()
    {
        return true;
    }
    
    
    function processValidated()
    {
        $this->invoice->addPayment($this);
        print "OK";
    }
    
    function getInvoice(){
        return $this->loadInvoice($this->findInvoiceId());
    }
}
class Am_Paysystem_Transaction_Epoch_Thanks extends Am_Paysystem_Transaction_Epoch_IPN {
    public function validateSource(){
        return true;
    }
    
    public function processValidated(){
        return;
    }
}
    
