<?php

class Am_Form_Element_ProductsWithQty extends HTML_QuickForm2_Element
{
    /** @var BillingPlan[] */
    protected $plans = array();
    protected $value = array();
    
    public function loadOptions(array $billingPlans)
    {
        $this->plans = array();
        foreach ($billingPlans as $p)
            $this->plans[$p->pk()] = $p;
        return $this;
    }
        
    public function __toString()
    {
        $opt = "";
        foreach ($this->plans as $p)
            try {
                $k = $p->plan_id;
                $v = $p->getProduct()->title;
                $v .= ' ('.$p->getTerms().')';
                $qty = $p->qty ? $p->qty : 1;
                $has_qty = $p->variable_qty ? 1 : '';
                if (!empty($this->value[$k]))
                {
                    $qty = (int)$this->value[$k];
                    $sel = "selected='selected'";
                } else {
                    $sel = '';
                }
                $opt .= "<option value='$k' $sel data-qty='$qty' data-has_qty='$has_qty'>$v</option>\n";
            } catch (Exception $e){}
        // now render magic select
        $name = Am_Controller::escape($this->getName());
        return <<<CUT
<select multiple='multiple' id='products-with-qty'>
    $opt
</select>
<script type='text/javascript'>
$(function(){
    $("#products-with-qty").magicSelect({
        callbackTitle: function(option)
        {
            var readonly = $(option).data("has_qty") ? '' : 'readonly="readonly"';
            var value = $(option).data("qty");
            var input = '<input type="text" name="{$name}['+option.value+']" size=2 '+readonly+' value="'+value+'"/>&nbsp;&nbsp;';
            return input + option.text;
        }
    });
});
</script>
<style type='text/css'>
    div.magicselect-item { padding: 8px; border-bottom: solid 1px lightgray; }
    div.magicselect-item input[readonly=readonly]{ background-color: lightgray; }
</style>
CUT;
    }

    public function getRawValue()
    {
        return $this->value;
    }

    public function getType()
    {
        return 'products-with-qty';
    }

    public function setValue($value)
    {
        foreach ($value as $plan_id => $qty)
        {
            $qty = intval(trim($qty));
            if ($qty <= 0) $qty = 1;
            ///
            if (!array_key_exists($plan_id, $this->plans)) 
                continue; // no such plan
            //
            $this->value[$plan_id] = $qty;
        }
    }
}

