<?php

class Am_Paysystem_Twocheckout extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    const URL = "https://www.2checkout.com/checkout/spurchase";
    const mURL = "https://www.2checkout.com/checkout/purchase";

    protected $defaultTitle = '2Checkout';
    protected $defaultDescription = 'purchase from 2Checkout';
    
    protected $_canResendPostback = true;

    public function _initSetupForm(Am_Form_Setup $form)
    {
        $form->addInteger('seller_id', array('size'=>20))
            ->setLabel('2CO Account#');
        $form->setDefault('secret', $this->getDi()->app->generateRandomString(10));
        $form->addText('secret', array('size'=>30))
            ->setLabel(array('2CO Secret Phrase', 'set it to the same value as configured in 2CO'));
        $form->addText('api_username')
            ->setLabel('2CO API Username');
        $form->addPassword('api_password')
            ->setLabel('2CO Password');

        $form->addSelect('lang', array(), array('options' =>
            array(
                'en' => 'English',
                'zh' => 'Chinese',
                'da' => 'Danish',
                'nl' => 'Dutch',
                'fr' => 'French',
                'gr' => 'German',
                'el' => 'Greek',
                'it' => 'Italian',
                'jp' => 'Japanese',
                'no' => 'Norwegian',
                'pt' => 'Portuguese',
                'sl' => 'Slovenian',
                'es_ib' => 'Spanish (es_ib)',
                'es_la' => 'Spanish (es_la)',
                'sv' => 'Swedish'
        )))->setLabel('2CO Interface language');
        $form->addAdvCheckbox('multi_page')
            ->setLabel('Use  Multi Page Checkout');
    }

    public function getSupportedCurrencies()
    {
        return array(
            'USD', 'GBP', 'ARS', 'AUD', 'BRL', 'CAD', 'DKK', 'EUR', 'HKD', 'INR', 
            'ILS', 'JPY', 'LTL', 'MYR', 'MXN', 'NZD', 'NOK', 'PHP', 'RON', 'RUB', 
            'SGD', 'ZAR', 'SEK', 'CHF', 'TRY', 'AED'
        );
    }
    
    
    public function init()
    {
        parent::init();
//        $this->getDi()->billingPlanTable->customFields()
//            ->add(new Am_CustomFieldText('twocheckout_id', "2Checkout Product#", 
//            "for any recurring products you have to create corresponding product in 2CO
//                admin panel and enter the id# here"));
    }
    
    public function isNotAcceptableForInvoice(Invoice $invoice)
    {
        if ($ret = parent::isNotAcceptableForInvoice($invoice))
            return $ret;
        foreach ($invoice->getItems() as $item)
        {
            if (!(float)$item->first_total && (float)$item->second_total)
                return array("2Checkout does not support products with free trial");
            if ($item->rebill_times && $item->second_period != $item->first_period)
                return array(___("2Checkout is unable to handle billing for product [{$item->item_title}] - second_period must be equal to first_period"));
        }
    }

    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $a = new Am_Paysystem_Action_Redirect($this->getConfig('multi_page') ? self::mURL  :  self::URL);
        $a->sid = $this->getConfig('seller_id');
        $a->mode = '2CO';
        $i = 0;
        foreach ($invoice->getItems() as $item)
        {
            $a->{"li_{$i}_type"} = 'product';
            $a->{"li_{$i}_name"} = $item->item_title;
            $a->{"li_{$i}_quantity"} = $item->qty;
            $a->{"li_{$i}_price"} = $item->rebill_times ? $item->second_total : $item->first_total;
            $a->{"li_{$i}_tangible"} = $item->is_tangible ? 'Y' : 'N';
            $a->{"li_{$i}_product_id"} = $item->item_id;
            if ($item->rebill_times)
            {
                $a->{"li_{$i}_recurrence"} = $this->period2Co($item->first_period);
                if ($item->rebill_times != IProduct::RECURRING_REBILLS)
                    $a->{"li_{$i}_duration"} = $this->period2Co($item->first_period, $item->rebill_times);
                else
                    $a->{"li_{$i}_duration"} = 'Forever';
                $a->{"li_{$i}_startup_fee"} = $item->first_total - $item->second_total;
            }
            $i++;
        }
        $a->skip_landing = 1;
        $a->x_Receipt_Link_URL = $this->getReturnUrl();
        $a->lang = $this->getConfig('lang', 'en');
        $a->merchant_order_id = $invoice->public_id;
        $a->first_name = $invoice->getFirstName();
        $a->last_name = $invoice->getLastName();
        $a->city = $invoice->getCity();
        $a->street_address = $invoice->getStreet();
        $a->state = $invoice->getState();
        $a->zip = $invoice->getZip();
        $a->country = $invoice->getCountry();
        $a->email = $invoice->getEmail();
        $a->phone = $invoice->getPhone();
        $result->setAction($a);
    }
    
    public function period2Co($period, $rebill_times = 1)
    {
        $p = new Am_Period($period);
        $c = $p->getCount() * $rebill_times;
        switch ($p->getUnit())
        {
            case Am_Period::DAY:
                if (!($c % 7))
                    return sprintf('%d Week', $c/7);
                else
                    throw new Am_Exception_Paysystem_NotConfigured("2Checkout does not supported per-day billing, period must be in weeks (=7 days), months, or years");
            case Am_Period::MONTH:
                return sprintf('%d Month', $c);
            case Am_Period::YEAR:
                return sprintf('%d Year', $c);
        }
        throw new Am_Exception_Paysystem_NotConfigured(
            "Unable to convert period [$period] to 2Checkout-compatible.".
            "Must be number of weeks, months or years");
    }

    public function getRecurringType()
    {
        return self::REPORTS_REBILL;
    }

    public function getReadme()
    {
return <<<CUT
            2Checkout payment plugin configuration
           -----------------------------------------

CONFIUGURATION OF ACCOUNT

1. Login into your 2Checkout account:
   https://www.2checkout.com/va/

2. Go to "Account->Site Management". Set:
   Direct Return:
     (*) Header Redirect (your URL)
   Secret Word:
     set to any value you like (aMember will offer you generated value, look at the form). 
     IMPORTANT! The same value must be entered to aMember 2Checkout plugin settings on this page
   Approved URL: 
     %root_url%/payment/twocheckout/thanks

3. Go to "Notifications->Settings", set INS URL:
     %root_url%/payment/twocheckout/ipn
   for all messages, click Save
   
4. Check your aMember product settings: for recurring products first period 
   must be equal to to second period, and period must be in weeks (specify days 
   multilplied to 7), months or years.

NOTE: <a target='_blank' href='http://www.2checkout.com/blog/2checkout-blog/standard-single-page-and-standard-multi-page/'>This article</a>  explains  difference between <b>Single Page Checkout</b> and <b>Multi Page Checkout</b>
CUT;
    }
    /** @return Am_Paysystem_Twocheckout_Api|null */
    public function getApi()
    {
        $user = $this->getConfig('api_username');
        $pass = $this->getConfig('api_password');
        if (empty($user) || empty($pass)) return null;
        return new Am_Paysystem_Twocheckout_Api($user, $pass);
    }
    
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return Am_Paysystem_Transaction_Twocheckout::create($this, $request, $response, $invokeArgs);
    }
    
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        if ($request->getActionName() == 'thanks')
            return $this->thanksAction($request, $response, $invokeArgs);
        elseif ($request->getActionName() == 'admin-cancel')
            return $this->adminCancelAction($request, $response, $invokeArgs);
        elseif ($request->getActionName() == 'cancel'){
            $invoice = $this->getDi()->invoiceTable->findBySecureId($request->getFiltered('id'), 'STOP'.$this->getId());
            if (!$invoice)                
                throw new Am_Exception_InputError("No invoice found [$id]");
            $result = new Am_Paysystem_Result;
            $payment = current($invoice->getPaymentRecords());
            $this->cancelInvoice($payment, $result);
            $invoice->setCancelled(true);
            Am_Controller::redirectLocation(REL_ROOT_URL . '/member/payment-history');
        }
        else
            return parent::directAction($request, $response, $invokeArgs);
    }
    public function thanksAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        $log = $this->logRequest($request);
        $transaction = new Am_Paysystem_Transaction_Twocheckout_Thanks($this, $request, $response, $invokeArgs);
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
    public function cancelAction(Invoice $invoice, $actionName, Am_Paysystem_Result $result)
    {
        $payment = current($invoice->getPaymentRecords());
        try {
            $this->cancelInvoice($payment, $result);
            $invoice->setCancelled(true);
        } catch (Exception $e) {
            $result->setFailed($e->getMessage());
        }
    }    
    
    public function cancelInvoice(InvoicePayment $payment, Am_Paysystem_Result $result)
    {
        try {
            $ret = $this->getApi()->detailSale($payment->receipt_id);
        }
        catch (Am_Exception_Paysystem $e)
        {
            //try to check if it is payment imported from v3
            $am3id = $this->getDi()->getDbService()->selectCell("SELECT value from ?_data 
                where `key`='am3:id' AND `table`='invoice_payment' and id=?",$payment->invoice_payment_id);
            if(!$am3id) throw $e;
            //try to load by invoice id to find sale id
            $ret = $this->getApi()->detailInvoice($payment->receipt_id);
            if(!$ret['sale']['sale_id']) throw $e;
            //now we have sale id
            $ret = $this->getApi()->detailSale($ret['sale']['sale_id']);
        }
        $lineitems = array();
        foreach ($ret['sale']['invoices'] as $inv)
            foreach ($inv['lineitems'] as $litem)
                $lineitems[] = $litem['lineitem_id'];
        $lineitems = array_unique($lineitems);
        if (!$lineitems)
        {
            $result->setFailed("Order not found, try to refund it manually");
            return;
        }
        
        $log = $this->getDi()->invoiceLogRecord;
        $log->setInvoice($payment->getInvoice());
//        foreach ($lineitems as $id)
//        {
            $id = max($lineitems);
            try {
                $return = $this->getApi()->stopLineitemRecurring($id);
            }
            catch (Am_Exception_Paysystem $e)
            {
                //if we get
                //NOTHING_TO_DO 
                //or
                //Lineitem is not scheduled to recur
                //mark invoice as cancelled
                if(preg_match('/NOTHING_TO_DO/', $e->getMessage()) && preg_match('/Lineitem is not scheduled to recur/', $e->getMessage()))
                    return;
                else
                    throw $e;
            }
            if ($return['response_code'] != 'OK')
            {
                $result->setFailed("Could not stop recurring for lieitem [$id]. Fix it manually in 2CO account");
                return;
            }
//        }
    }
    
    
    public function processRefund(InvoicePayment $payment, Am_Paysystem_Result $result, $amount)
    {
        if (!$this->getApi())
            throw new Am_Exception_Paysystem_NotConfigured("No 2Checkout API username/password configured - could not do refund");
        
        $log = $this->getDi()->invoiceLogRecord;
        $log->setInvoice($payment->getInvoice());
        $return = $this->getApi()->refundInvoice($payment->receipt_id, 5, "Customer Request");
        $log->add($return);
        if ($return['response_code'] == 'OK')
        {
            $trans = new Am_Paysystem_Transaction_Manual($this);
            $trans->setAmount($amount);
            $trans->setReceiptId($payment->receipt_id.'-2co-refund');
            $result->setSuccess($trans);
        } else {
            $result->setFailed($return['response_message']);
        }
    }
}

