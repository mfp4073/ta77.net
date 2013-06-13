<?php

class Am_Paysystem_Epay extends Am_Paysystem_CreditCard
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const FORM_URL = 'https://ssl.ditonlinebetalingssystem.dk/auth/default.aspx';
    const SUBSCRIPTIONID = 'epay_subscriptionid';

    protected $defaultTitle = "Pay with your Credit Card";
    protected $defaultDescription  = "accepts all major credit cards";
    

    public function getRecurringType()
    {
        return self::REPORTS_CRONREBILL;
    }
    
    function storesCcInfo(){
	return false;
    }
    function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Form('https://ssl.ditonlinebetalingssystem.dk/popup/default.asp');
        $a->language        =   $this->getConfig('language', 2); // English default for testing.
        $a->merchantnumber  =   $this->getConfig('id');
        $a->orderid         =   $invoice->public_id;
        $a->currency        =   Am_Currency::getNumericCode($invoice->currency);
        $a->amount          =   $invoice->first_total * 100;
        $a->accepturl       =   $this->getReturnUrl();
        $a->declineurl      =   $this->getCancelUrl();
        $a->callbackurl     =   $this->getPluginUrl('ipn');
        $a->instantcallback =   1; // Call callback before user returned to accept_url
        $a->instantcapture  =   1;
        $a->ordertext       =   $invoice->getLineDescription();
        $a->windowstate     =   2;
        if ($invoice->rebill_times)
        {
            $a->subscription = 1;
            $a->subscriptionname  = sprintf('Invoice %s, User %s', $invoice->public_id, $invoice->getLogin());
        }
        $a->md5key = $this->getOutgoingMd5($a);
        $result->setAction($a);
    }
    function getSupportedCurrencies()
    {
        return array('EUR', 'USD', 'GBP', 'DKK', 'NOK', 'SEK');
    }
    function renameElement(Am_Form $form, $from, $to)
    {
        foreach ($form->getElementsByName($from) as $el)
            $el->setName($to);
    }
    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc=null, Am_Paysystem_Result $result)
    {
        $transaction = new Am_Paysystem_Transaction_EpaySale($this, $invoice, null, $doFirst);
        $transaction->run($result);
    }
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('id')->setLabel('MerchantNumber');
        $form->addText('key')->setLabel('Security Key');
        $form->addSelect('language', array(), array('options' => array(
            2   =>  'English',
            1   =>  'Danish',
            3   =>  'Swedish',
            4   =>  'Norwegian',
            5   =>  'Greenland',
            6   =>  'Iceland',
            7   =>  'German',
            8   =>  'Finnish',
            9   =>  'Spanish'
        )))->setLabel('Language');
    }
    public function isConfigured()
    {
        return strlen($this->getConfig('id')) && strlen($this->getConfig('key'));
    }
    
    
    function getEpayError($epay_code, $pbs_code){
        $result = $this->APIRequest("subscription", "getEpayError", array(
            'merchantnumber' => $this->getConfig('id'),
	    'language'	=>	$this->getConfig('language'),
            'epayresponsecode' => $epay_code
            ));   

        $result1 = $this->APIRequest("subscription", "getPbsError", array(
            'merchantnumber' => $this->getConfig('id'),
	    'language'	=>	$this->getConfig('language'),
            'pbsResponseCode' => $pbs_code
            ));   
            
        $xml = $this->getResponseXML($result);
        $xml1 = $this->getResponseXML($result1);
        return $xml->getEpayErrorResponse->epayResponseString."<br/>".$xml1->getPbsErrorResponse->pbsResponseString;
     
    }
    
    
    /**
     *
     * @param String $response
     * @return SimpleXmlElement 
     */
    public function getResponseXML($response){
        if(!$response)
            throw new Am_Exception_InternalError("Can't cancel subscription. Empty result received from epay server!");
        
        // We do this to not deal with namespaces.
        $response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);

        $xml = simplexml_load_string($response);
        if($xml === false) 
            throw new Am_Exception_InternalError("Can't parse XML!. Got response: $response");
        return $xml->soapBody;
    }
    public function cancelInvoice(Invoice $invoice)
    {
        $subscriptionid = $invoice->data()->get(self::SUBSCRIPTIONID);

        if(!$subscriptionid) 
            throw new Am_Exception_InternalError('Subscriptionid is empty in invoice! Nothing to cancel. ');

        $result = $this->APIRequest("subscription", "deletesubscription", array(
            'merchantnumber' => $this->getConfig('id'),
            'subscriptionid'=>$subscriptionid
            ));   
            
        $xml = $this->getResponseXML($result);
        
        if($xml->deletesubscriptionResponse->deletesubscriptionResult != 'true')
        {
            throw new Am_Exception_InternalError("Subscription was not cancelled! Got: ".$result);
        }
        // Cancelled;
        return ;
    }
    
    
    function createXML($type, $method, $vars){
        $request = <<<CUT
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
  </soap:Body>
</soap:Envelope>        
CUT;
        $x = new SimpleXMLElement($request);
        $ns = $x->getNamespaces();
        $m = $x->children($ns['soap'])->addChild($method,"", 'https://ssl.ditonlinebetalingssystem.dk/remote/'.$type);
        foreach($vars as $k=>$v){
            $m->addChild($k, $v);
        }
        $xml = $x->asXML();
        return $xml;
    }
    function APIRequest($type='subscription', $function='',$vars=array()){
        try{
            
            $client = new Am_HttpRequest(sprintf("https://ssl.ditonlinebetalingssystem.dk/remote/%s.asmx?op=%s", $type, $function), Am_HttpRequest::METHOD_POST);
            $client->setHeader('Content-type', 'text/xml');
            $client->setHeader('SOAPAction', sprintf("https://ssl.ditonlinebetalingssystem.dk/remote/%s/%s", $type, $function));
            $client->setBody($xml = $this->createXML($type, $function, $vars));
            $response = $client->send();
            
        }catch(Exception $e){
            $this->getDi()->errorLogTable->logException($e);
            throw new Am_Exception_InputError("Unable to contact webservice. Got error: ".$e->getMessage());
        }
        if(!$response->getBody())
            throw new Am_Exception_InputError("Empty response received from API");
            
        return $response->getBody();
        
    }
    function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Epay($this, $request, $response, $invokeArgs);
        
    }
    function getOutgoingMd5(Am_Paysystem_Action $a)
    {
        return md5($a->currency.$a->amount.$a->orderid.$this->getConfig('key'));
    }
    
    function getIncomingMd5(Am_Request $r)
    {
        $key = md5($s = $r->get('amount').$r->get('orderid').$r->get('tid').$this->getConfig('key'));
        return $key;
    }
    
    function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount)
    {
        
        $result = $this->APIRequest("payment", "credit", array(
            'merchantnumber' => $this->getConfig('id'),
            'transactionid'=> $payment->receipt_id,
            'amount'       =>   $amount*100
            ));   
            
        $xml = $this->getResponseXML($result);
        
        if($xml->creditResponse->creditResult == 'true'){
            $trans = new Am_Paysystem_Transaction_Manual($this);
            $trans->setAmount($amount);
            $trans->setReceiptId($payment->receipt_id.'-epay-refund');
            $result->setSuccess($trans);
        }else{
            $result->setFailed(array('Error Processing Refund!'));
        }
        
    }
    
    function getReadme(){
        return <<<CUT
Security Key  must be entered in the payment system. 
To do so you must login to the payment system admin and goto 
"Settings" -> "Payment system" -> "Go to settings for the "Payment system"

Here you have 3 options for MD5 Security check
1: Don't want to use MD5	Disable the MD5 security feature
2: On accepturl only	Only your accepturl will be using MD5 security check
3: On accepturl and by authorization	Your accepturl and the authorization will be using MD5 security check

Select option 2 or 3 then enter a MD5 security key in the "key" field
CUT;
        
    }
    
    
    
    
}

