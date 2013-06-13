<?php
// @todo : remove rules within UI

class Am_Grid_Action_AddTier extends Am_Grid_Action_Abstract
{
    protected $type = Am_Grid_Action_Abstract::NORECORD;
    protected $title = 'Add Tier';

    public function run()
    {
        $max_tier = $this->getDi()->affCommissionRuleTable->getMaxTier();
        $next_tier = ++$max_tier;

        $comm = $this->getDi()->affCommissionRuleRecord;
        $comm->tier = $next_tier;
        $comm->type = AffCommissionRule::TYPE_GLOBAL;
        $comm->sort_order = ($next_tier + 1) * 10000;
        $comm->comment = ($next_tier + 1) . '-Tier Affiliates Commission';
        $comm->save();

        $this->grid->redirectBack();
    }
    /**
     *
     * @return Am_Di
     */
    protected function getDi()
    {
        return $this->grid->getDi();
    }
}

class Am_Grid_Action_RemoveLastTier extends Am_Grid_Action_Abstract
{
    protected $type = Am_Grid_Action_Abstract::NORECORD;
    protected $title = 'Remove Last Tier';

    public function run()
    {
        $max_tier = $this->getDi()->affCommissionRuleTable->getMaxTier();
        if ($max_tier) {
            $this->getDi()->affCommissionRuleTable->findFirstByTier($max_tier)->delete();
        }
        $this->grid->redirectBack();
    }
    /**
     *
     * @return Am_Di
     */
    protected function getDi()
    {
        return $this->grid->getDi();
    }
}

class Am_Grid_Action_TestAffCommissionRule extends Am_Grid_Action_Abstract
{
    protected $type = Am_Grid_Action_Abstract::NORECORD;
    protected $title = 'Test Rules';
    
