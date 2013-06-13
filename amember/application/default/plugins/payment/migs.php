<?php
class Am_Paysystem_Migs extends Am_Paysystem_Abstract
{
    const PLUGIN_STATUS = self::STATUS_BETA;
    const PLUGIN_REVISION = '4.2.17';

    const URL = "https://migs.mastercard.com.au/vpcpay";

    protected $defaultTitle = 'MIGS';
    protected $defaultDescription = 'MasterCard Internet Gateway Service';

    
    public function _initSetupForm(Am_Form_Setup $form) 
    {
        $form->addText('merchant_id')->setLabel('Merchant ID');
        $form->addText('access_code')->setLabel(array('Access Code', 'Authenticates the merchant on the Payment Server'));
        $form->addText('secure_hash', array('size' =>60))->setLabel(array(
            'Secure Hash', 
            'A secure hash which allows the Virtual Payment Client to authenticate the merchant and check the integrity of the transaction Request'));
        
        
        
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result) {
        $a = new Am_Paysystem_Action_Redirect(self::URL);
        $vars = array(
            'vpc_Version' => '1',
            'vpc_Command' => 'pay',
            'vpc_MerchTxnRef' => $invoice->public_id,
            'vpc_AccessCode' => $this->getConfig('access_code'),
            'vpc_Merchant' => $this->getConfig('merchant_id'),
            'vpc_OrderInfo' => $invoice->public_id,
            'vpc_Amount' => intval($invoice->first_total * 100),
            'vpc_Locale' => 'en',
            'vpc_ReturnURL' => $this->getPluginUrl('thanks')
        ); 
        $vars = array_filter($vars);
        ksort($vars);
        $vars['vpc_SecureHash'] = strtoupper(md5($h = $this->getConfig('secure_hash').implode('', array_values($vars))));
        foreach($vars as $k=>$v){
            $a->__set($k, $v);
        }
        $result->setAction($a);
    }
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs) {
        
    }
    public function createThanksTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs) {
        return new Am_Paysystem_Transaction_Migs($this, $request, $response, $invokeArgs);
    }
    public function getRecurringType() {
        return self::REPORTS_NOT_RECURRING;
    }
}

class Am_Paysystem_Transaction_Migs extends Am_Paysystem_Transaction_Incoming_Thanks{
    
    function findInvoiceId() {
        return $this->request->getFiltered('vpc_OrderInfo');
    }
    public function getUniqId() {
        return $this->request->getFiltered('vpc_ReceiptNo');
    }
    public function validateSource() {
        if($this->getPlugin()->getConfig('merchant_id') != $this->request->get('vpc_Merchant')){
            throw new Am_Exception_Paysystem_TransactionSource('Incorrect merchant ID received!');
        }
        
        $vars = $this->request->toArray();
        $secureHash = $vars['vpc_SecureHash'];
        unset($vars['vpc_SecureHash']);
        ksort($vars);
        $hash = strtoupper(md5($h = $this->getPlugin()->getConfig('secure_hash').implode('', array_values($vars))));
        if($hash != $secureHash)
            throw new Am_Exception_Paysystem_TransactionInvalid('Unable to verity transaction. Calculated hash is not valid!');
        return true;
        
    }
    public function validateStatus() {
        if($this->request->get('vpc_TxnResponseCode') != '0'){
            throw new Am_Exception_Paysystem_TransactionInvalid('Transaction was not completed. Error code: '.$this->request->get('vpc_TxnResponseCode'));
        }
        return true;
    }
    public function validateTerms() {
        return ($this->invoice->first_total == ($this->request->get('vpc_Amount')/100));
    }
}
?>