class Am_Paysystem_Transaction_Epay  extends Am_Paysystem_Transaction_Incoming{
    

    public function getUniqId()
    {
        return $this->request->get('tid');
    }
    public function findInvoiceId()
    {
        return $this->request->get('orderid');
    }
    public function validateSource()
    {
        return $this->getPlugin()->getIncomingMd5($this->request) == $this->request->get('eKey');
    }
    public function validateStatus()
    {
        return true;
    }
    public function validateTerms()
    {
        return $amount == ($this->first_total *100);
    }
    public function processValidated()
    {
        $this->invoice->addPayment($this);
        if($this->request->get('subscriptionid')){
            $this->invoice->data()->set(Am_Paysystem_Epay::SUBSCRIPTIONID, $this->request->get('subscriptionid'))->update();
        }
    }
    
}

class Am_Paysystem_Transaction_EpaySale extends Am_Paysystem_Transaction_CreditCard
{
    
    protected $ret;
    public function run(Am_Paysystem_Result $result)
    {

        $subscriptionid = $this->invoice->data()->get(Am_Paysystem_Epay::SUBSCRIPTIONID);

        $req = $this->plugin->APIRequest('subscription', 'authorize', $vars= array(
                'merchantnumber'    =>  $this->plugin->getConfig('id'),
                'subscriptionid'    =>  $subscriptionid,
                'orderid'           =>  $this->invoice->public_id."-".$this->invoice->getPaymentsCount(),
                'amount'            =>  $this->invoice->second_total*100,
                'currency'          =>  Am_Currency::getNumericCode($this->invoice->currency),
                'instantcapture'    =>  1,
                'description'       =>  'Recurring payment for invoice '.$this->invoice->public_id, 
                'email'             =>  $this->invoice->getEmail(), 
                'ipaddress'         =>  $this->invoice->getUser()->remote_addr
            ));
           $log = $this->getInvoiceLog();
                   $log->add(print_r($vars, true));

        $this->ret = $this->plugin->getResponseXML($req);
        
                                   $log->add(print_r($this->ret, true));
        
        if($this->ret->authorizeResponse->authorizeResult != 'true')
        {
            $result->setFailed(___("Payment failed"). ":" . $this->plugin->getEpayError($this->ret->authorizeResponse->epayresponse));
        }
        else
        {
            $result->setSuccess($this);
            $this->processValidated();
        }
    
    }
    
    
    
    public function getUniqId()
    {
        return $this->ret->authorizeResponse->transactionid;
    }
    
    public function parseResponse()
    {
        
    }
    public function validate() 
    {
        $this->result->setSuccess($this);
    }
    public function processValidated()
    {
        $this->invoice->addPayment($this);
    }
    
}