    public function run()
    {
        $f = $this->createForm();
        echo '<h1>' . ___('Test Commission Rules') . '</h1>';
        if ($f->isSubmitted() && $f->validate() && $this->process($f->getValue())) 
            return;
        echo $f;
    }
    function process(array $vars)
    {
        $vars['user'] = filterId($vars['user']);
        $vars['aff']  = filterId($vars['aff']);
        $user = Am_Di::getInstance()->userTable->findFirstByLogin($vars['user']);
        if (!$user)
            throw new Am_Exception_InputError("User {$vars['user']} not found");
        $aff  = Am_Di::getInstance()->userTable->findFirstByLogin($vars['aff']);
        if (!$aff)
            throw new Am_Exception_InputError("Affiliate {$vars['aff']} not found");
        /* @var $invoice Invoice */
        $invoice = Am_Di::getInstance()->invoiceTable->createRecord();
        $invoice->setUser($user);
        if ($vars['coupon']) {
            $invoice->setCouponCode($vars['coupon']);
            $error = $invoice->validateCoupon();
            if ($error) throw new Am_Exception_InputError($error);
        }
        $user->aff_id = $aff->pk();
        foreach (Am_Di::getInstance()->productTable->loadIds($vars['product_ids']) as $pr)
            $invoice->add($pr);
        $invoice->paysys_id = 'manual';
        $invoice->calculate();

        $invoice->invoice_id = '00000';
        $invoice->public_id = 'TEST';
        $invoice->tm_added = sqlTime('now');

        echo "<pre>";
        echo $invoice->render();
        echo
            "\nBilling Terms: " . $invoice->getTerms() . 
            "\n".str_repeat("-", 70)."\n";
        
        $helper = new Am_View_Helper_UserUrl();
        $helper->setView(new Am_View);
        printf("User Ordering the subscription: <a target='_blank' href='%s'>%d/%s &quot;%s&quot; &lt;%s&gt</a>\n",
            $helper->userUrl($user->pk()),
            $user->pk(), Am_Controller::escape($user->login), 
            Am_Controller::escape($user->name_f . ' ' . $user->name_l),
            Am_Controller::escape($user->email));
        printf("Reffered Affiliate: <a target='_blank' href='%s'>%d/%s &quot;%s&quot; &lt;%s&gt</a>\n",
            $helper->userUrl($aff->pk()),
            $aff->pk(), 
            Am_Controller::escape($aff->login), 
            Am_Controller::escape($aff->name_f . ' ' . $aff->name_l),
            Am_Controller::escape($aff->email));
        
        //COMMISSION FOR FREE SIGNUP
        if (!(float)$invoice->first_total
            && !(float)$invoice->second_total
            && $vars['is_first']) {

            echo "\n<strong>FREE SIGNUP</strong>:\n";
            list($item,) = $invoice->getItems();

            echo sprintf("* ITEM: %s\n", Am_Controller::escape($item->item_title));
            foreach (Am_Di::getInstance()->affCommissionRuleTable->findRules($invoice, $item, $aff, 0, 0) as $rule)
            {
                echo $rule->render('*   ');
            }
            echo "* AFFILIATE WILL GET FOR THIS ITEM: " . Am_Di::getInstance()->affCommissionRuleTable->calculate($invoice, $item, $aff, 0, 0) . " $invoice->currency \n";
            echo "* " . str_repeat("-", 70) . "\n";
        }

        //COMMISSION FOR FIRST PAYMENT
        $price_field = (float)$invoice->first_total ? 'first_total' : 'second_total';
        if ((float)($invoice->$price_field)) {
            echo "\n<strong>FIRST PAYMENT ($invoice->currency {$invoice->$price_field})</strong>:\n";

            $payment = Am_Di::getInstance()->invoicePaymentTable->createRecord();
            $payment->invoice_id = @$invoice->invoice_id;
            $payment->dattm = sqlTime('now');
            $payment->amount = $invoice->$price_field;
            echo str_repeat("-", 70) . "\n";
            foreach ($invoice->getItems() as $item)
            {
                if (!(float)($item->$price_field)) continue; //do not calculate commission for free items within invoice
                echo sprintf("* ITEM: %s ($invoice->currency {$item->$price_field})\n", Am_Controller::escape($item->item_title));
                foreach (Am_Di::getInstance()->affCommissionRuleTable->findRules($invoice, $item, $aff, 1, 0, $payment->dattm) as $rule)
                {
                    echo $rule->render('*   ');
                }
                echo "* AFFILIATE WILL GET FOR THIS ITEM: " . Am_Di::getInstance()->affCommissionRuleTable->calculate($invoice, $item, $aff, 1, 0, $payment->amount, $payment->dattm) . " $invoice->currency \n";
                echo "* " . str_repeat("-", 70) . "\n";
            }
        }
        //COMMISSION FOR SECOND AND SUBSEQUENT PAYMENTS
        if ($invoice->second_total)
        {
            echo "\n<strong>SECOND AND SUBSEQUENT PAYMENTS ($invoice->second_total $invoice->currency)</strong>:\n";
            $payment = Am_Di::getInstance()->invoicePaymentTable->createRecord();
            $payment->invoice_id = @$invoice->invoice_id;
            $payment->dattm = sqlTime('now');
            $payment->amount = $invoice->second_total;
            echo str_repeat("-", 70) . "\n";
            foreach ($invoice->getItems() as $item)
            {
                if (!(float)$item->second_total) continue; //do not calculate commission for free items within invoice
                echo sprintf("* ITEM:  %s ($item->second_total $invoice->currency)\n", Am_Controller::escape($item->item_title));
                foreach (Am_Di::getInstance()->affCommissionRuleTable->findRules($invoice, $item, $aff, 2, 0, $payment->dattm) as $rule)
                { 
                    echo $rule->render('*   ');
                }
                echo "* AFFILIATE WILL GET FOR THIS ITEM: " . 
                    Am_Di::getInstance()->affCommissionRuleTable->calculate($invoice, $item, $aff, 2, 0, $payment->amount, $payment->dattm).
                    " $invoice->currency \n";
                echo "* " . str_repeat("-", 70) . "\n";
            }
        }
        echo "</pre>";
        return true;
    }
    protected function createForm()
    {
        $f = new Am_Form_Admin;
        $f->addText('user')->setLabel('Enter username of existing user')
            ->addRule('required', 'This field is required');
        $f->addText('aff')->setLabel('Enter username of existing affiliate')
            ->addRule('required', 'This field is required');
        $f->addText('coupon')->setLabel('Enter coupon code or leave field empty');
        $f->addCheckbox('is_first')->setLabel('Is first user invoice?');
        $f->addMagicSelect('product_ids')->setLabel('Choose products to include into test invoice')
                ->loadOptions(Am_Di::getInstance()->productTable->getOptions())
                ->addRule('required', 'This field is required');
        $f->addSubmit('', array('value' => 'Test'));
        $f->addScript()->setScript(<<<CUT
$(function(){
    $("#user-0, #aff-0" ).autocomplete({
        minLength: 2,
        source: window.rootUrl + "/admin-users/autocomplete"
    });
});            
CUT
        );
        foreach ($this->grid->getVariablesList() as $k)
        {
            $kk = $this->grid->getId() . '_' . $k;
            if ($v = @$_REQUEST[$kk])
                $f->addHidden($kk)->setValue($v);
        }
        return $f;
    }
}

