<?php
/**
 *  Comment for a good guy  who will decide to implement cancellations through API. 
 *  Pay attention to Content-Length header that is being sent by curl. 
 *  if there is no content-length header, curl send 
 *  Content-Length: -1
 *  Clickbank return 400 (bad request) in this situation. 
 *  Content-Length: 0  works as expected. 
 */
class Am_Paysystem_Clickbank extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    
    protected $_canResendPostback = true;
    
    protected $url = 'http://www.clickbank.net/sell.cgi';
    
    public function __construct(Am_Di $di, array $config)
    {
        $this->defaultTitle = ___("ClickBank");
        $this->defaultDescription = ___("pay using credit card or PayPal");
        parent::__construct($di, $config);
        $di->billingPlanTable->customFields()->add(
            new Am_CustomFieldText(
            'clickbank_product_id', 
            'ClickBank Product#', 
            'you have to create similar product in ClickBank and enter its number here'
            ,array(/*,'required'*/)
            )
            /*new Am_CustomFieldSelect(
            'clickbank_product_id', 
            'ClickBank Product#', 
            'you have to create similar product in ClickBank and enter its number here', 
            'required', array('options' => array('' => '-- Please select --', '11' => '#11', '22' => '#22')))*/
        );
    }
    public function isConfigured()
    {
        return strlen($this->getConfig('account'));
    }
    public function canAutoCreate()
    {
        return true;
    }
    public function isNotAcceptableForInvoice(Invoice $invoice)
    {
        foreach ($invoice->getItems() as $item)
        {
            /* @var $item InvoiceItem */
            if (!$item->getBillingPlanData('clickbank_product_id'))
                return "item [" . $item->item_title . "] has no related ClickBank product configured";
        }
    }
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('account', array('size' => 20, 'maxlength' => 16))
            ->setLabel("ClickBank account id\n".
                "your ClickBank username")
            ->addRule('required');
        $form->addText('secret', array('size' => 20, 'maxlength' => 16))
            ->setLabel("ClickBank secret phrase\n".
                "defined at clickbank.com -> login -> My Site -> Advanced Tools -> edit -> Secret Key")
            ->addRule('required');
        $form->addText('clerk_key', array('size' => 50))
            ->setLabel("ClickBank Clerk API Key\n".
                "defined at clickbank.com -> login -> My Account -> Clerk API Keys -> edit")
            ->addRule('required');
        $form->addText('dev_key', array('size' => 50))
            ->setLabel("Developer API Key\n".
                "defined at clickbank.com -> login -> My Account -> Developer API Keys -> edit")
            ->addRule('required')
            ->addRule('callback2', '-- wrong keys --', array($this, 'checkApiKeys'));
    }
    function checkApiKeys($vals)
    {
        $c = new Am_HttpRequest('https://sandbox.clickbank.com/rest/1.3/sandbox/product/list');
        $c->setHeader('Accept', 'application/xml')
          ->setHeader('Authorization', $vals['dev_key'] .':'. $vals['clerk_key']);
        $res = $c->send();
    }
    
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Redirect($this->url);
        $a->link = sprintf('%s/%s/%s', 
            $this->getConfig('account'),
            $this->invoice->getItem(0)->getBillingPlanData('clickbank_product_id'),
            $this->invoice->getLineDescription()
            );
        $a->seed = $invoice->public_id;
        
        $a->name = $invoice->getName();
        $a->email = $invoice->getEmail();
        $a->country = $invoice->getCountry();
        $a->zipcode = $invoice->getZip();
        $a->filterEmpty();
        $result->setAction($a);
    }
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        try {
            return parent::directAction($request, $response, $invokeArgs);
        } catch (Exception $e) {
            if ($request->getActionName() == 'ipn')
            {
                $response->setBody('ERROR')->setHttpResponseCode(200);
            } else {
                throw $e;
            }
        }
    }
    public function cancelAction(Invoice $invoice, $actionName, Am_Paysystem_Result $result)
    {
        $request = $this->createHttpRequest();
        $ps = new stdclass;
        $ps->type = 'cncl';
        $ps->reason = 'ticket.type.cancel.7';
        $ps->comment = 'cancellation request from aMember user ('.$invoice->getLogin().')';
        $get_params = http_build_query((array)$ps);
        $payment = current($invoice->getPaymentRecords());
        $request->setUrl($s='https://api.clickbank.com/rest/1.3/tickets/'.
            Am_Di::getInstance()->invoicePaymentTable->getLastReceiptId($invoice->pk())."?$get_params");
        $request->setHeader(array(
            'Content-Length' => '0',
            'Accept' => 'application/xml',
            'Authorization' => $this->getConfig('dev_key').':'.$this->getConfig('clerk_key')));
        $request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->logRequest($request);
        $request->setMethod('POST');
        $response = $request->send();
        $this->logResponse($response);
        if( $response->getStatus() != 200 && $response->getBody() != 'Subscription already canceled') 
            throw new Am_Exception_InputError("An error occured while cancellation request");
    }
    
    public function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount) {
        $request = $this->createHttpRequest();
        $ps = new stdclass;
        $ps->type = 'rfnd';
        $ps->reason = 'ticket.type.refund.8';
        $ps->comment = 'refund request for aMember user ('.$payment->getUser()->login.')';
        $get_params = http_build_query((array)$ps);
        $request->setUrl($s='https://api.clickbank.com/rest/1.3/tickets/'.
            $payment->receipt_id."?$get_params");
        $request->setHeader(array(
            'Content-Length' => '0',
            'Accept' => 'application/xml',
            'Authorization' => $this->getConfig('dev_key').':'.$this->getConfig('clerk_key')));
        $request->setMethod(Am_HttpRequest::METHOD_POST);
        $this->logRequest($request);
        $request->setMethod('POST');
        $response = $request->send();
        $this->logResponse($response);       
        if( $response->getStatus() != 200 && $response->getBody() != 'Refund ticket already open') 
            throw new Am_Exception_InputError("An error occured during refund request");
        $trans = new Am_Paysystem_Transaction_Manual($this);
        $trans->setAmount($amount);
        $trans->setReceiptId($payment->receipt_id.'-clickbank-refund');
        $result->setSuccess($trans);
    }

    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, 
        array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Clickbank($this, $request, $response, $invokeArgs);
    }
    public function createThanksTransaction(Am_Request $request, Zend_Controller_Response_Http $response, 
        array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Clickbank_Thanks($this, $request, $response, $invokeArgs);
    }
    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }
    public function getReadme()
    {
        return <<<CUT
                      ClickBank plugin installation

 1. Enable plugin: go to aMember CP -> Setup/Configuration -> Plugins and enable
	"ClickBank" payment plugin.
    
 2. Configure plugin: go to aMember CP -> Setup/Configuration -> ClickBank
	and configure it.
    
 3. For each your product and billing plan, configure ClickBank Product ID at 
        aMember CP -> Manage Products -> Edit
        
 4. Configure ThankYou Page URL in your ClickBank account (for each Product) to this URL:
    %root_url%/payment/clickbank/thanks
    
 5. Configure Instant Notification URL in your ClickBank account
    ( Account Settings -> My Site -> Advanced Tools -> Edit )
    to this URL: %root_url%/payment/clickbank/ipn
    Set version to 2.1
    
 6. Run a test transaction to ensure everything is working correctly.
 

------------------------------------------------------------------------------

CUT;
    }
}

