<?php

abstract class Am_Paysystem_CreditCard extends Am_Paysystem_Abstract
{
    const ACTION_CC = 'cc';
    const ACTION_UPDATE = 'update';
    const ACTION_CANCEL = 'cancel';
    
    // form fields contants @see getFormOptions
    const CC_COMPANY                = 'cc_company';
    const CC_TYPE_OPTIONS           = 'cc_type_options';
    const CC_CODE                   = 'cc_code';
    const CC_MAESTRO_SOLO_SWITCH    = 'cc_maestro_solo_switch';
    const CC_INPUT_BIN              = 'cc_input_bin';
    const CC_HOUSENUMBER            = 'cc_housenumber';
    const CC_PROVINCE_OUTSIDE_OF_US = 'cc_province_outside_of_us';
    const CC_PHONE                  = 'cc_phone';
    const CC_STREET2                = 'cc_street2';
    const CC_STREET                 = 'cc_street';
    const CC_CITY                   = 'cc_city';
    const CC_ADDRESS                = 'cc_address';
    const CC_COUNTRY                = 'cc_country';
    const CC_STATE                  = 'cc_state';
    const CC_ZIP                    = 'cc_zip';
    
    /** invoice data field name */
    const FIRST_REBILL_FAILURE = 'first_rebill_failure';

    /** @var CcRecord set during bill processing */
    protected $cc;
    
    /** @var bool do not display warning about PCI DSS compliance */
    protected $_pciDssNotRequired = false;

    const FORM_TYPE_CC = 'Am_Form_CreditCard';
    
    /** @return Am_Form */
    public function createForm($actionName)
    {
        return new Am_Form_CreditCard($this);
    }
    