class Am_Grid_Editable_AffCommissionRule extends Am_Grid_Editable 
{
    protected $permissionId = 'affiliates';
    public function renderTable()
    {
        $root = REL_ROOT_URL;
        return parent::renderTable() . 
            <<<CUT
<div class='comment'>
For each item in purchase, aMember will look through all rules, from top to bottom.
If it finds a matching multiplier, it will be remembered.
If it finds a matching custom rule, it takes commission rates from it.
If no matching custom rule was found, it uses "Default" commission settings.

For n-tier affiliates, no rules are used, you can just define percentage of commission earned by previous level.
</div>

<p><br />
<a target='_top' href='$root/admin-setup/aff'>Check other Affiliate Program Settings</a>
</p>
CUT;
    }
    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct('_affcommconf', 
            'Affiliate Commission Config', Am_Di::getInstance()->affCommissionRuleTable->createQuery(),
            $request, $view);
        
        $this->setRecordTitle('Commission Rule');
        $this->addField('comment', 'Comment')->setRenderFunction(array($this, 'renderComment'));
        $this->addField('sort_order', 'Sort')->setRenderFunction(array($this, 'renderSort'));
        $this->addField('_commission', 'Commission', false)->setRenderFunction(array($this, 'renderCommission'));
        $this->addField('_conditions', 'Conditions', false)->setRenderFunction(array($this, 'renderConditions'));
        
        $this->actionGet('edit')->setTarget('_top');
        $this->actionGet('insert')->setTitle('New Custom %s')->setTarget('_top');
        $this->actionAdd(new Am_Grid_Action_AddTier());
        if ($this->getDi()->affCommissionRuleTable->getMaxTier()) {
            $this->actionAdd(new Am_Grid_Action_RemoveLastTier());
        }
        $this->actionAdd(new Am_Grid_Action_TestAffCommissionRule());
        