class Am_Paysystem_Transaction_Twocheckout extends Am_Paysystem_Transaction_Incoming
{
    // the following messages are sent once for each invoice
    const ORDER_CREATED = "ORDER_CREATED";
    const FRAUD_STATUS_CHANGED = "FRAUD_STATUS_CHANGED";
    const SHIP_STATUS_CHANGED = "SHIP_STATUS_CHANGED";
    const INVOICE_STATUS_CHANGED = "INVOICE_STATUS_CHANGED";

    // the following messages are sent for EACH item in the invoice
    const REFUND_ISSUED = "REFUND_ISSUED";
    const RECURRING_INSTALLMENT_SUCCESS = "RECURRING_INSTALLMENT_SUCCESS";
    const RECURRING_INSTALLMENT_FAILED = "RECURRING_INSTALLMENT_FAILED";
    const RECURRING_STOPPED = "RECURRING_STOPPED";
    const RECURRING_COMPLETE = "RECURRING_COMPLETE";
    const RECURRING_RESTARTED = "RECURRING_RESTARTED";

    public function findInvoiceId()
    {
        return $this->request->getFiltered('vendor_order_id');
    }
    public function getUniqId()
    {
        return $this->request->getFiltered('sale_id', $this->request->getFiltered('message_id'));
    }
    public function getReceiptId()
    {
        return $this->request->getFiltered('sale_id'); // @todo . add rebill date or message_id ?
    }
    public function validateSource()
    {
        $hash = $this->request->get('sale_id') .
                intval($this->plugin->getConfig('seller_id')) .
                $this->request->get('invoice_id') .
                $this->plugin->getConfig('secret');
        return strtoupper(md5($hash)) === $this->request->get('md5_hash');
    }
    public function validateStatus()
    {
        return true;
    }
    public function validateTerms()
    {
        return true;
    }
    