class AdminUserPaymentsController extends Am_Controller 
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_invoice', 'browse');
    }
    function preDispatch()
    {
        $this->user_id = intval($this->_request->user_id);
        if (!in_array($this->_request->getActionName(), array('log', 'invoice')))
        {
            if ($this->user_id <= 0)
                throw new Am_Exception_InputError("user_id is empty in " . get_class($this));
        }
        return parent::preDispatch();
    }
    public function createAdapter() {
        $adapter =  $this->_createAdapter();
        
        $query = $adapter->getQuery();
        $query->addWhere('t.user_id=?d', $this->user_id);

        return $adapter;
    }
    
    public function addInvoiceAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_invoice', 'insert');
        
        $form = new Am_Form_Admin('add-invoice');

        $tm_added = $form->addDate('tm_added')->setLabel(___('Date'));
        $tm_added->setValue($this->getDi()->sqlDate);
        $tm_added->addRule('required');

        $form->addElement(new Am_Form_Element_ProductsWithQty('product_id'))->setLabel(___('Products'))
            ->loadOptions($this->getDi()->billingPlanTable->selectAllSorted())
            ->addRule('required');
        $form->addSelect('paysys_id')->setLabel(___('Payment System'))
            ->loadOptions($this->getDi()->paysystemList->getOptions())
            ->addRule('required');

        $couponEdit = $form->addText('coupon')->setLabel('Coupon');
        $is_add_payment = $form->addCheckbox('is_add_payment')->setLabel(___('Add Payment'))
            ->setId('add-invoice-is_add_payment');
        $receipt = $form->addText('receipt')->setLabel(___('Receipt#'))
                ->setId('add-invoice-receipt');

        $receipt->addRule('nonempty', ___('This field is required'))->or_($is_add_payment->createRule('empty'));

        $form->addScript()->setScript('
            $("#add-invoice-is_add_payment").change(function(){
                $("#add-invoice-receipt").closest("div.row").toggle(this.checked)
            }).change();

        ');
        $form->addSaveButton();
        $form->setDataSources(array($this->getRequest()));

        do {
        if ($form->isSubmitted() && $form->validate()) {
            $vars = $form->getValue();
            $invoice = $this->getDi()->invoiceRecord;
            $invoice->setUser($this->getDi()->userTable->load($this->user_id));
            $invoice->tm_added = sqlTime($vars['tm_added']);
            if ($vars['coupon']) {
                $invoice->setCouponCode($vars['coupon']);
                $error = $invoice->validateCoupon();
                if ($error) 
                {
                    $couponEdit->setError($error);
                    break;
                }
            }
            foreach ($vars['product_id'] as $plan_id => $qty) {
                $p = $this->getDi()->billingPlanTable->load($plan_id);
                $pr = $p->getProduct();
                $invoice->add($pr, $qty);
            }
            
            $invoice->calculate();
            $invoice->setPaysystem($vars['paysys_id']);
            $invoice->data()->set('added-by-admin', $this->getDi()->authAdmin->getUserId());
            $invoice->save();

            if ($vars['is_add_payment']) {
                if($invoice->first_total<=0){
                    $invoice->addAccessPeriod(new Am_Paysystem_Transaction_Free($this->getDi()->plugins_payment->get($vars['paysys_id'])));
                }else{
                    $transaction = new Am_Paysystem_Transaction_Manual($this->getDi()->plugins_payment->get($vars['paysys_id']));
                    $transaction->setAmount($invoice->first_total)
                        ->setReceiptId($vars['receipt'])
                        ->setTime(new DateTime($vars['tm_added']));
                    $invoice->addPayment($transaction);
                }
            }
            return $this->redirectLocation(REL_ROOT_URL . '/admin-user-payments/index/user_id/' . $this->user_id);
        } // if
        } while (false);

        $this->view->content = '<h1>' . ___('Add Invoice') . ' (<a href="' . REL_ROOT_URL . '/admin-user-payments/index/user_id/' . $this->user_id . '">' . ___('return') . '</a>)</h1>' . (string)$form;
        $this->view->display('admin/user-layout.phtml');

    }

    public function calculateAccessDatesAction()
    {
        $invoice = $this->getDi()->invoiceRecord;
        $invoice->setUser($this->getDi()->userTable->load($this->user_id));

        $product = $this->getDi()->productTable->load($this->getRequest()->getParam('product_id'));
        $invoice->add($product);

        $begin_date = $product->calculateStartDate($this->getDi()->sqlDate, $invoice);

        $p = new Am_Period($product->getBillingPlan()->first_period);
        $expire_date = $p->addTo($begin_date);

        $this->ajaxResponse(array(
            'begin_date' => $begin_date,
            'expire_date' => $expire_date
        ));

    }

    public function getAddForm($set_date = true)
    {
        $form = new Am_Form_Admin;
        $form->setAction($url = $this->getUrl(null, 'addpayment', null, 'user_id',$this->user_id));
        $form->addText("receipt_id", array('tabindex' => 2))
             ->setLabel("Receipt#")
             ->addRule('required');
        $amt = $form->addSelect("amount", array('tabindex' => 3), array('intrinsic_validation' => false))
             ->setLabel("Amount");
        $amt->addRule('required', 'This field is required');
        if ($this->_request->getInt('invoice_id'))
        {
            $invoice = $this->getDi()->invoiceTable->load($this->_request->getInt('invoice_id'));
            if ((doubleval($invoice->first_total) === 0) || $invoice->getPaymentsCount())
                $amt->addOption($invoice->second_total, $invoice->second_total);
            else
                $amt->addOption($invoice->first_total, $invoice->first_total);
        }
        $form->addSelect("paysys_id", array('tabindex' => 1))
             ->setLabel("Payment System")
             ->loadOptions($this->getDi()->paysystemList->getOptions());
        $date = $form->addDate("dattm", array('tabindex' => 4))
             ->setLabel("Date Of Transaction");
        $date->addRule('required', 'This field is required');
        if($set_date) $date->setValue(sqlDate('now'));
        
        $form->addHidden("invoice_id");
        $form->addSaveButton();
        return $form;
    }
    function getAccessRecords()
    {
        return $this->getDi()->accessTable->selectObjects("SELECT a.*, p.title as product_title
            FROM ?_access a LEFT JOIN ?_product p USING (product_id)
            WHERE a.user_id = ?d
            ORDER BY begin_date, expire_date, product_title
            ", $this->user_id);
    }
    public function createAccessForm()
    {
        static $form;
        if (!$form)
        {
            $form = new Am_Form_Admin;
            $form->setAction($url = $this->getUrl(null, 'addaccess', null, 'user_id', $this->user_id));
            $sel = $form->addSelect('product_id');
            $options = $this->getDi()->productTable->getOptions();
            $sel->addOption(___('Please select an item...'), '');
            foreach ($options as $k => $v)
                $sel->addOption($v, $k);
            $sel->addRule('required', 'this field is required');
            $form->addText('comment');
            $form->addDate('begin_date')->addRule('required', 'this field is required');
            $form->addDate('expire_date')->addRule('required', 'this field is required');
            $form->addSaveButton('Add Access Manually');
        }
        return $form;
    }
    public function indexAction()
    {
        $this->getDi()->plugins_payment->loadEnabled();
        $this->view->invoices = $this->getDi()->invoiceTable->findByUserId($this->user_id);
        
        foreach ($this->view->invoices as $invoice)
        {
            if ($invoice->getStatus() == Invoice::RECURRING_ACTIVE)
                $invoice->_cancelUrl = REL_ROOT_URL . '/admin-user-payments/stop-recurring/user_id/'.$invoice->user_id.'?invoice_id=' . $invoice->pk();
                
        }
        
        $this->view->user_id = $this->user_id;
        $this->view->addForm = $this->getAddForm();
        $this->view->accessRecords = $this->getAccessRecords();
        $this->view->accessForm = $this->createAccessForm()->toObject();
        $this->view->display('admin/user-invoices.phtml');
    }
    
    
    public function changeAccessDateAction(){
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_payment', 'edit');
        
        $this->_response->setHeader("Content-type", "application/json");
        
        try
        {
            if(!($access_id = $this->_request->getInt('access_id'))) 
                throw new Am_Exception_InputError('No access_id submitted');
        

            switch($this->_request->getFiltered('field')){
                case 'begin_date' : 
                    $field = 'begin_date';
                    break; 
                case 'expire_date' : 
                    $field = 'expire_date';
                    break; 
                default:
                    throw new Am_Exception_InputError('Invalid field type. You can change begin or expire date fields only');
            }
        
            if(!($value = $this->_request->get('access_date')))
                throw new Am_Exception_InputError('No new value submitted');
        
            $value = new DateTime($value);
            $access = $this->getDi()->accessTable->load($access_id);
        
            $old_value = $access->get($field);
            if($old_value != $value)
            {
                $access->updateQuick($field, $value->format('Y-m-d'));
                
                if(!$access->data()->get('ORIGINAL_'.strtoupper($field)))
                    $access->data()->set('ORIGINAL_'.strtoupper($field), $old_value)->update();
                // Update cache and execute hooks
                $access->getUser()->checkSubscriptions(true);
                $this->getDi()->adminLogTable->log(
                    'Access date changed ('.$field.') old value='.$old_value.' new_value='.$access->get($field).' user_id='.$access->user_id, 
                    'access', 
                    $access->access_id
                    );
            }
            echo $this->getJson(array('success'=>true, 'reload'=>true));
        }catch(Exception $e){
            echo $this->getJson(array('success'=>false, 'error'=>$e->getMessage()));
        }
        
    }
    
    public function refundAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_payment', 'edit');
        
        $this->invoice_payment_id = $this->getInt('invoice_payment_id');
        if (!$this->invoice_payment_id)
            throw new Am_Exception_InputError("Not payment# submitted");
        $p = $this->getDi()->invoicePaymentTable->load($this->invoice_payment_id);
        /* @var $p InvoicePayment */
        if (!$p)
            throw new Am_Exception_InputError("No payment found");
        if ($this->user_id != $p->user_id)
            throw new Am_Exception_InputError("Payment belongs to another customer");
        if ($p->isRefunded())
            throw new Am_Exception_InputError("Payment is already refunded");
        $amount = sprintf('%.2f', $this->_request->get('amount'));
        if ($p->amount < $amount)
            throw new Am_Exception_InputError("Refund amount cannot exceed payment amount");
        if ($this->_request->getInt('manual'))
        {
            switch ($type = $this->_request->getFiltered('type'))
            {
                case 'refund':
                case 'chargeback':
                    $pl = $this->getDi()->plugins_payment->loadEnabled()->get($p->paysys_id);
                    if (!$pl)
                        throw new Am_Exception_InputError("Could not load payment plugin [$pl]");
                    $invoice = $p->getInvoice();
                    $transaction = new Am_Paysystem_Transaction_Manual($pl);
                    $transaction->setAmount($amount);
                    $transaction->setReceiptId($p->receipt_id . '-manual-'.$type);
                    $transaction->setTime($this->getDi()->dateTime);
                    if ($type == 'refund')
                        $invoice->addRefund($transaction, $p->receipt_id);
                    else
                        $invoice->addChargeback($transaction, $p->receipt_id);
                    break;
                case 'correction':
                    $this->getDi()->accessTable->deleteBy(array('invoice_payment_id' => $this->invoice_payment_id));
                    $invoice = $p->getInvoice();
                    $p->delete();
                    $invoice->updateStatus();
                    break;
                default:
                    throw new Am_Exception_InputError("Incorrect refund [type] passed:" . $type );
            }
            $res = array(
                'success' => true,
                'text'    => ___("Payment has been successfully refunded"),
            );
        } else { // automatic 
            /// ok, now we have validated $p here
            $pl = $this->getDi()->plugins_payment->loadEnabled()->get($p->paysys_id);
            if (!$pl)
                throw new Am_Exception_InputError("Could not load payment plugin [$pl]");
            /* @var $pl Am_Paysystem_Abstract */
            $result = new Am_Paysystem_Result;
            $pl->processRefund($p, $result, $amount);

            if ($result->isSuccess())
            {
                $p->getInvoice()->addRefund($result->getTransaction(), $p->receipt_id, $amount);

                $res = array(
                    'success' => true,
                    'text'    => ___("Payment has been successfully refunded"),
                );
            } elseif ($result->isAction()) {
                $action = $result->getAction();
                if ($action instanceof Am_Paysystem_Action_Redirect)
                {
                    $res = array(
                        'success' => 'redirect',
                        'url'     => $result->getUrl(),
                    );
                } else {// todo handle other actions if necessary
                    throw new Am_Exception_NotImplemented("Could not handle refund action " . get_class($action));
                }
            } elseif ($result->isFailure()) {
                $res = array(
                    'success' => false,
                    'text' => join(";", $result->getErrorMessages()),
                );
            }
        }
        $this->_response->setHeader("Content-type", "application/json");
        echo $this->getJson($res);
    }
    
    function addaccessAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_payment', 'insert');
        
        $form = $this->createAccessForm();
        if ($form->validate())
        {
            $access = $this->getDi()->accessRecord;
            $access->setForInsert($form->getValue());
            unset($access->save);
            $access->user_id = $this->user_id;
            $access->insert();
            // send 1-day autoresponders if supposed to
            $user = $this->getDi()->userTable->load($this->user_id);
            $this->getDi()->emailTemplateTable->sendZeroAutoresponders($user);
            //
            $form->setDataSources(array(new Am_Request(array())));
            $form->getElementById('begin_date-0')->setValue('');
            $form->getElementById('expire_date-0')->setValue('');
        } else {
            
        }
        return $this->indexAction();
    }
    function delaccessAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_payment', 'delete');
        
        $access = $this->getDi()->accessTable->load($this->getInt('id'));
        if ($access->user_id != $this->user_id)
            throw new Am_Exception_InternalError("Wrong access record to delete - member# does not match");
        $access->delete();
        return $this->indexAction();
    }
    function addpaymentAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_payment', 'insert');
        
        $invoice = $this->getDi()->invoiceTable->load($this->_request->getInt('invoice_id'));
        if (!$invoice || $invoice->user_id != $this->user_id)
            throw new Am_Exception_InputError("Invoice not found");

        $form = $this->getAddForm(false);
        if (!$form->validate())
        {
            echo $form;
            return;
        }
        
        $vars = $form->getValue();
        $transaction = new Am_Paysystem_Transaction_Manual($this->getDi()->plugins_payment->get($vars['paysys_id']));
        $transaction->setAmount($vars['amount'])->setReceiptId($vars['receipt_id'])->setTime(new DateTime($vars['dattm']));
        if(floatval($vars['amount']) == 0)
            $invoice->addAccessPeriod($transaction);
        else
            $invoice->addPayment($transaction);
                

        $form->setDataSources(array(new Am_Request(array())));
        $form->addHidden('saved-ok');
        echo $form;
    }
    
    function stopRecurringAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_invoice', 'edit');
        // todo: rewrote stopRecurring
        $invoiceId = $this->_request->getInt('invoice_id');
        if (!$invoiceId)
            throw new Am_Exception_InputError("No invoice# provided");
        
        $invoice = $this->getDi()->invoiceTable->load($invoiceId);
        $plugin = $this->getDi()->plugins_payment->loadGet($invoice->paysys_id, true);
        
        $result = new Am_Paysystem_Result();
        $result->setSuccess();
        try {
            $plugin->cancelAction($invoice, 'cancel-admin', $result);
        } catch (Exception $e) {
            Am_Controller::ajaxResponse(array('ok' => false, 'msg' => $e->getMessage()));
            return;
        }

        if ($result->isSuccess())
        {
            $invoice->setCancelled(true);
            $this->getDi()->adminLogTable->log("Invoice Cancelled", 'invoice', $invoice->pk());
            Am_Controller::ajaxResponse(array('ok' => true));
        } elseif ($result->isAction()) {
            $action = $result->getAction();
            if ($action instanceof Am_Paysystem_Action_Redirect)
                Am_Controller::ajaxResponse(array('ok'=> false, 'redirect' => $action->getUrl()));
            else
                $action->process(); // this .. simply will not work hopefully we never get to this point
        } else {
            Am_Controller::ajaxResponse(array('ok' => false, 'msg' => $result->getLastError()));
        }
    }

    function startRecurringAction()
    {
        if(!defined('AM_ALLOW_RESTART_CANCELLED'))
        {
            Am_Controller::ajaxResponse(array('ok' => false, 'msg' => 'Restart is not allowed'));
            return;
        }
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_invoice', 'edit');
        $invoiceId = $this->_request->getInt('invoice_id');
        if (!$invoiceId)
            throw new Am_Exception_InputError("No invoice# provided");        
        $invoice = $this->getDi()->invoiceTable->load($invoiceId);
        $invoice->setCancelled(false);
        $this->getDi()->adminLogTable->log("Invoice Restarted", 'invoice', $invoice->pk());
        Am_Controller::ajaxResponse(array('ok' => true));
    }
    
    function changeRebillDateAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_invoice', 'edit');
        $invoice_id = $this->_request->getInt('invoice_id');
        $rebill_date  = $this->_request->get('rebill_date');
        try{
            if(!$invoice_id) throw new Am_Exception_InputError('No invoice provided');
            $invoice = $this->getDi()->invoiceTable->load($invoice_id);
            
            // Invoice must be recurring active and rebills should be controlled by paylsystem, 
            // otherwise this doesn't make any sence
            
            if(($invoice->status != Invoice::RECURRING_ACTIVE) || 
                ($invoice->getPaysystem()->getRecurringType() != Am_Paysystem_Abstract::REPORTS_CRONREBILL)
                ) throw new Am_Exception_InputError('Unable to change rebill_date for this invoice!');
            
            $rebill_date = new DateTime($rebill_date);
            $old_rebill_date = $invoice->rebill_date;
            
            $invoice->updateQuick('rebill_date',  $rebill_date->format('Y-m-d'));
            $invoice->data()->set('first_rebill_failure', null)->update();
            
            $this->getDi()->invoiceLogTable->log($invoice_id, null, 
                $title = 'Rebill Date changed from '.$old_rebill_date.' to '.$invoice->rebill_date, $title);
            
            Am_Controller::ajaxResponse(array('ok'=>true, 'msg'=>'Rebill date has been changed!'));
            
        }catch(Exception $e){
            Am_Controller::ajaxResponse(array('ok'=>false, 'msg'=>$e->getMessage()));
            
        }
    }
    
    
    
    function logAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission(Am_Auth_Admin::PERM_LOGS);
        $invoice = $this->getDi()->invoiceTable->load($this->_request->getInt('invoice_id'));
        $this->getResponse()->setHeader('Content-type', 'text/xml');
        echo $invoice->exportXmlLog();
    }

    function approveAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_invoice', 'edit');
        $invoiceId = $this->_request->getInt('invoice_id');
        if (!$invoiceId)
            throw new Am_Exception_InputError("No invoice# provided");
        $invoice = $this->getDi()->invoiceTable->load($invoiceId);
        if (!$invoice) 
            throw new Am_Exception_InputError("No invoice found [$invoiceId]");
        $invoice->approve();
        $this->_redirect('admin-user-payments/index/user_id/'.$invoice->user_id.'#invoice-'.$invoiceId);
    }
    
    
    function invoiceAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_invoice', 'browse');
        $payment = $this->getDi()->invoicePaymentTable->load($this->_request->getInt('payment_id'));

        $pdfInvoice = new Am_Pdf_Invoice($payment);
        $pdfInvoice->setDi($this->getDi());
        $pdfInvoiceRendered = $pdfInvoice->render();

        $this->noCache();
        $this->getResponse()->setHeader('Content-type', 'application/pdf');
        $this->getResponse()->setHeader('Content-Length', strlen($pdfInvoiceRendered));
        $this->getResponse()->setHeader('Content-Disposition', "attachment; filename={$pdfInvoice->getFileName()}");

        echo $pdfInvoiceRendered;
    }
    
    function replaceProductAction()
    {
        $this->getDi()->authAdmin->getUser()->checkPermission('grid_payment',  'edit');
        
        $item = $this->getDi()->invoiceItemTable->load($this->_request->getInt('id'));
        $pr = $this->getDi()->productTable->load($item->item_id);

        $form = new Am_Form_Admin('replace-product-form');
        $form->setDataSources(array($this->_request));
        $form->method = 'post';
        $form->addHidden('id');
        $form->addHidden('user_id');
        $form->addStatic()
            ->setLabel(___('Replace Product'))
            ->setContent("#{$pr->product_id} [$pr->title]");
        $sel = $form->addSelect('product_id')->setLabel('To Product');    
        $options = array('' => '-- ' . ___('Please select') . ' --');
        foreach ($this->getDi()->billingPlanTable->getProductPlanOptions() as $k => $v)
            if (strpos($k, $pr->pk().'-')!==0)
                $options[$k] = $v;
        $sel->loadOptions($options);
        $sel->addRule('required');
        $form->addSubmit('_save', array('value' => ___('Save')));
        if ($form->isSubmitted() && $form->validate())
        {
            try {
                list($p,$b) = explode("-", $sel->getValue(), 2);
                $item->replaceProduct(intval($p), intval($b));
                $this->getDi()->adminLogTable->log("Inside invoice: product #{$item->item_id} replaced to product #$p (plan #$b)", 'invoice', $item->invoice_id);
                return $this->ajaxResponse(array('ok'=>true));
            } catch (Am_Exception $e) {
                $sel->setError($e->getMessage());
            }
        }
        echo $form->__toString();
    }
}
