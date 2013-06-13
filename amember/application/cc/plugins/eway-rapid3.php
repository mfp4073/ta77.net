<?php 

class Am_Paysystem_EwayRapid3 extends Am_Paysystem_CreditCard
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_DATE = '$Date$';
    const PLUGIN_REVISION = '4.2.17';

    protected $defaultTitle = "eWay Rapid3.0";
    protected $defaultDescription = "accepts all major credit cards";

    const CREATE_ACCESS_CODE_URL = 'https://api.ewaypayments.com/CreateAccessCode.xml';
    const CREATE_ACCESS_CODE_SANDBOX_URL = 'https://api.sandbox.ewaypayments.com/CreateAccessCode.xml';

    const GET_RESULT_URL = 'https://api.ewaypayments.com/GetAccessCodeResult.xml';
    const GET_RESULT_SANDBOX_URL = 'https://api.sandbox.ewaypayments.com/GetAccessCodeResult.xml';
    
    const API_TOKEN_SANDBOX_URL = 'https://www.eway.com.au/gateway/ManagedPaymentService/test/managedcreditcardpayment.asmx';
    const API_TOKEN_URL = 'https://www.eway.com.au/gateway/ManagedPaymentService/managedcreditcardpayment.asmx';

    const TOKEN = 'TokenCustomerID';

    protected $messages = array(
        'S5000' => 'System Error',
        'S5085' => 'Started 3dSecure',
        'S5086' => 'Routed 3dSecure',
        'S5087' => 'Completed 3dSecure',
        'S5099' => 'Incomplete (Access Code in progress/incomplete)',
        'V6000' => 'Validation error',
        'V6001' => 'Invalid CustomerIP',
        'V6002' => 'Invalid DeviceID',
        'V6011' => 'Invalid Payment TotalAmount',
        'V6012' => 'Invalid Payment InvoiceDescription',
        'V6013' => 'Invalid Payment InvoiceNumber',
        'V6014' => 'Invalid Payment InvoiceReference',
        'V6015' => 'Invalid Payment CurrencyCode',
        'V6016' => 'Payment Required',
        'V6017' => 'Payment CurrencyCode Required',
        'V6018' => 'Unknown Payment CurrencyCode',
        'V6021' => 'EWAY_CARDHOLDERNAME Required',
        'V6022' => 'EWAY_CARDNUMBER Required',
        'V6023' => 'EWAY_CARDCVN Required',
        'V6033' => 'Invalid Expiry Date',
        'V6034' => 'Invalid Issue Number',
        'V6035' => 'Invalid Valid From Date',
        'V6040' => 'Invalid TokenCustomerID',
        'V6041' => 'Customer Required',
        'V6042' => 'Customer FirstName Required',
        'V6043' => 'Customer LastName Required',
        'V6044' => 'Customer CountryCode Required',   
        'V6045' => 'Customer Title Required',
        'V6046' => 'TokenCustomerID Required',
        'V6047' => 'RedirectURL Required',
        'V6051' => 'Invalid Customer FirstName',
        'V6052' => 'Invalid Customer LastName',
        'V6053' => 'Invalid Customer CountryCode',
        'V6058' => 'Invalid Customer Title',
        'V6059' => 'Invalid RedirectURL',
        'V6060' => 'Invalid TokenCustomerID',
        'V6061' => 'Invalid Customer Reference',
        'V6062' => 'Invalid Customer CompanyName',
        'V6063' => 'Invalid Customer JobDescription',
        'V6064' => 'Invalid Customer Street1',
        'V6065' => 'Invalid Customer Street2',
        'V6066' => 'Invalid Customer City',
        'V6067' => 'Invalid Customer State',
        'V6068' => 'Invalid Customer PostalCode',
        'V6069' => 'Invalid Customer Email',
        'V6070' => 'Invalid Customer Phone',
        'V6071' => 'Invalid Customer Mobile',
        'V6072' => 'Invalid Customer Comments',
        'V6073' => 'Invalid Customer Fax',
        'V6074' => 'Invalid Customer URL',
        'V6075' => 'Invalid ShippingAddress FirstName',
        'V6076' => 'Invalid ShippingAddress LastName',
        'V6077' => 'Invalid ShippingAddress Street1',
        'V6078' => 'Invalid ShippingAddress Street2',
        'V6079' => 'Invalid ShippingAddress City',
        'V6080' => 'Invalid ShippingAddress State',
        'V6081' => 'Invalid ShippingAddress PostalCode',
        'V6082' => 'Invalid ShippingAddress Email',
        'V6083' => 'Invalid ShippingAddress Phone',
        'V6084' => 'Invalid ShippingAddress Country',
        'V6085' => 'Invalid ShippingAddress ShippingMethod',
        'V6086' => 'Invalid ShippingAddress Fax',
        'V6091' => 'Unknown Customer CountryCode',
        'V6092' => 'Unknown ShippingAddress CountryCode',
        'V6100' => 'Invalid EWAY_CARDNAME',
        'V6101' => 'Invalid EWAY_CARDEXPIRYMONTH',
        'V6102' => 'Invalid EWAY_CARDEXPIRYYEAR',
        'V6103' => 'Invalid EWAY_CARDSTARTMONTH',
        'V6104' => 'Invalid EWAY_CARDSTARTYEAR',
        'V6105' => 'Invalid EWAY_CARDISSUENUMBER',
        'V6106' => 'Invalid EWAY_CARDCVN',
        'V6107' => 'Invalid EWAY_ACCESSCODE',
        'V6108' => 'Invalid CustomerHostAddress',
        'V6109' => 'Invalid UserAgent',
        'V6110' => 'Invalid EWAY_CARDNUMBER'
    );
    
    protected $responseCodesFailed = array(
        'D4401' => 'Refer to Issuer',
        'D4402' => 'Refer to Issuer, special',
        'D4403' => 'No Merchant',
        'D4404' => 'Pick Up Card',
        'D4405' => 'Do Not Honour',
        'D4406' => 'Errror',
        'D4407' => 'Pick Up Card, Special',
        'D4409' => 'Request In Progress', 
        'D4412' => 'Invalid Transaction',
        'D4413' => 'Invalid Amount',
        'D4414' => 'Invalid Card Number',
        'D4415' => 'No Issuer',
        'D4419' => 'Re-enter Last Transaction',
        'D4421' => 'No Action Taken',
        'D4422' => 'Suspected Malfunction',
        'D4423' => 'Unacceptable Transaction Fee',
        'D4425' => 'Unable to Locate Record On File',
        'D4430' => 'Format Error',
        'D4433' => 'Expired Card, Capture',
        'D4434' => 'Suspected Fraud, Retain Card',
        'D4435' => 'Card Acceptor, Contact Acquirer, Retain Card',
        'D4436' => 'Restricted Card, Retain Card',
        'D4437' => 'Contact Acquirer Security Department, Retain Card',
        'D4438' => 'PIN Tries Exceeded, Capture',
        'D4439' => 'No Credit Account',
        'D4440' => 'Function Not Supported',
        'D4441' => 'Lost Card',
        'D4442' => 'No Universal Account',
        'D4443' => 'Stolen Card',
        'D4444' => 'No Investment Account',
        'D4451' => 'Insufficient Funds',
        'D4452' => 'No Cheque Account',
        'D4453' => 'No Savings Account',
        'D4454' => 'Expired Card',
        'D4455' => 'Incorrect PIN',
        'D4456' => 'No Card Record',
        'D4457' => 'Function Not Permitted to Cardholder',
        'D4458' => 'Function Not Permitted to Terminal',
        'D4459' => 'Suspected Fraud',
        'D4460' => 'Acceptor Contact Acquirer',
        'D4461' => 'Exceeds Withdrawal Limit',
        'D4462' => 'Restricted Card',
        'D4463' => 'Security Violation',
        'D4464' => 'Original Amount Incorrect',
        'D4466' => 'Acceptor Contact Acquirer, Security',  
        'D4467' => 'Capture Card',
        'D4475' => 'PIN Tries Exceeded',
        'D4482' => 'CVV Validation Error',
        'D4490' => 'Cut off In Progress',
        'D4491' => 'Card Issuer Unavailable',
        'D4492' => 'Unable To Route Transaction',
        'D4493' => 'Cannot Complete, Violation Of The Law',
        'D4494' => 'Duplicate Transaction',
        'D4496' => 'System Error'           
    );
    
    protected $responseCodesBeagle = array(
        'F7000' => 'Undefined Fraud Error',
        'F7001' => 'Challenged Fraud',
        'F7002' => 'Country Match Fraud',
        'F7003' => 'High Risk Country Fraud',
        'F7004' => 'Anonymous Proxy Fraud',
        'F7005' => 'Transparent Proxy Fraud',
        'F7006' => 'Free Email Fraud',
        'F7007' => 'Inernational Transaction Fraud',
        'F7008' => 'Risk Score Fraud',
        'F7009' => 'Denied Fraud',
        'F9010' => 'High Risk Billing Country',
        'F9011' => 'High Risk Credit Card Country',
        'F9012' => 'High Risk Customer IP Address',
        'F9013' => 'High Risk Email Address',
        'F9014' => 'High Risk Shipping Country',
        'F9015' => 'Multiple card numbers for single email address',  
        'F9016' => 'Multiple card numbers for single location',
        'F9017' => 'Multiple email addresses for single card number',
        'F9018' => 'Multiple email addresses for single location',
        'F9019' => 'Multiple locations for single card number',
        'F9020' => 'Multiple locations for single email address',
        'F9021' => 'Suspicious Customer First Name',
        'F9022' => 'Suspicious Customer Last Name',
        'F9023' => 'Transaction Declined',
        'F9024' => 'Multiple transactions for same address with known credit card',
        'F9025' => 'Multiple transactions for same address with new credit card',
        'F9026' => 'Multiple transactions for same email with new credit card',
        'F9027' => 'Multiple transactions for same email with known credit card',
        'F9028' => 'Multiple transactions for new credit card',
        'F9029' => 'Multiple transactions for known credi card',
        'F9030' => 'Multiple transactions for same credit card',
        'F9032' => 'Invalid Customer Last Name',
        'F9033' => 'Invalid Billing Street',
        'F9034' => 'Invalid Shipping Street'    
    );

    function storesCcInfo(){
        return false;
    }

    public function getErrorMessage($code)
    {
        $message = '';
        if(!empty($this->messages[$code]))
            $message .= $this->messages[$code].", ";
        if(!empty($this->responseCodesFailed[$code]))
            $message .= $this->responseCodesFailed[$code];
        if(!empty($this->responseCodesBeagle[$code]))
            $message .= $this->responseCodesBeagle[$code];
        return $message;
    }

    public function getSupportedCurrencies()
    {
        return array('USD', 'CAD', 'GBP', 'EUR', 'AUD', 'NZD');
    }

    public function getRecurringType()
    {
        return self::REPORTS_CRONREBILL;
    }

    public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result)
    { 
        //eway rapid
        if($doFirst)
        {
            $transaction = new Am_Paysystem_Transaction_EwayRapid3_RequestFormActionUrl($this, $invoice, $doFirst , $cc);
            $transaction->run($result);
            if(!$result->isSuccess())
            {
                return $result;
            }
            $response = $transaction->getResponse();
            
            $transaction = new Am_Paysystem_Transaction_EwayRapid3_RequestAccessCode($this, $invoice, $doFirst , $cc, $response);
            $transaction->run($result);
            if(!$result->isSuccess())
                return $result;
            $headers = $transaction->getResponse();
            $accessCode = preg_replace("/(.+)AccessCode=/", '', $headers['location']);
            
            $transaction = new Am_Paysystem_Transaction_EwayRapid3($this, $invoice, $doFirst, $accessCode);
            $transaction->run($result);
        }
        //token api
        else
        {
            $transaction = new Am_Paysystem_Transaction_EwayRapid3_Recurring($this, $invoice, $doFirst);
            $transaction->run($result);
        }
    }

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('apikey', 'size=40')->setLabel('Api Key')->addRule('required');
        $form->addText('password', 'size=40')->setLabel('Api Password')->addRule('required');
        $form->addText('customer_id', 'size=20')->setLabel(array('Eway Customer ID',
            'Your unique 8 digit eWAY customer ID 
                assigned to you when you join eWAY
                e.g. 1xxxxxxx.'))->addRule('required');
        $form->addText('customer_username', 'size=20')->setLabel(array('Eway Username',
            'Your username which is used to 
                login to eWAY Business Center.'))->addRule('required');
        $form->addText('customer_password', 'size=20')->setLabel(array('Eway Password',
            'Your password which is used to 
                login to eWAY Business Center.'))->addRule('required');

        $form->addAdvCheckbox('testing')->setLabel("Test Mode Enabled");
    }

    function getReadme()
    {
        return <<<CUT
<b>Recurring will work only for AU eway accounts</b>
CUT;
    }
}

class Am_Paysystem_Transaction_EwayRapid3_Recurring extends Am_Paysystem_Transaction_CreditCard
{
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice)
    {
        parent::__construct($plugin, $invoice, $plugin->createHttpRequest(), $doFirst);
        $header[] = "Content-Type: text/xml";
        $header[] = 'SOAPAction: "https://www.eway.com.au/gateway/managedpayment/ProcessPayment"';
        $this->request->setHeader($header);        
        $this->request->setBody($this->createXml($invoice, $doFirst));
        $this->request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->request->setUrl($this->plugin->getConfig('testing') ? 
            Am_Paysystem_EwayRapid3::API_TOKEN_SANDBOX_URL : 
            Am_Paysystem_EwayRapid3::API_TOKEN_URL);

    }
    protected function createXml(Invoice $invoice, $doFirst)
    {
                $request = <<<CUT
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Header>
  </soap:Header>
  <soap:Body>
  </soap:Body>
</soap:Envelope>        
CUT;
        $x = new SimpleXMLElement($request);
        $ns = $x->getNamespaces();
        $b = $x->children($ns['soap'])->Body;
        $bb = $b->addChild('ProcessPayment',"", 'https://www.eway.com.au/gateway/managedpayment');

        $h = $x->children($ns['soap'])->Header;
        $hh = $h->addChild('eWAYHeader',"", 'https://www.eway.com.au/gateway/managedpayment');
        
        $hh->addChild('eWAYCustomerID', $this->plugin->getConfig('customer_id'));
        $hh->addChild('Username', $this->plugin->getConfig('customer_username'));
        $hh->addChild('Password', $this->plugin->getConfig('customer_password'));
        
        $bb->addChild('managedCustomerID', $invoice->getUser()->data()->get(Am_Paysystem_EwayRapid3::TOKEN));
        $bb->addChild('amount', $invoice->second_total*100);
        $bb->addChild('invoiceReference', $invoice->public_id."-".sprintf("%03d", $invoice->getPaymentsCount()));
        $bb->addChild('invoiceDescription', $invoice->getLineDescription());
        $xml = $x->asXML();
        print_rr(htmlentities($xml));
        return $xml;
    }
    public function getUniqId()
    {
        
    }

    public function parseResponse()
    {
        $this->parsedResponse = simplexml_load_string($this->response->getBody());
    }
    public function validate()
    {print_rre($this->parsedResponse);
        if($this->parsedResponse->Errors)
        {
            $message = '';
            $errors = explode(",", $this->xml->Errors);            
            foreach($errors as $error)
                $message .= $this->plugin->getErrorMessage($error).", ";
            $this->result->setFailed("Internal plugin's errors: ".substr($message, 0, -2));
            return;
        }
        $this->result->setSuccess($this);
        return true;
    }
}
class Am_Paysystem_Transaction_EwayRapid3 extends Am_Paysystem_Transaction_CreditCard
{   
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst=true, $accessCode)
    {
        parent::__construct($plugin, $invoice, $plugin->createHttpRequest(), $doFirst);
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><GetAccessCodeResult></GetAccessCodeResult>');
        $xml->addChild('AccessCode', $accessCode);
        $xml =  utf8_encode($xml->asXML());
        $url = $plugin->getConfig('testing') ? Am_Paysystem_EwayRapid3::GET_RESULT_SANDBOX_URL : Am_Paysystem_EwayRapid3::GET_RESULT_URL;
        $header[] = "Authorization: Basic ".base64_encode(
                $plugin->getConfig('apikey').':'
                .$plugin->getConfig('password'));
        $header[] = "Content-Type: text/xml";
        $this->request->setUrl($url);
        $this->request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->request->setHeader($header);
        $this->request->setBody($xml);
        return $request;
    }
    protected $responseCodesSuccess = array(
            'A2000' => 'Transaction Approved',
            'A2008' => 'Honour With Identification',
            'A2010' => 'Approved FOr Partial Amount',
            'A2011' => 'Approved, VIP',
            'A2016' => 'Approved, Update Track 3',
    );
    
    public function validate()
    {
        if(!array_key_exists((string)$this->parsedResponse->ResponseMessage, $this->responseCodesSuccess))
        {
            $errors = explode(",", (string)$this->parsedResponse->ResponseMessage);
            foreach($errors as $error)
            {
                if($this->getPlugin()->getErrorMessage($error))
                    $message .= $this->getPlugin()->getErrorMessage($error).", ";
                else
                    $message .= $error.", ";
            }

            $this->result->setFailed("Internal plugin's errors: ".substr($message, 0, -2));
            return;
        }
        else
        {
            $this->invoice->getUser()->data()->set(Am_Paysystem_EwayRapid3::TOKEN, (string)$this->parsedResponse->TokenCustomerID)->update();
            $this->result->setSuccess($this);
        }
    }

    public function parseResponse()
    {
        $this->parsedResponse = simplexml_load_string($this->response->getBody());
    }

    public function getUniqId()
    {
        return (string)$this->parsedResponse->TransactionID;
    }
}
class Am_Paysystem_Transaction_EwayRapid3_RequestFormActionUrl extends Am_Paysystem_Transaction_CreditCard
{
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true, CcRecord $cc)
    {
        parent::__construct($plugin, $invoice, $plugin->createHttpRequest(), $doFirst);
        $this->cc = $cc;
        $header[] = "Authorization: Basic ".base64_encode(
                $plugin->getConfig('apikey').':'
                .$plugin->getConfig('password'));
        $header[] = "Content-Type: text/xml";
        $this->request->setHeader($header);        
        $this->request->setBody($this->createXml($invoice, $doFirst));
        $this->request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->request->setUrl($this->plugin->getConfig('testing') ? 
            Am_Paysystem_EwayRapid3::CREATE_ACCESS_CODE_SANDBOX_URL : 
            Am_Paysystem_EwayRapid3::CREATE_ACCESS_CODE_URL);
    }
    public function getResponse()
    {
        return $this->xml;
    }

    public function getUniqId()
    {        
    }
    public function parseResponse()
    {
        $this->xml = new SimpleXMLElement($this->response->getBody());
    }
    protected function createXml(Invoice $invoice, $doFirst)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><CreateAccessCodeRequest></CreateAccessCodeRequest>');
        $xml->addChild('RedirectUrl', $this->plugin->getCancelUrl());
        $xml->addChild('Method', 'TokenPayment');        
        $xml->addChild('Customer');
        $xml->Customer->addChild('TokenCustomerID', $invoice->getUser()->data()->get(Am_Paysystem_EwayRapid3::TOKEN) ? $invoice->getUser()->data()->get(Am_Paysystem_EwayRapid3::TOKEN) : '');
        if($this->doFirst)
        {
            $xml->Customer->addChild('Title', 'Mr.');
            $xml->Customer->addChild('Reference', $invoice->getUserId());
            $xml->Customer->addChild('FirstName', $this->cc->cc_name_f);
            $xml->Customer->addChild('LastName', $this->cc->cc_name_l);
            $xml->Customer->addChild('Street1', $this->cc->cc_street);
            //$xml->Customer->addChild('Street2', $this->cc->cc_street2);
            $xml->Customer->addChild('City', $this->cc->cc_city);
            $xml->Customer->addChild('State', $this->cc->cc_state);
            $xml->Customer->addChild('PostalCode', $this->cc->cc_zip);
            $xml->Customer->addChild('Country', strtolower($this->cc->cc_country));
            $xml->Customer->addChild('Email', $invoice->getEmail());
            $xml->Customer->addChild('Phone', $invoice->getPhone());
        }
        $xml->addChild('Items');
        foreach($invoice->getItems() as $item)
        {
            $xml->Items->addChild('LineItem');
            $xml->Items->LineItem->addChild('Description', $item->item_title);
        }        
        $xml->addChild('Payment');
        $xml->Payment->addChild('TotalAmount', ($doFirst ? $invoice->first_total : $invoice->second_total) * 100);
        $xml->Payment->addChild('InvoiceNumber', $invoice->public_id."-".sprintf("%03d", $invoice->getPaymentsCount()));
        $xml->Payment->addChild('CurrencyCode', $invoice->currency);         
        return utf8_encode($xml->asXML());        
    }
    public function validate()
    {
        if($this->xml->Errors)
        {
            $message = '';
            $errors = explode(",", $this->xml->Errors);            
            foreach($errors as $error)
                $message .= $this->plugin->getErrorMessage($error).", ";
            $this->result->setFailed("Internal plugin's errors: ".substr($message, 0, -2));
            return;
        }
        $this->result->setSuccess($this);
        return true;
    }
    public function processValidated()
    {
    }
}
class Am_Paysystem_Transaction_EwayRapid3_RequestAccessCode extends Am_Paysystem_Transaction_CreditCard
{
    public function __construct(Am_Paysystem_Abstract $plugin, Invoice $invoice, $doFirst = true, CcRecord $cc, $response)
    {
        parent::__construct($plugin, $invoice, $plugin->createHttpRequest(), $doFirst);
        $post_params = new stdclass;
        $post_params->EWAY_ACCESSCODE = (string)$response->AccessCode;
        $post_params->EWAY_CARDNAME = $cc ? $cc->cc_name_f." ".$cc->cc_name_l : (string)$response->Customer->CardName;
        $post_params->EWAY_CARDNUMBER = $cc ? $cc->cc_number : (string)$response->Customer->CardNumber;
        $post_params->EWAY_CARDEXPIRYMONTH = $cc ? substr($cc->cc_expire, 0, 2) : (string)$response->Customer->CardExpiryMonth;
        $post_params->EWAY_CARDEXPIRYYEAR = $cc ? substr($cc->cc_expire, 2, 2) : (string)$response->Customer->CardExpiryYear;
        $post_params->EWAY_CARDCVN = $cc ? $cc->getCvv() : '';
        $this->request->addPostParameter((array)$post_params);
        $this->request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->request->setUrl((string)$response->FormActionURL);
    }
    public function getUniqId()
    {        
    }
    public function parseResponse()
    {        
    }
    public function getResponse()
    {
        return $this->response->getHeader();
    }
    public function validate()
    {
        $this->result->setSuccess();
        return true;
    }
    public function validateResponseStatus(Am_Paysystem_Result $result)
    {
        if ($this->response->getStatus() != 302)
        {
            $result->setErrorMessages(array("Received invalid response from payment server: " . $this->response->getStatus()));
            return false;
        }
        return true;
    }
    
    public function processValidated()
    {
    }
}