    static function create(Am_Paysystem_Abstract $plugin, Am_Request $request, Zend_Controller_Response_Http $response,
        array $invokeArgs)
    {
        $class = null;
        switch ($request->get('message_type'))
        {
            case Am_Paysystem_Transaction_Twocheckout::ORDER_CREATED:
                $class = 'Am_Paysystem_Transaction_Twocheckout_Order';
                break;
            case Am_Paysystem_Transaction_Twocheckout::RECURRING_INSTALLMENT_SUCCESS:
                $class = 'Am_Paysystem_Transaction_Twocheckout_RecurringOrder';
                break;
            case Am_Paysystem_Transaction_Twocheckout::FRAUD_STATUS_CHANGED:
                $class = 'Am_Paysystem_Transaction_Twocheckout_Fraud';
                break;
            case Am_Paysystem_Transaction_Twocheckout::REFUND_ISSUED:
                $class = 'Am_Paysystem_Transaction_Twocheckout_Refund';
                break;
        }
        if ($class)
            return new $class($plugin, $request, $response, $invokeArgs);
    }
}

class Am_Paysystem_Transaction_Twocheckout_Order extends Am_Paysystem_Transaction_Twocheckout
{
    public function processValidated()
    {
        if ($this->invoice->getPaymentsCount() == 1)
            foreach ($this->invoice->getPaymentRecords() as $p)
                if ($p->transaction_id == $this->getUniqId())
                    return; // already handled by thanksAction, skip silently
        $this->invoice->addPayment($this);
    }
    public function validateTerms()
    {
        // @todo for recurring
        return $this->request->get('invoice_list_amount') == $this->invoice->first_total;
    }
}
class Am_Paysystem_Transaction_Twocheckout_RecurringOrder extends Am_Paysystem_Transaction_Twocheckout
{
    public function getUniqId()
    {
        return $this->request->getFiltered('sale_id').'-'.$this->request->getFiltered('message_id');
    }
    