class Am_Paysystem_Transaction_Clickbank extends Am_Paysystem_Transaction_Incoming
{
    // payment
    const SALE = "SALE";
    const TEST = "TEST";
    const TEST_SALE = "TEST_SALE";
    const BILL = "BILL";
    const TEST_BILL = "TEST_BILL";

    // refund
    const RFND = "RFND";
    const TEST_RFND = "TEST_RFND";
    const CGBK = "CGBK";
    const TEST_CGBK = "TEST_CGBK";
    const INSF = "INSF";
    const TEST_INSF = "TEST_INSF";
    
    // cancel
    const CANCEL_REBILL = "CANCEL-REBILL";
    const CANCEL_TEST_REBILL = "CANCEL-TEST-REBILL";

    // cancel
    const UNCANCEL_REBILL = "UNCANCEL-REBILL";
    const UNCANCEL_TEST_REBILL = "UNCANCEL-TEST-REBILL";
    
    protected $_autoCreateMap = array(
        'name' => 'ccustname',
        'country' => 'ccustcc',
        'state' => 'ccuststate',
        'email' => 'ccustemail',
        'user_external_id' => 'ccustemail',
        'invoice_external_id' => 'ccustemail',
    );
    public function findTime()
    {
        //clickbank timezone
        $dtc = new DateTime('now', new DateTimeZone('Canada/Central'));
        //local timezone
        $dtl = new DateTime('now', new DateTimeZone(date_default_timezone_get()));        
        $diff = $dtc->getOffset() - $dtl->getOffset();

        $dt = new DateTime('@' . ($this->request->getInt('ctranstime') - $diff));
        $dt->setTimezone(new DateTimeZone('Canada/Central'));
        return $dt;
    }
    
