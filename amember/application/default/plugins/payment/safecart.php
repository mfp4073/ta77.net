<?php
class Am_Paysystem_Safecart extends Am_Paysystem_Abstract{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';

    protected $defaultTitle = 'SafeCart';
    protected $defaultDescription = 'Credit card & Paypal';
    protected $_canResendPostback = true;
    
    function _initSetupForm(Am_Form_Setup $form) {
        $form->addText("username")->setLabel('Your SafeCart username');
        return $form;
    }

    function init(){
        parent::init();
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('safecart_sku', "SafeCart SKU",
                    "you must create the same product<br />
             in Safecart  and enter SKU here"));
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('safecart_product', "SafeCart Product",
                    "You can get it from cart url: https://safecart.com/1mtest/PRODUCT/"));
        
        
    }
    
    function getURL(Invoice $invoice){
    	/* 
    	*	Added to fix long username problem.
    	*	If username is over 15 characters we truncate it to 15 characters
    	*	This helps resolve issue we had with safecart URL versus the IPN validation
    	*	return sprintf("https://safecart.com/%s/%s/", $this->getConfig("username"), $invoice->getItem(0)->getBillingPlanData('safecart_product'));
    	*/
    	if (strlen($this->getConfig("username")) > 15)
    	{
    		$username = substr($this->getConfig("username"), 0, 15);
    	}
    	else
    	{
    		$username = $this->getConfig("username");
    	}
    	
        return sprintf("https://safecart.com/%s/%s/", $username, $invoice->getItem(0)->getBillingPlanData('safecart_product'));
    }
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result) {
        $action = new Am_Paysystem_Action_Redirect($this->getURL($invoice));
        $action->name   =   $invoice->getName();
        $action->email  =   $invoice->getEmail();
        $action->country=   $invoice->getCountry();
        $action->postal_zip =   $invoice->getZip();
        $action->__set('sku[]', $invoice->getItem(0)->getBillingPlanData('safecart_sku'));
        $action->payment_id = $invoice->public_id;
        $action->rbvar = 6; // I don't know what is it. Ported from v3 plugin
        $result->setAction($action);
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs) {
        return new Am_Paysystem_Transaction_Safecart($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType() {
        return self::REPORTS_REBILL;
    }
    function getReadme()
    {
        $rootURL = $this->getDi()->config->get('root_url');

        return <<<CUT
<b>SafeCart Payment Plugin Configuration</b>
1. Notification URL in your Safecart account should be set to $rootURL/payment/safecart/ipn
2. Notification types should be set to XML
CUT;
    }
    
}


class Am_Paysystem_Transaction_Safecart extends Am_Paysystem_Transaction_Incoming{
    protected $xml;
    protected $req;
    protected $ip = array(array('209.139.253.0', '209.139.253.255'));
    const SALE = 'sale';
    const REFUND = 'refund';
    
    function __construct(Am_Paysystem_Abstract $plugin, Am_Request $request, Zend_Controller_Response_Http $response, $invokeArgs) {
        parent::__construct($plugin, $request, $response, $invokeArgs);
        $this->req = $request->getRawBody();
        $this->xml = simplexml_load_string($this->req);
    }
    
    public function getUniqId() {
        return (string)$this->xml->attributes()->id;
    }
    
    public function validateSource() {
        $this->_checkIp($this->ip);
        if($this->xml === false){
            throw new Am_Exception_Paysystem_TransactionInvalid("Invalid input type. Make sure that postback notifications type is set to XML");
        }

        if( ((string) $this->xml->attributes()->merchant) != $this->plugin->getConfig('username'))
            throw new Am_Exception_Paysystem_TransactionSource("Merchant ID is not correct for received transaction!");
        return true;
        
    }

    public function findInvoiceId(){
        $data = (string) $this->xml->extra->request;
        parse_str(urldecode($data), $req);
        return @$req['payment_id'];
    }

    public function validateStatus() {
        return true;
    }
    
    public function validateTerms() {
        if((string) $this->xml->event->attributes()->type == self::SALE){
            $amount = (float) $this->xml->event->sale->attributes()->amount;
            if($this->xml->event->tax)
                $amount = $amount - (float) $this->xml->event->tax->attributes()->amount;
            if(floatval($this->invoice->first_total) != floatval($amount)){
                throw new Am_Exception_Paysystem_TransactionInvalid("Incorrect payment amount");
            }
        }
        return true;
    }
    function processValidated() {
        switch($this->xml->event->attributes()->type){
            case self::SALE : 
                $this->invoice->addPayment($this);
                break; 
            case self::REFUND : 
                $this->invoice->addRefund($this, $this->getReceiptId(), abs($this->xml->sale->amount));
                break; 
        }
    }
    function setInvoiceLog(InvoiceLog $log)
    {
        parent::setInvoiceLog($log);
        $this->getPlugin()->logOther('SAFECART IPN:', $this->req);
    }

    
}