    public function processValidated()
    {
        $this->invoice->addPayment($this);
    }
    public function validateTerms()
    {
        $sum = 0;
        if($count = $this->request->get('item_count')){
            for($i = 1; $i<= $count; $i++){
                $sum += $this->request->get('item_list_amount_'.$i);
            }
        }
        return $sum  == $this->invoice->second_total;
    }
}

class Am_Paysystem_Transaction_Twocheckout_Fraud extends Am_Paysystem_Transaction_Twocheckout
{
    public function processValidated()
    {
        if ($this->request->get('fraud_status') != 'pass')
            $this->invoice->addVoid($this, 
                Am_Di::getInstance()->invoicePaymentTable->getLastReceiptId($this->invoice->pk()));
    }
}
class Am_Paysystem_Transaction_Twocheckout_Refund extends Am_Paysystem_Transaction_Twocheckout
{
    public function getAmount()
    {
        //https://www.2checkout.com/static/va/documentation/INS/message_refund_issued.html
        $amount = 0;
        foreach($this->request->getParams() as $k => $v)
            if(preg_match("/item_type_([0-9]+)/", $k, $m) && $v == 'refund')
                $amount+=$this->request->get ("item_list_amount_$m[1]", 0);
        return $amount;
    }

    public function processValidated()
    {
        $this->invoice->addRefund($this, 
            Am_Di::getInstance()->invoicePaymentTable->getLastReceiptId($this->invoice->pk()));
    }
}