    /*
     * ccustname 	customer name 	1-510 Characters
ccuststate 	customer state 	0-2 Characters
ccustcc 	customer country code 	0-2 Characters
ccustemail 	customer email 	1-255 Characters
cproditem 	ClickBank product number 	1-5 Characters
cprodtitle 	title of product at time of purchase 	0-255 Characters
cprodtype 	type of product on transaction (STANDARD, and RECURRING) 	8-11 Characters
ctransaction * 	action taken 	4-15 Characters
ctransaffiliate 	affiliate on transaction 	0-10 Characters
ctransamount 	amount paid to party receiving notification (in pennies (1000 = $10.00)) 	3-10 Characters
ctranspaymentmethod 	method of payment by customer 	0-4 Characters
ctransvendor 	vendor on transaction 	5-10 Characters
ctransreceipt 	ClickBank receipt number 	8-13 Characters
cupsellreceipt ** § 	Parent receipt number for upsell transaction 	8-13 Characters
caffitid 	affiliate tracking id 	0 – 24 Characters
cvendthru 	extra information passed to order form with duplicated information removed 	0-1024 Characters
cverify ** 	the “cverify” parameter is used to verify the validity of the previous fields 	8 Characters
ctranstime ** 	the Epoch time the transaction occurred (not included in cverify)
     */
    public function getUniqId()
    {
         return $this->request->get('ctransreceipt');       
    }
    public function getReceiptId()
    {
        return $this->request->get('ctransreceipt');
    }
    public function getAmount()
    {
        return moneyRound($this->request->get('ctransamount'));
    }
    public function findInvoiceId()
    {
        $seed = $this->request->getFiltered('seed');
        if(!$seed && ($vars = $this->request->get('cvendthru'))){
            parse_str(html_entity_decode($vars), $ret);
            return $ret['seed'];
        }
        
    }
    public function validateSource()
    {
        $ipnFields = $this->request->getPost();
        unset($ipnFields['cverify']);
        ksort($ipnFields);
        $pop = implode('|', $ipnFields) . '|' . $this->getPlugin()->getConfig('secret');
        if (function_exists('mb_convert_encoding'))
            $pop = mb_convert_encoding($pop, "UTF-8");
        $calcedVerify = strtoupper(substr(sha1($pop),0,8));
        
        return ($this->request->get('cverify') == $calcedVerify) && ($this->request->getFiltered('ctransrole') == 'VENDOR');    
    
    }
    public function validateStatus()
    {
        return true;
    }
    public function validateTerms()
    {
        return true;
    }
    public function processValidated()
    {        
        switch ($this->request->get('ctransaction'))
        {
            //payment
            case Am_Paysystem_Transaction_Clickbank::SALE:
            case Am_Paysystem_Transaction_Clickbank::TEST:
            case Am_Paysystem_Transaction_Clickbank::TEST_SALE:
            case Am_Paysystem_Transaction_Clickbank::BILL:
            case Am_Paysystem_Transaction_Clickbank::TEST_BILL:
                $this->invoice->addPayment($this);
                break;
            //refund
            case Am_Paysystem_Transaction_Clickbank::RFND:
            case Am_Paysystem_Transaction_Clickbank::TEST_RFND:
            case Am_Paysystem_Transaction_Clickbank::CGBK:
            case Am_Paysystem_Transaction_Clickbank::TEST_CGBK:
            case Am_Paysystem_Transaction_Clickbank::INSF:
            case Am_Paysystem_Transaction_Clickbank::TEST_INSF:
                $this->invoice->addRefund($this, 
            Am_Di::getInstance()->invoicePaymentTable->getLastReceiptId($this->invoice->pk()));
                //$this->invoice->stopAccess($this);
                break;
            //cancel
            case Am_Paysystem_Transaction_Clickbank::CANCEL_REBILL:
            case Am_Paysystem_Transaction_Clickbank::CANCEL_TEST_REBILL:
                $this->invoice->setCancelled(true);
                break;
            //un cancel
            case Am_Paysystem_Transaction_Clickbank::UNCANCEL_REBILL:
            case Am_Paysystem_Transaction_Clickbank::UNCANCEL_TEST_REBILL:
                $this->invoice->setCancelled(false);
                break;
        }        
    }
    public function generateInvoiceExternalId()
    {
        list($l,) = explode('-',$this->getUniqId());
        return $l;
    }
    public function autoCreateGetProducts()
    {
        $cbId = $this->request->getFiltered('cproditem');
        if (empty($cbId)) return;
        $pl = $this->getPlugin()->getDi()->billingPlanTable->findFirstByData('clickbank_product_id', $cbId);
        if (!$pl) return;
        $pr = $pl->getProduct();
        if (!$pr) return;
        return array($pr);
    }
    public function fetchUserInfo()
    {
        $email = $this->request->get('ccustemail');
        $email = preg_replace('/[^a-zA-Z0-9._+@-]/', '', $email);
        return array(
            'name_f' => ucfirst(strtolower($this->request->getFiltered('ccustfirstname'))),
            'name_l' => ucfirst(strtolower($this->request->getFiltered('ccustlastname'))),
            'email'  => $email,
            'country' => $this->request->getFiltered('ccustcounty'),
            'zip' => $this->request->getFiltered('ccustzip'),
        );
    }
}


