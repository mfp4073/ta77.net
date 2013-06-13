<?php
/**
 * @todo Add cancellations support. 
 * 
 */
class Am_Paysystem_Fastspring extends Am_Paysystem_Abstract{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    protected $defaultTitle = 'FastSpring';
    protected $defaultDescription = 'Pay by credit card';
    
    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addText('company')->setLabel(array('Company Name', 'your Company name as registered at FastSpring'));
        $form->addText('key')->setLabel(array('Private Security Key', 'FastSpring -> Account -> Notification Configuration -> Order Notification -> Security'));
        $form->addText('api_user')->setLabel(array('API Username', 'used to handle cancellations'));
        $form->addText('api_pass')->setLabel(array('API Password', 'used to handle cancellations'));
        $form->addSelect("testing", array(), array('options' => array(
                ''=>'No',
                '1'=>'Yes' 
            )))->setLabel('Test Mode');
        
    }

    public function getSupportedCurrencies()
    {
        return array('AUD', 'CAD', 'CHF', 'DKK',
            'EUR', 'GBP', 'HKD', 'JPY', 'NZD', 'SGD', 'USD');
    }

    public function init()
    {
        parent::init();
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('fastspring_product_id', "FastSpring Product ID",
                    "You can get an ID from your FastSpring account -> Products and Settings -> Product Pages -> Option 1: View Product Detail Page 
                    <br />For example ID is 'testmembership' for an URL http://sites.fastspring.com/your_company/product/testmembership"));
        $this->getDi()->billingPlanTable->customFields()
            ->add(new Am_CustomFieldText('fastspring_product_name', "FastSpring Product Name",
                    "You can get Name from your FastSpring account -> Products and Settings -> Product Catalog"));
        
    }
    
    function getActionUrl(Invoice $invoice){
        return sprintf("http://sites.fastspring.com/%s/product/%s", 
            $this->getConfig('company'), 
            $invoice->getItem(0)->getBillingPlanData('fastspring_product_id'));
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Redirect($this->getActionUrl($invoice));
        $a->referrer = $invoice->public_id;
        if($this->getConfig('testing')){
            $a->mode = 'test';
            $a->member = 'new';
            $a->sessionOption = 'new';
        }
        $coupon = $invoice->getCoupon()->code;
        if ($coupon)
            $a->coupon = $coupon;
        $result->setAction($a);
    }
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Paysystem_Transaction_Fastspring($this, $request, $response, $invokeArgs);
    }

    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }
    
    public function getReadme(){
        return <<<CUT
<b>FastSpring plugin installation</b>

 1. Configure plugin at aMember CP -> Setup/Configuration -> FastSpring

 2. Configure FastSpring Product ID/Name at aMember CP -> Manage Products -> ->Billing Plans
 
 4. Configure Remote Server URL in your FastSpring account (NOTIFY -> Add Notification Rule)

    Format: HTTP Remote Server Call
    Type:  Order Notification
    Remote Server URL: %root_surl%/payment/fastspring/ipn

    Optionally, after clicking 'Next' button you can add following 'HTTP Parameters'.
    
    Name: SubscriptionURL
    Value: #{order.allItems[0].subscription.url.detail}

    Name:  OrderReference2
    Value: #{order.allItems[0].subscription.reference}
    
    Name:  OrderReferrer2
    Value: #{order.allItems[0].subscription.referrer}

 5. Run a test transaction to ensure everything is working correctly.

 Note: You can setup in FastSpring the same coupon code as in aMember.
        
CUT;
    }
    function canAutoCreate()
    {
        return true;
    }

    function getUserCancelUrl(Invoice $invoice)
    {
        $customerUrl = '';
        $SubscriptionURL = $invoice->data()->get('SubscriptionURL');
        $OrderReference = $invoice->data()->get('OrderReference2');
        if ($SubscriptionURL != ''){
            $customerUrl = $SubscriptionURL;
        } elseif ($OrderReference != ''){
            $url = sprintf("https://api.fastspring.com/company/%s/subscription/%s?user=%s&pass=%s",
                    $this->getConfig('company'),
                    $OrderReference,
                    $this->getConfig('api_user'),
                    $this->getConfig('api_pass'));
            $request = new Am_HttpRequest($url);
            $response = $request->send();
            $body = $response->getBody();
            if (strpos($body, "<?xml") !== false){
                $xml = simplexml_load_string();
                $customerUrl = $xml->customerUrl;
            } else {
                $this->logResponse($body);
            }
        }
        
        return $customerUrl;
    }    
    
}

class Am_Paysystem_Transaction_Fastspring extends Am_Paysystem_Transaction_Incoming{
    
    protected $_autoCreateMap = array(
        'name_f'    =>  'CustomerFirstName',
        'name_l'    =>  'CustomerLastName',
        'phone'     =>  'phone',
        'country'   =>  'AddressCountry',
        'street'    =>  'AddressStreet1',
        'state'     =>  'AddressRegion',
        'email'     =>  'CustomerEmail',
        'zip'       =>  'AddressPostalCode',
        'user_external_id' => 'CustomerEmail',
        'invoice_external_id' => 'OrderReference',
    );

    public function getUniqId()
    {
        return $this->request->get("OrderReference") ? $this->request->get("OrderReference") : $this->request->get("OrderID");
    }
    
    public function findInvoiceId(){
        return $this->request->get("OrderReferrer");
    }

    public function validateSource()
    {
        if(md5($this->request->get('security_data').$this->getPlugin()->getConfig('key')) != $this->request->get('security_hash'))
            throw new Am_Exception_Paysystem_TransactionSource('Received security hash is not correct');
            
        return true;
    }
    
    public function validateStatus()
    {
        if($this->request->get('OrderIsTest') == 'true' && !$this->getPlugin()->getConfig('testing')){
            throw new Am_Exception_Paysystem_TransactionInvalid('Test IPN received but test mode is not enabled');
        }
        return true;
        
    }
    public function validateTerms()
    {
        /**
         * @todo Add real validation here; Need to check variables that will be sent from fastspring. 
         */
        return true;
    }
    public function processValidated()
    {
        $SubscriptionURL = $this->request->get('SubscriptionURL');
        $OrderReference2 = $this->request->get('OrderReference2');
        if ($SubscriptionURL != '')
            $this->invoice->data()->set('SubscriptionURL', $SubscriptionURL)->update();
        if ($OrderReference2 != '')
            $this->invoice->data()->set('OrderReference2', $OrderReference2)->update();
        
        $this->invoice->addPayment($this);
    }
    public function autoCreateGetProducts()
    {
        $prId = $this->request->get('OrderProductNames');
        if (empty($prId)) return;
        $pl = $this->getPlugin()->getDi()->billingPlanTable->findFirstByData('fastspring_product_name', $prId);
        if (!$pl) return;
        $pr = $pl->getProduct();
        if (!$pr) return;
        return array($pr);
    }

}


?>