class Am_Paysystem_Twocheckout_Api
{
    const URL = 'https://www.2checkout.com/api/';
    protected $req;
    public function __construct($user, $pass)
    {
        $this->req = new Am_HttpRequest();
        $this->req->setAuth($user, $pass);
        $this->req->setHeader('Accept', 'application/json');
    }
    protected function send()
    {
        $res = $this->req->send();
        if ($res->getStatus() != 200)
            throw new Am_Exception_Paysystem("Bad response from 2CO api: HTTP Status " . 
                $res->getStatus() . ', body: ' . $res->getBody());
        $ret = json_decode($res->getBody(), true);
        if ($ret['response_code'] != 'OK')
            throw new Am_Exception_Paysystem("Bad response from 2CO api: " . 
                $ret['response_code'] . '-' . $ret['response_message']);
        return $ret;
    }
    function detailSale($saleId)
    {
        $this->req->setUrl(self::URL . 'sales/detail_sale?sale_id='.$saleId);
        return $this->send();
    }
    function detailInvoice($invoiceId)
    {
        $this->req->setUrl(self::URL . 'sales/detail_sale?invoice_id='.$invoiceId);
        return $this->send();
    }
    function refundInvoice($saleId, $reasonCategory, $reasonComment)
    {
        $this->req->addPostParameter('sale_id', $saleId);
        $this->req->addPostParameter('category', $reasonCategory);
        $this->req->addPostParameter('comment', $reasonComment);
        $this->req->setMethod('POST');
        $this->req->setUrl(self::URL . 'sales/refund_invoice');
        return $this->send();
    }
    function stopLineItemRecurring($lineItemId)
    {
        $this->req->addPostParameter('lineitem_id', $lineItemId);
        $this->req->setMethod('POST');
        $this->req->setUrl(self::URL . 'sales/stop_lineitem_recurring');
        return $this->send();
    }
}

class Am_Paysystem_Transaction_Twocheckout_Thanks extends Am_Paysystem_Transaction_Incoming
{
    public function fetchUserInfo()
    {
        $email = $this->request->get('cemail');
        $email = preg_replace('/[^a-zA-Z0-9._+@-]/', '', $email);
        return array(
            'name_f' => $this->request->getFiltered('first_name'),
            'name_l' => $this->request->getFiltered('last_name'),
            'email'  => $email,
            'country' => $this->request->getFiltered('country'),
            'zip' => $this->request->getFiltered('zip'),
        );
    }
    public function generateInvoiceExternalId()
    {
        return $this->getUniqId();
    }
//    public function autoCreateGetProducts()
//    {
//        $cbId = $this->request->getFiltered('item');
//        if (empty($cbId)) return;
//        $pl = $this->getPlugin()->getDi()->billingPlanTable->findFirstByData('clickbank_product_id', $cbId);
//        if (!$pl) return;
//        $pr = $pl->getProduct();
//        if (!$pr) return;
//        return array($pr);
//    }
    public function getUniqId()
    {
        return $this->request->getFiltered('order_number');
    }
    public function findInvoiceId()
    {
        return $this->request->getFiltered('merchant_order_id');
    }
    public function getAmount()
    {
        return moneyRound($this->request->get('total'));
    }
    public function validateSource()
    {
        $vars = array(
            $this->getPlugin()->getConfig('secret'),
            $this->getPlugin()->getConfig('seller_id'),
            $this->request->get('order_number'),
            sprintf('%.2f', $this->request->get('total')),
        );
        $hash = strtoupper(md5(implode('', $vars)));
        if ($this->request->get('key') != $hash)
        {
            throw new Am_Exception_Paysystem_TransactionSource("2Checkout validation failed - most possible [secret] is configured incorrectly - mismatch between values in aMember and 2Checkout");
        }
        return true;
    }
    public function validateStatus()
    {
        return true;
    }
    public function validateTerms()
    {
        if ($this->invoice->status == Invoice::PENDING)
            $this->assertAmount($this->invoice->first_total, $this->getAmount(), 'First Total');
        else
            $this->assertAmount($this->invoice->second_total, $this->getAmount(), 'Second Total');
        return true;
    }
    public function getInvoice()
    {
        return $this->invoice;
    }
}