    public function getRecurringType()
    {
        return self::REPORTS_CRONREBILL;
    }
    /** @return bool if plugin needs to store CC info */
    public function storesCcInfo()
    {
        return true;
    }
    public function _process(Invoice $invoice, Am_Request $request, Am_Paysystem_Result $result)
    {
        $action = new Am_Paysystem_Action_Redirect( $this->getPluginUrl(self::ACTION_CC) );
        $action->id = $invoice->getSecureId($this->getId());
        $result->setAction($action);
    }
    public function createTransaction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs){}
    
    public function directAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        switch ($request->getActionName())
        {
            case self::ACTION_IPN:
                return parent::directAction($request, $response, $invokeArgs);
            case self::ACTION_UPDATE:
                return $this->updateAction($request, $response, $invokeArgs);
            case self::ACTION_CANCEL:
                return $this->doCancelAction($request, $response, $invokeArgs);
            case self::ACTION_CC:
            default:
                return $this->ccAction($request, $response, $invokeArgs);
        }
    }
    
    protected function ccActionValidateSetInvoice(Am_Request $request, array $invokeArgs)
    {
        $invoiceId = $request->getFiltered('id');
        if (!$invoiceId)
            throw new Am_Exception_InputError("invoice_id is empty - seems you have followed wrong url, please return back to continue");
        
        $invoice = $this->getDi()->invoiceTable->findBySecureId($invoiceId, $this->getId());
        if (!$invoice)
            throw new Am_Exception_InputError('You have used wrong link for payment page, please return back and try again');

        if ($invoice->isCompleted())
            throw new Am_Exception_InputError(sprintf(___('Payment is already processed, please go to %sMembership page%s'), "<a href='".htmlentities($this->getDi()->config->get('root_url'))."/member'>","</a>"));

        if ($invoice->paysys_id != $this->getId())
            throw new Am_Exception_InputError("You have used wrong link for payment page, please return back and try again");

        if ($invoice->tm_added < sqlTime('-1 days'))
            throw new Am_Exception_InputError("Invoice expired - you cannot open invoice after 24 hours elapsed");
        
        $this->invoice = $invoice; // set for reference 
    }
    
    /**
     * Show credit card info input page, validate it if submitted
     */
    public function ccAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        $this->ccActionValidateSetInvoice($request, $invokeArgs);
        $p = $this->createController($request, $response, $invokeArgs);
        $p->setPlugin($this);
        $p->setInvoice($this->invoice);
        $p->run();
    }
    
    /**
     * Process credit card update request
     * @param Am_Request $request
     * @param Zend_Controller_Response_Http $response
     * @param array $invokeArgs
     */
    public function updateAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        if (!$this->getDi()->auth->getUser())
            throw new Am_Exception_Security("Authentication required to access this page");
        $p = $this->createController($request, $response, $invokeArgs);
        $p->setPlugin($this);
        $p->run();
    }
    
    /**
     * Process "cancel recurring" request
     * @param Am_Request $request
     * @param Zend_Controller_Response_Http $response
     * @param array $invokeArgs
     */
    public function doCancelAction(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        $id = $request->getFiltered('id');
        $invoice = $this->getDi()->invoiceTable->findBySecureId($id, 'STOP'.$this->getId());
        if (!$invoice) 
            throw new Am_Exception_InputError("No invoice found [$id]");
        if ($invoice->user_id != $this->getDi()->auth->getUserId())
            throw new Am_Exception_InternalError("User tried to access foreign invoice: [$id]");
        if (method_exists($this, 'cancelInvoice'))
                $this->cancelInvoice($invoice);
        $invoice->setCancelled();
        $response->setRedirect(ROOT_SURL . '/member/payment-history');
    }
    
    /**
     * To be overriden in children classes
     * @param Am_Request $request
     * @param Zend_Controller_Response_Http $response
     * @param array $invokeArgs
     * @return \Am_Controller_CreditCard
     */
    protected function createController(Am_Request $request, Zend_Controller_Response_Http $response, array $invokeArgs)
    {
        return new Am_Controller_CreditCard($request, $response, $invokeArgs);
    }
    /**
     * Method must return array of self::CC_xxx constants to control which
     * additional fields will be displayed in the form
     * @return array
     */
    public function getFormOptions(){
        $ret = array(self::CC_CODE, self::CC_ADDRESS, self::CC_COUNTRY, self::CC_STATE, self::CC_CITY, self::CC_STREET, self::CC_ZIP);
        if ($this->getCreditCardTypeOptions()) $ret[] = self::CC_TYPE_OPTIONS;
        return $ret;
    }
    /**
     * 
     */
    public function getCreditCardTypeOptions(){
        return array();
    }


    /**
     * You can do form customization necessary for the plugin
     * here
     */
    public function onFormInit(Am_Form_CreditCard $form)
    {
    }

    /**
     * You can do custom form validation here. If errors found,
     * call $form->getElementById('xx-0')->setError('xxx') and
     * return false
     * @return bool
     */
    public function onFormValidate(Am_Form_CreditCard $form)
    {
        return true;
    }
    
    /**
     * Filter and validate cc#
     * @return null|string null if ok, error message if error
     */
    public function validateCreditCardNumber($cc){
        require_once 'ccvs.php';
        $validator = new CreditCardValidationSolution;
        if (!$validator->validateCreditCard($cc)) 
            return $validator->CCVSError;
        /** @todo translate error messages from ccvs.php */
        return null;
    }

    public function doBill(Invoice $invoice, $doFirst, CcRecord $cc)
    {
        $this->invoice = $invoice;
        $this->cc = $cc;
        $result = new Am_Paysystem_Result();
        $this->_doBill($invoice, $doFirst, $cc, $result);
        return $result;
    }
    abstract public function _doBill(Invoice $invoice, $doFirst, CcRecord $cc, Am_Paysystem_Result $result);

    /**
     * Function can be overrided to change behaviour
     */
    public function storeCreditCard(CcRecord $cc, Am_Paysystem_Result $result)
    {
        if ($this->storesCcInfo())
        {
            $cc->replace();
            $result->setSuccess();
        }
        return $this;
    }


    /**
     * Method defined for overriding in child classes where CC info is not stored locally
     * @return CcRecord
     * @param Invoice $invoice
     * @throws Am_Exception
     */
    public function loadCreditCard(Invoice $invoice)
    {
        if ($this->storesCcInfo())
            return $this->getDi()->ccRecordTable->findFirstByUserId($invoice->user_id);
    }

    public function prorateInvoice(Invoice $invoice, CcRecord $cc, Am_Paysystem_Result $result, $date)
    {
        /** @todo use "reattempt" config **/
        $reattempt = array_filter($this->getConfig('reattempt', array()));
        sort($reattempt);
        if (!$reattempt) return;

        $first_failure = $invoice->data()->get(self::FIRST_REBILL_FAILURE);
        if (!$first_failure)
        {
            $invoice->data()->set(self::FIRST_REBILL_FAILURE, $date)->update();
            $first_failure = $date;
        }
        $days_diff = (strtotime($date) - strtotime($first_failure)) / (24*3600);
        foreach ($reattempt as $day)
             if ($day > $days_diff) break; // we have found next rebill date to jump
        if ($day <= $days_diff){
            // Several rebilling attempts failed already. 
            // change status to RECURRING_FAILED;
            $invoice->updateQuick('status', Invoice::RECURRING_FAILED);
            $invoice->updateQuick('rebill_date', null);
            return;
        }
        
        $invoice->updateQuick('rebill_date', date('Y-m-d', strtotime($first_failure) + $day * 24*3600));

        $tr = new Am_Paysystem_Transaction_Manual($this);
        if ($invoice->getAccessExpire() < $invoice->rebill_date)
            $invoice->extendAccessPeriod($invoice->rebill_date);
    }
    
    public function onRebillFailure(Invoice $invoice, CcRecord $cc, Am_Paysystem_Result $result, $date)
    {
        $this->prorateInvoice($invoice, $cc, $result, $date);
        
        if($this->getDi()->config->get('cc.rebill_failed'))
            $this->sendRebillFailedToUser($invoice, $result->getLastError(), $invoice->rebill_date);
        
        
    }
    
    function sendRebillFailedToUser(Invoice $invoice, $failedReason, $nextRebill)
    {
        try
        {
            if($et = Am_Mail_Template::load('cc.rebill_failed'))
            {
                $et->setError($failedReason);
                $et->setUser($invoice->getUser());
                $et->setInvoice($invoice);
                
                $et->setProrate(
                    ($nextRebill > $this->getDi()->sqlDate) ?  
                        sprintf(___('Our system will try to charge your card again on %s'), amDate($nextRebill)) : ""
                    );
                
                $et->setMailPeriodic(Am_Mail::USER_REQUESTED);
                $et->send($invoice->getUser());
            }
        }catch(Exception $e)
        {
            // No mail exceptions when  rebilling;
            $this->getDi()->errorLogTable->logException($e);
        }
    }

    function sendRebillSuccessToUser(Invoice $invoice)
    {
        try
        {
            if($et = Am_Mail_Template::load('cc.rebill_success'))
            {
                $et->setUser($invoice->getUser());
                $et->setInvoice($invoice);
                
                $et->setAmount($invoice->second_total);
                $et->setRebill_date($invoice->rebill_date ? amDate($invoice->rebill_date) : ___('NEVER'));
                $et->setMailPeriodic(Am_Mail::USER_REQUESTED);
                $et->send($invoice->getUser());
            }
        }
        catch(Exception $e)
        {
            // No mail exceptions when  rebilling;
            $this->getDi()->errorLogTable->logException($e);
        }
    }
    
    public function onRebillSuccess(Invoice $invoice, CcRecord $cc, Am_Paysystem_Result $result, $date)
    {
        if ($invoice->data()->get(self::FIRST_REBILL_FAILURE))
        {
            $invoice->addToRebillDate(false, $invoice->data()->get(self::FIRST_REBILL_FAILURE));
            $invoice->data()->set(self::FIRST_REBILL_FAILURE, null)->update();
        }
    
        if($this->getDi()->config->get('cc.rebill_success'))
            $this->sendRebillSuccessToUser($invoice);
        
    }

    public function ccRebill($date = null)
    {
        /**
         *  If plugin can't  rebill payments itself, leave it alone.  
         */
        if($this->getRecurringType() != self::REPORTS_CRONREBILL) return;
        
        $rebillTable = $this->getDi()->ccRebillTable;
        $processedCount = 0;
        foreach ($this->getDi()->invoiceTable->findForRebill($date, $this->getId()) as $invoice)
        {
            
            // Invoice must have status RECURRING_ACTIVE in order to be rebilled; 
            if($invoice->status != Invoice::RECURRING_ACTIVE) 
                continue;

            // If we already have all payments for this invoice unset rebill_date and update invoice status;
            if($invoice->getPaymentsCount() >= $invoice->getExpectedPaymentsCount())
            {
                $invoice->recalculateRebillDate();
                $invoice->updateStatus();
                continue;
            }
            
            /* @var $invoice Invoice */
            try {
                $rebill = $rebillTable->createRecord(array(
                    'paysys_id'     => $this->getId(),
                    'invoice_id'    => $invoice->invoice_id,
                    'rebill_date'   => $date,
                    'status'        => CcRebill::STARTED,
                    'status_msg'    => "Not Processed",
                ));
                
                //only one attempt to rebill per day
                try {
                    $rebill->insert();
                } catch (Am_Exception_Db_NotUnique $e) {
                    continue;
                }
                
                $cc = null;
                if ($this->storesCcInfo())
                {
                    $cc = $this->loadCreditCard($invoice);
                    if (!$cc)
                    {
                        $rebill->setStatus(CcRebill::NO_CC, "No credit card saved, cannot rebill");
                        continue;
                    }
                }
                $result = $this->doBill($invoice, false, $cc);
                if (!$result->isSuccess())
                    $this->onRebillFailure($invoice, $cc, $result, $date);
                else
                    $this->onRebillSuccess($invoice, $cc, $result, $date);
                $rebill->setStatus($result->isSuccess() ? CcRebill::SUCCESS : CcRebill::ERROR, 
                    current($result->getErrorMessages()));
                $processedCount++;
            } catch (Exception $e) {
                if (stripos(get_class($e), 'PHPUnit_')===0) throw $e;
                $rebill->setStatus(CcRebill::EXCEPTION, 
                    "Exception " . get_class($e) . " : " . $e->getMessage());
                // if there was an exception in billing (say internal error),
                // we set rebill_date to tomorrow
                $invoice->updateQuick('rebill_date', date('Y-m-d', strtotime($invoice->rebill_date . ' +1 day')));
                $this->getDi()->errorLogTable->logException($e);
                
                $this->logError("Exception on rebilling", $e, $invoice);
                
                unset($this->invoice);
            }
        }
        
        // Send message only if rebill executed by cron;
        
        if($this->getDi()->config->get('cc.admin_rebill_stats') 
            && (is_null($date) || $date == $this->getDi()->sqlDate) 
            && $processedCount) 
            $this->sendStatisticsEmail();
    }
    
    protected function getStatisticsRow(Array $r)
    {
        if($r['status']!= CcRebill::SUCCESS)
        {
            $failed = "Reason: ".$r['status_msg'];
            if($r['rebill_date']>$this->getDi()->sqlDate)
                $failed .= "\t Next Rebill Date: ".$r['rebill_date'];
        }else 
            $failed = '';
        
        $row = sprintf("%s, %s, %s %s, \nInvoice: %s\tAmount: %s\t%s\n%s\n\n", 
            $r['email'], $r['login'], $r['name_f'], $r['name_l'], 
            $r['public_id'], $r['second_total'], $failed, 
            ROOT_SURL . sprintf('/admin-user-payments/index/user_id/%s#invoice-%s', $r['user_id'], $r['invoice_id'])
            );
        return $row;
    }
    
    
    protected function sendStatisticsEmail()
    {
        $date = $this->getDi()->sqlDate;
        $success = $failed = "";
        $success_count = $failed_count = $success_amount = $failed_amount = 0;
        
        if ($et = Am_Mail_Template::load('cc.admin_rebill_stats'))
        {
            foreach($this->getDi()->db->selectPage($total, "
                SELECT r.*, i.second_total, i.user_id, i.invoice_id, i.public_id, i.rebill_date, u.name_f, u.name_l, u.email, u.login
                FROM ?_cc_rebill r LEFT JOIN ?_invoice i USING(invoice_id) LEFT JOIN ?_user u ON(i.user_id = u.user_id)
                WHERE status_tm>? and status_tm<=now() and r.paysys_id=?
                ", $date, $this->getId()) as $r)
            {
                if($r['status'] == CcRebill::SUCCESS)
                {
                    $success_count++; $success_amount+=$r['second_total'];
                    $success .= $this->getStatisticsRow($r);
                }else{
                    $failed_count++; $failed_amount += $r['second_total'];
                    $failed .= $this->getStatisticsRow($r);;
                }
            }
            if($success || $failed)
            {
                $currency = $this->getDi()->config->get('currency');
                
                $et->setShort_stats(sprintf(___('Success: %d (%0.2f %s) Failed: %d (%0.2f %s)'), 
                    $success_count, $success_amount, $currency, $failed_count, $failed_amount, $currency));
                
                $et->setRebills_success(!empty($success) ? $success : ___('No items in this section'));
                $et->setRebills_failed(!empty($failed) ? $failed : ___('No items in this section'));
                $et->setPlugin($this->getId());
                
                $et->setMailPeriodic(Am_Mail::ADMIN_REQUESTED);
                
                $et->sendAdmin();
            }
            
        }
        

    }
    
    protected function _afterInitSetupForm(Am_Form_Setup $form)
    {
        // insert title, description fields
        $form->setTitle(ucfirst(toCamelCase($this->getId())));
        $el = $form->addMagicSelect('reattempt', array('multiple'=>'multiple'));
        $options = array();
        for ($i=1;$i<60;$i++) $options[$i] = ___("on %d-th day", $i);
        $el->loadOptions($options);
        $el->setLabel(___("Retry On Failure\n".
                 "if the recurring billing has failed,\n".
                 "aMember can repeat it after several days,\n".
                 "and extend customer subscription for that period\n".
                 "enter number of days to repeat billing attempt"));
        
        if($this->storesCcInfo() && !$this->_pciDssNotRequired)
        {
            $text = "<p><font color='red'>WARNING!</font> Every application processing credit card information, must be certified\n" .
                    "as PA-DSS compliant, and every website processing credit cards must\n" .
                    "be certified as PCI-DSS compliant.</p>";
            $text.= "<p>aMember Pro is not yet certified as PA-DSS compliant. We will start certification process\n".
                    "once we get 4.2.0 branch released and stable. This plugins is provided solely for TESTING purproses\n".
                    "Use it for anything else but testing at your own risk.</p>";
            
            $form->addProlog(<<<CUT
<div class="warning_box">
    $text
</div>   
CUT
);
        }
        
        $keyFile = defined('AM_KEYFILE') ? AM_KEYFILE : APPLICATION_PATH . '/configs/key.php';
        if (!is_readable($keyFile))
        {
            $random = $this->getDi()->app->generateRandomString(78);
            $text = "<p>To use credit card plugins, you need to create a key file that contains unique\n";
            $text .= "encryption key for your website. It is necessary even if the plugin does not\n";
            $text .= "store sensitive information.</p>";
            $text .= "<p>In a text editor, create file with the following content (one-line, no spaces before opening &lt;?php):\n";
            $text .= "<br /><br /><pre style='background-color: #e0e0e0;'>&lt;?php return '$random';</pre>\n";
            $text .= "<br />save the file as <b>key.php</b>, and upload to <i>amember/application/configs/</i> folder.\n";
            $text .= "This warning will disappear once you do it correctly.</p>";
            $text .= "<p>KEEP A BACKUP COPY OF THE key.php FILE (!)</p>";
            
            $form->addProlog(<<<CUT
<div class="warning_box">
    $text
</div>
CUT
            );
        }
        return parent::_afterInitSetupForm($form);
    }

    /**
     * If plugin require special actions to cancel invoice, cancelInvoice will be called 
     * after  Invoice will actually be cancelled by CreditCard Controller. 
     * do nothing by default;
     * @throws Am_Exception_InputError if failure;
     * @param Invoice $invoice 
     */
    
    function cancelInvoice(Invoice $invoice){
        return true;
    }
    public function cancelAction(Invoice $invoice, $actionName, Am_Paysystem_Result $result)
    {
        $invoice->setCancelled(true);
    }    
    public function getUpdateCcLink($user)
    {
        if ($this->storesCcInfo() && 
            $this->getDi()->ccRecordTable->findFirstByUserId($user->user_id))
        {
            return $this->getPluginUrl('update');
        }
    }
    
    public function onDaily()
    {
        $this->sendCcExpireMessage();
    }
    
    
    public function sendCcExpireMessage()
    {
        // Send Message only if plugin is allowed to store CC info. 
        if(!$this->storesCcInfo()) return; 
        
        if(!$this->getDi()->config->get('cc.card_expire')) return;
        
        $oRebillDate = $this->getDi()->dateTime;
        $oRebillDate->modify(sprintf("+%d days", $this->getDi()->config->get('cc.card_expire_days', 5)));

        foreach($this->getDi()->db->selectPage($total, "
            SELECT i.invoice_id, c.cc_expire 
            FROM ?_invoice i LEFT JOIN ?_cc c using(user_id)
            WHERE i.rebill_date = ? and CONCAT(SUBSTR(c.cc_expire, 3,2), SUBSTR(c.cc_expire, 1,2)) < ? 
            and i.paysys_id = ? 
            ", $oRebillDate->format('Y-m-d'), $oRebillDate->format('ym'), $this->getId()) as $r)
        {
            $invoice = $this->getDi()->invoiceTable->load($r['invoice_id']);
            if($et = Am_Mail_Template::load('cc.card_expire'))
            {
                $et->setUser($invoice->getUser());
                $et->setInvoice($invoice);
                $et->setExpires(substr_replace($r['cc_expire'], '/', 2, 0));
                $et->setMailPeriodic(Am_Mail::USER_REQUESTED);
                $et->send($invoice->getUser());
            }
            
        }
        
    }
    
}