class Am_Paysystem_Transaction_Clickbank_Thanks extends Am_Paysystem_Transaction_Incoming_Thanks
{
    public function autoCreateGetProducts()
    {
        $cbId = $this->request->getFiltered('item');
        if (empty($cbId)) return;
        $pl = $this->getPlugin()->getDi()->billingPlanTable->findFirstByData('clickbank_product_id', $cbId);
        if (!$pl) return;
        $pr = $pl->getProduct();
        if (!$pr) return;
        return array($pr);
    }
    public function findTime()
    {
        //clickbank timezone
    	$dtc = new DateTime('now', new DateTimeZone('Canada/Central'));
        //local timezone
        $dtl = new DateTime('now', new DateTimeZone(date_default_timezone_get()));        
        $diff = $dtc->getOffset() - $dtl->getOffset();

        $dt = new DateTime('@' . ($this->request->getInt('time') - $diff));
    	$dt->setTimezone(new DateTimeZone('Canada/Central'));
        return $dt;
    }
    public function generateInvoiceExternalId()
    {
        return $this->getUniqId();
    }
    public function fetchUserInfo()
    {
        $names = preg_split('/\s+/', $this->request->get('cname'), 2);
        $names[0] = preg_replace('/[^a-zA-Z0-9._+-]/', '', $names[0]);
        $names[1] = preg_replace('/[^a-zA-Z0-9._+-]/', '', $names[1]);
        $email = $this->request->get('cemail');
        $email = preg_replace('/[^a-zA-Z0-9._+@-]/', '', $email);
        return array(
            'name_f' => $names[0],
            'name_l' => $names[1],
            'email'  => $email,
            'country' => $this->request->getFiltered('ccountry'),
            'zip' => $this->request->getFiltered('czip'),
        );
    }
    public function findInvoiceId()
    {
        $invoice = $this->getPlugin()->getDi()->invoiceTable->findByReceiptIdAndPlugin($this->request->getEscaped('cbreceipt'), $this->plugin->getId());        
        if ($invoice) 
            return $invoice->public_id;
        else
            return $this->request->getFiltered('seed');
    }
    public function getUniqId()
    {
         return $this->request->get('cbreceipt');       
    }
    public function validateStatus()
    {
        return true;
    }
    public function validateTerms()
    {
        return true;
    }
    public function validateSource()
    {
        $vars = array(
            $this->getPlugin()->getConfig('secret'),
            $this->request->get('cbreceipt'),
            $this->request->get('time'),
            $this->request->get('item'),
        );
        $hash = sha1(implode('|', $vars));
        return strtolower($this->request->get('cbpop')) == substr($hash, 0, 8);
    }
    public function getInvoice()
    {
        return $this->invoice;
    }
}