        $this->setForm(array($this,'createConfigForm'));
        $this->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($this, '_valuesToForm'));
        $this->addCallback(Am_Grid_Editable::CB_VALUES_FROM_FORM, array($this, '_valuesFromForm'));
    }
    public function renderSort(AffCommissionRule $rule)
    {
        $v = $rule->isGlobal() ? '-' : $rule->sort_order;
        return $this->renderTd($v);
    }
    public function renderCommission(AffCommissionRule $rule, $fieldName)
    {
        return $this->renderTd($rule->renderCommission(), false);
    }
    public function renderConditions(AffCommissionRule $rule, $fieldName)
    {
        return $this->renderTd($rule->renderConditions(), true);
    }
    public function renderComment(AffCommissionRule $rule)
    {
        if ($rule->isGlobal()) 
            $text = '<strong>'.$rule->comment.'</strong>';
        else
            $text = $this->escape($rule->comment);
        return "<td>$text</td>\n";
    }
    public function _valuesToForm(& $values, AffCommissionRule $record)
    {
        $values['_conditions'] = json_decode(@$values['conditions'], true);
        foreach ((array)$values['_conditions'] as $k => $v) {
            $values['_conditions_status'][$k] = 1; //enabled
        }
    }
    public function _valuesFromForm(& $values, AffCommissionRule $record)
    {
        $values['free_signup_t'] = '$';
        $conditions = array();
        foreach ($values['_conditions_status'] as $k => $v) {
            if ($v) {
                $conditions[$k] = $values['_conditions'][$k];
            }
        }
        $this->cleanUpConditions($conditions);
        if (!empty($conditions)) {
            $values['conditions'] = json_encode($conditions);
        }
    }

    /**
     * Remove incomplete conditions
     *
     * @param array $conditions
     */
    protected function cleanUpConditions(& $conditions)
    {
        foreach ($conditions as $type => $vars) {
            switch ($type) {
                case AffCommissionRule::COND_PRODUCT_ID :
                case AffCommissionRule::COND_PRODUCT_CATEGORY_ID :
                    if (empty($vars)) unset($conditions[$type]);
                    break;
                case AffCommissionRule::COND_AFF_SALES_AMOUNT:
                case AffCommissionRule::COND_AFF_ITEMS_COUNT:
                case AffCommissionRule::COND_AFF_SALES_COUNT:
                    if (empty($vars['count']) || empty($vars['days']))
                        unset($conditions[$type]);
                    break;
                case AffCommissionRule::COND_COUPON :
                    if (($vars['type'] == 'batch' && !$vars['batch_id'])
                        || ($vars['type'] == 'coupon' && !$vars['code']))
                        unset($conditions[$type]);
                    break;
            }
        }
    }

    public function createConfigForm(Am_Grid_Editable $grid)
    {
        $form = new Am_Form_Admin;
        
        $record = $grid->getRecord($grid->getCurrentAction());
        
        if (empty($record->type)) $record->type = null;
        if (empty($record->tier)) $record->tier = 0;
        
        $globalOptions = AffCommissionRule::getTypes();
        ($record->type && !isset($globalOptions[$record->type])) && $globalOptions[$record->type] = $record->getTypeTitle();

        $cb = $form->addSelect('type')->setLabel('Type')->loadOptions($globalOptions);
        if ($record->isGlobal())
            $cb->toggleFrozen(true);
        
        $form->addScript()->setScript(<<<CUT
$(function(){
    $("select#type-0").change(function(){
        var val = $(this).val();
        $("fieldset#multiplier").toggle(val == 'multi');
        $("fieldset#commission").toggle(val != 'multi');
        var checked = val.match(/^global-/);
        $("#conditions").toggle(!checked);
        $("#sort_order-0").closest(".row").toggle(!checked);
    }).change();
    
    $("#condition-select").change(function(){
        var val = $(this).val();
        $(this.options[this.selectedIndex]).prop("disabled", true);
        this.selectedIndex = 0;
        $('input[name="_conditions_status[' + val + ']"]').val(1);
        $('#row-'+val).show();
    });

    $("#conditions .row").not("#row-condition-select").each(function(){
        var val = /row-(.*)/i.exec(this.id).pop();
        if (!$('input[name="_conditions_status[' + val + ']"]').val()) {
            $(this).hide();
        } else {
            $("#condition-select option[value='"+val+"']").prop("disabled", true);
        }
        $(this).find(".element-title").append("&nbsp;<a href='javascript:' class='hide-row'>X</a>&nbsp;");
    });

    $("a.hide-row").live('click',function(){
        var row = $(this).closest(".row");
        var id = row.hide().attr("id");
        var val = /row-(.*)/i.exec(id).pop();
        $('input[name="_conditions_status[' + val + ']"]').val(0);
        $("#condition-select option[value='"+val+"']").prop("disabled", false);
    });

    $('#used-type').change(function(){
        $('#used-batch_id, #used-code').hide();
        switch ($(this).val()) {
            case 'batch' :
                $('#used-batch_id').show();
                break;
            case 'coupon' :
                $('#used-code').show();
                break;
        }

    }).change()
});
CUT
);
        
        $comment = $form->addText('comment', array('size' => 40))
            ->setLabel('Rule title - for your own reference');
        if ($record->isGlobal()) {
            $comment->toggleFrozen(true);
        } else {
            $comment->addRule('required', 'This field is required');
        }

        if (!$record->isGlobal())
            $form->addInteger('sort_order')
                ->setLabel('Sort order - rules with lesser values executed first');

        if (!$record->isGlobal()) // add conditions
        {
            $set = $form->addFieldset('', array('id'=>'conditions'))->setLabel('Conditions');
            $set->addSelect('', array('id' => 'condition-select'))->setLabel('Add Condition')->loadOptions(array(
                '' => 'Select Condition...',
                'product_id' => 'By Product',
                'product_category_id' => 'By Product Category',
                'aff_group_id' => 'By Affiliate Group Id',
                'aff_sales_count' => 'By Affiliate Sales Count',
                'aff_items_count' => 'By Affiliate Item Sales Count',
                'aff_sales_amount' => 'By Affiliate Sales Amount',
                'coupon' => 'By Used Coupon'
            ));

            $set->addHidden('_conditions_status[product_id]');

            $set->addMagicSelect('_conditions[product_id]', array('id' => 'product_id'))
                ->setLabel(array('This rule is for particular products', 
                    'if none specified, rule works for all products'))
               ->loadOptions(Am_Di::getInstance()->productTable->getOptions());

            $set->addHidden('_conditions_status[product_category_id]');

            $el = $set->addMagicSelect('_conditions[product_category_id]', array('id' => 'product_category_id'))
                ->setLabel(array('This rule is for particular product categories', 
                    'if none specified, rule works for all product categories'));
            $el->loadOptions(Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions());

            $set->addHidden('_conditions_status[aff_group_id]');

            $el = $set->addMagicSelect('_conditions[aff_group_id]', array('id' => 'aff_group_id'))
                ->setLabel(array('This rule is for particular affiliate groups', 
                    'you can add user groups and assign it to customers in User editing form'));
            $el->loadOptions(Am_Di::getInstance()->userGroupTable->getSelectOptions());

            $set->addHidden('_conditions_status[aff_sales_count]');

            $gr = $set->addGroup('_conditions[aff_sales_count]', array('id' => 'aff_sales_count'))
                ->setLabel(array('Affiliate sales count', 
                'trigger this commission if affiliate made more than ... sales within ... days before the current date' . PHP_EOL .
                '(only count of new invoices is calculated)'
                ));
            $gr->addStatic()->setContent('use only if affiliate referred ');
            $gr->addInteger('count', array('size'=>4));
            $gr->addStatic()->setContent(' invoices within last ');
            $gr->addInteger('days', array('size'=>4));
            $gr->addStatic()->setContent(' days');

            $set->addHidden('_conditions_status[aff_items_count]');

            $gr = $set->addGroup('_conditions[aff_items_count]', array('id' => 'aff_items_count'))
                ->setLabel(array('Affiliate items count', 
                'trigger this commission if affiliate made more than ... item sales within ... days before the current date' . PHP_EOL .
                '(only count of items in new invoices is calculated)'
                ));
            $gr->addStatic()->setContent('use only if affiliate made ');
            $gr->addInteger('count', array('size'=>4));
            $gr->addStatic()->setContent(' item sales within last ');
            $gr->addInteger('days', array('size'=>4));
            $gr->addStatic()->setContent(' days');

            $set->addHidden('_conditions_status[aff_sales_amount]');

            $gr = $set->addGroup('_conditions[aff_sales_amount]', array('id' => 'aff_sales_amount'))
                ->setLabel(array('Affiliate sales amount', 
                'trigger this commission if affiliate made more than ... sales within ... days before the current date' . PHP_EOL .
                '(only new invoices calculated)'
                ));
            $gr->addStatic()->setContent('use only if affiliate made ');
            $gr->addInteger('count', array('size'=>4));
            $gr->addStatic()->setContent(' ' .Am_Currency::getDefault(). ' in commissions within last ');
            $gr->addInteger('days', array('size'=>4));
            $gr->addStatic()->setContent(' days');

            $set->addHidden('_conditions_status[coupon]');

            $gr = $set->addGroup('_conditions[coupon]', array('id' => 'coupon'))
                ->setLabel(array('Used coupon'));
            $gr->addSelect('used')
                ->loadOptions(array(
                   '1' => 'Used',
                   '0' => "Didn't Use"
                ));
            $gr->addSelect('type')
                ->setId('used-type')
                ->loadOptions(array(
                   'any' => 'Any Coupon',
                   'batch' => "Coupon From Batch",
                   'coupon' => "Specific Coupon"
                ));
            $gr->addSelect('batch_id')
                ->setId('used-batch_id')
                ->loadOptions(
                $this->getDi()->couponBatchTable->getOptions()
            );
            $gr->addText('code', array('size'=>10))
                ->setId('used-code');
            
            
        }

        $set = $form->addFieldset('', array('id' => 'commission'))->setLabel('Commission');
        
        if ($record->tier == 0)
        {
            $set->addElement(new Am_Form_Element_AffCommissionSize(null, null, 'first_payment'))
                ->setLabel(___("Commission for First Payment\ncalculated for first payment in each invoice"));
            $set->addElement(new Am_Form_Element_AffCommissionSize(null, null, 'recurring'))
                ->setLabel(___("Commission for Rebills"));
            $group = $set->addGroup('')
                ->setLabel(___("Commission for Free Signup\ncalculated for first customer invoice only"));
            $group->addText('free_signup_c', array('size'=>5));
            $group->addStatic()->setContent('&nbsp;&nbsp;' . Am_Currency::getDefault());
                ;//->addRule('gte', 'Value must be a valid number > 0, or empty (no text)', 0);
        } else {
            $set->addText('first_payment_c')
                ->setLabel(___("Commission\n% of commission received by referred affiliate"));
        }
        if (!$record->isGlobal())
        {
            $set = $form->addFieldset('', array('id' => 'multiplier'))->setLabel('Multipier');
            $set->addText('multi', array('size' => 5, 'placeholder' => '1.0'))
                ->setLabel(array(___("Multiply commission calculated by the following rules
                    to number specified in this field. To keep commission untouched, enter 1 or delete this rule")))
                ;//->addRule('gt', 'Values must be greater than 0.0', 0.0);
        }
        return $form;
    }
}

class Aff_AdminCommissionController extends Am_Controller_Pages
{
    public function initPages()
    {
        $this->addPage(array($this, 'createGrid'), 'grid', ___('Commissions'));
        $this->addPage(array($this, 'createClicksGrid'), 'clicks', ___('Clicks'));
        $this->addPage(array($this, 'createLeadsGrid'), 'leads', ___('Leads'));
        $this->addPage('Am_Grid_Editable_AffCommissionRule', 'config', ___('Commission Configuration'));
    }
    
    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->affCommissionTable);
        $ds->leftJoin('?_invoice', 'i', 'i.invoice_id=t.invoice_id');
        $ds->leftJoin('?_user', 'u', 'u.user_id=i.user_id');
        $ds->leftJoin('?_user', 'a', 't.aff_id=a.user_id');
        $ds->leftJoin('?_product', 'p', 't.product_id=p.product_id');
        $ds->addField('CONCAT(a.name_f, \' \', a.name_l,\' [#\', a.user_id, \'] \')', 'aff_name')
           ->addField('u.user_id', 'user_id')
           ->addField('CONCAT(u.name_f, \' \',u.name_l,\' [#\', u.user_id, \'] \')', 'user_name')
           ->addField('u.email', 'user_email')
           ->addField('p.title', 'product_title')
           ->addField('IF(payout_detail_id IS NULL, \'no\', \'yes\')', 'is_paid');
        $ds->setOrder('commission_id', 'desc');
        
        $grid = new Am_Grid_ReadOnly('_affcomm', ___("Affiliate Commission"), $ds, $this->_request, $this->view);
        $grid->setPermissionId('affiliates');
        
        $userUrl = new Am_View_Helper_UserUrl();
        $grid->addField(new Am_Grid_Field_Date('date', ___('Date')))->setFormatDate();
        $grid->addField('aff_name', ___('Affiliate'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($userUrl->userUrl('{aff_id}'), '_blank'));
        $grid->addField('user_name', ___('User'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($userUrl->userUrl('{user_id}'), '_blank'));
        $grid->addField('product_title', ___('Product'));
        $grid->addField('record_type', ___('Type'));
        $grid->addField('amount', ___('Commission'));
        $grid->addField('is_paid', ___('Paid'));
        $grid->addField('tier', ___('Tier'));
        return $grid;
    }
    public function createClicksGrid()
    {
        $ds = new Am_Query($this->getDi()->affClickTable);

        $ds->leftJoin('?_user', 'a', 't.aff_id=a.user_id');
        $ds->addField('CONCAT(a.name_f, \' \', a.name_l)', 'aff_name');

        $ds->leftJoin('?_aff_banner', 'b', 't.banner_id=b.banner_id');
        $ds->addField('b.title', 'banner');
        
        $grid = new Am_Grid_ReadOnly('_affclicks', ___("Clicks"), $ds, $this->_request, $this->view);
        $grid->setPermissionId('affiliates');
        $userUrl = new Am_View_Helper_UserUrl();
        $grid->addField('time', ___('Time'))->setFormatFunction('amDateTime');
        $grid->addField('remote_addr', ___('IP Address'));
        $grid->addField('banner', 'Banner');
        $grid->addField(new Am_Grid_Field_Expandable('referer', ___('Referer')));
        
        $grid
            ->addField('aff_name', ___('Affiliate'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($userUrl->userUrl('{aff_id}'), '_blank'));
        
        return $grid;
    }
    public function createLeadsGrid()
    {
        $ds = new Am_Query($this->getDi()->affLeadTable);
        $ds->leftJoin('?_user', 'a', 't.aff_id=a.user_id');
        $ds->addField('CONCAT(a.name_f, \' \', a.name_l)', 'aff_name');

        $ds->leftJoin('?_aff_banner', 'b', 't.banner_id=b.banner_id');
        $ds->addField('b.title', 'banner');
        
        $ds->leftJoin('?_user', 'u', 'u.user_id=t.user_id');
        $ds->addField('CONCAT(u.name_f, \' \',u.name_l)', 'user_name')
            ->addField('u.email', 'user_email');
        
        $grid = new Am_Grid_ReadOnly('_affclicks', ___("Leads"), $ds, $this->_request, $this->view);
        $grid->setPermissionId('affiliates');
        
        $userUrl = new Am_View_Helper_UserUrl();
        $grid->addField('aff_name', ___('Affiliate'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($userUrl->userUrl('{aff_id}'), '_blank'));
        $grid->addField('user_name', ___('User'))
            ->addDecorator(new Am_Grid_Field_Decorator_Link($userUrl->userUrl('{user_id}'), '_blank'));
        $grid->addField('banner', ___('Banner'));
        $grid->addField('time', ___('Time'))->setFormatFunction('amDateTime');
        $grid->addField('first_visited', ___('First visited'))->setFormatFunction('amDateTime');
        return $grid;
    }
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('affiliates');
    }
}

