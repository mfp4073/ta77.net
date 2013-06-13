<?php 
class Am_Form_Admin_Product extends Am_Form_Admin {
    protected $plans = array();

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_product');
    }
    
    public function __construct($plans)
    {
        $this->plans = (array)$plans;
        parent::__construct('admin-product');
    }

    function addBillingPlans()
    {
        $plans = $this->plans;
        if (!$plans)
            $plans[] = array(
                'title' => ___('Default Billing Plan'),
                'plan_id' => 0,
            );
        $plans[] = array(
            'title' => 'TEMPLATE',
            'plan_id'    => 'TPL',
        );
        foreach ($plans as $plan)
        {
            $fieldSet = $this->addElement('fieldset', '', array('id'=>'plan-'.$plan['plan_id'], 'class'=>'billing-plan'))
                    ->setLabel('<span class="plan-title-text">' . ($plan['title'] ? $plan['title'] : "title - click to edit") .  '</span>' .
                        sprintf(' <input type="text" class="plan-title-edit" name="_plan[%s][title]" value="%s" size="30" style="display: none" />',
                        $plan['plan_id'], Am_Controller::escape($plan['title'])));
            $this->addBillingPlanElements($fieldSet, $plan['plan_id']);
        }
    }
    function addBillingPlanElements(HTML_QuickForm2_Container $fieldSet, $plan)
    {
        $prefix = '_plan['.$plan.'][';
        $suffix = ']';
        $firstPrice = $fieldSet->addElement('text', $prefix.'first_price'.$suffix)
                ->setLabel(___("First Price\n".
                    "price of first period of subscription"));
        $firstPrice->addRule('gte', ___('must be equal or greather than 0'), 0.0)
                    ->addRule('regex', ___('must be a number in format 99 or 99.99'), '/^(\d+(\.\d+)?|)$/');

        $firstPeriod = $fieldSet->addElement('period', $prefix.'first_period'.$suffix)
                ->setLabel(___('First Period'));

        $group = $fieldSet->addGroup()->setLabel(
            ___("Rebill Times\n".
            "This is the number of payments which\n". 
            "will occur at the Second Price"));

        $sel = $group->addElement('select', $prefix.'_rebill_times'.$suffix)->setId('s_rebill_times');
        $sel->addOption(___('No more charges'), 0);
        $sel->addOption(___('Charge Second Price Once'), 1);
        $sel->addOption(___('Charge Second Price x Times'), 'x');
        $sel->addOption(___('Rebill Second Price until cancelled'), IProduct::RECURRING_REBILLS);
        $txt = $group->addElement('text', $prefix.'rebill_times'.$suffix, array('size'=>5, 'maxlength'=>6))->setId('t_rebill_times');

        $secondPrice = $fieldSet->addElement('text', $prefix.'second_price'.$suffix)
                ->setLabel(
                    ___("Second Price\n".
                    "price that must be billed for second and\n".
                    "the following periods of subscription"));
        $secondPrice->addRule('gte', ___('must be equal or greather than 0.0'), 0.0)
                ->addRule('regex', ___('must be a number in format 99 or 99.99'), '/^\d+(\.\d+)?$/');

        $secondPeriod = $fieldSet->addElement('period', $prefix.'second_period'.$suffix)
                ->setLabel(___('Second Period'));

        $secondPeriod = $fieldSet->addElement('text', $prefix.'terms'.$suffix, array('size' => 40, 'class'=>'translate'))
                ->setLabel(___("Terms Text\nautomatically calculated if empty"));

        $fs = $fieldSet->addGroup()
            ->setLabel(___("Quantity\ndefault - 1, normally you do not need to change it\nFirst and Second Price is the total for specified qty"));
        
        $fs->addInteger($prefix.'qty'.$suffix, array('placeholder' => 1,));
        $fs->addCheckbox($prefix.'variable_qty'.$suffix, array('class' => 'variable_qty'))
            ->setContent('allow user to change quantity');
        
        foreach (Am_Di::getInstance()->billingPlanTable->customFields()->getAll() as $k => $f)
        {
            $el = $f->addToQf2($fieldSet);
            $el->setName($prefix.$el->getName().$suffix);
        }
    }
    
    function checkBillingPlanExists(array $vals)
    {
        foreach ($vals['_plan'] as $k => $v)
        {
            if ($k === 'TPL') continue;
            if (strlen($v['first_price']) && strlen($v['first_period']))
                return true;
        }
        return false;
    }

    function init() {
        $this->addElement('hidden', 'product_id');
        
        $this->addRule('callback', ___('At least one billing plan must be added'), array($this, 'checkBillingPlanExists'));
        
        /* General Settings */
        $fieldSet = $this->addElement('fieldset', 'general')
                ->setLabel(___('General Settings'));

        $fieldSet->addElement('text', 'title', array('size' => 40, 'class'=>'translate'))
                ->setLabel(___('Title'))
                ->addRule('required');

        $fieldSet->addElement('textarea', 'description', array('class'=>'translate'))
                ->setLabel(___(
                    "Description\n".
                    "displayed to visitors on order page below the title"))
                ->setAttribute('cols', 40)->setAttribute('rows', 2);

        $fieldSet->addElement('textarea', 'comment')
                ->setLabel(___("Comment\nfor admin reference"))
                ->setAttribute('cols', 40)->setAttribute('rows', 2);

        $gr = $fieldSet->addGroup()->setSeparator(' ')
                ->setLabel(___('Product Categories'));
        $gr->addMagicSelect('_categories')
            ->loadOptions(Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions());
        $gr->addStatic()->setContent('<a href="'.Am_Controller::escape(REL_ROOT_URL).'/admin-product-categories">'.___('Edit Categories').'</a>');
        
        /* Billing Settings */
        $fieldSet = $this->addElement('fieldset', 'billing')
                ->setLabel(___('Billing'));

        $fieldSet->addElement('advcheckbox', 'tax_group', array('value' => IProduct::ALL_TAX))
               ->setLabel(___('Apply Tax?'));

        $fieldSet->addElement('select', 'currency')
                ->setLabel(array(___("Currency"), ___("you can choose from list of currencies supported by paysystems")))
                ->loadOptions(Am_Currency::getSupportedCurrencies('ru_RU'));

        $this->addBillingPlans();

        /* Advanced Settings */
//        $fieldSet = $this->addElement('fieldset', 'advanced')
//                ->setId('advanced')
//                ->setLabel(___('Advanced'));
        
//        $fieldSet->addElement('text', 'trial_group', array('size' => 20))
//                ->setLabel(___("Trial Group\n".
//                'If this field is filled-in, user will be unable to order the product
//                    twice. It is extermelly useful for any trial product. This field
//                    can have different values for different products, then "trial history"
//                    will be separate for these groups of products.
//                    If your site offers only one level of membership,
//                    just enter "1" to this field. '));

        /* Product availability */
        $fieldSet = $this->addElement('fieldset', 'avail')
                ->setLabel(___('Product Availability'));
        
        $this->insertAvailablilityFields($fieldSet);

        $sdGroup = $fieldSet->addGroup()->setLabel(___(
            "Start Date Calculation\n". 
            "rules for subscription start date calculation.\n".
            "MAX date from alternatives will be chosen.\n".
            "This settings has no effect for recurring subscriptions"));
        
        $sd = $sdGroup->addSelect('start_date', 
            array('multiple'=>'mutliple', 'id'=>'start-date-edit',));
        $sd->loadOptions(array(
            Product::SD_PRODUCT => ___('Last existing subscription date of this product'),
            Product::SD_GROUP   => ___('Last expiration date in the renewal group'),
            Product::SD_FIXED    => ___('Fixed date'),
            Product::SD_PAYMENT => ___('Payment date'),
            ___('Nearest') => array(
                Product::SD_WEEKDAY_SUN => ___('Nearest Sunday'),
                Product::SD_WEEKDAY_MON => ___('Nearest Monday'),
                Product::SD_WEEKDAY_TUE => ___('Nearest Tuesday'),
                Product::SD_WEEKDAY_WED => ___('Nearest Wednesday'),
                Product::SD_WEEKDAY_THU => ___('Nearest Thursday'),
                Product::SD_WEEKDAY_FRI => ___('Nearest Friday'),
                Product::SD_WEEKDAY_SAT => ___('Nearest Saturday'),
                Product::SD_MONTH_1 => ___('Nearest 1st Day of Month'),
                Product::SD_MONTH_15 =>  ___('Nearest 15th Day of Month')
            )
        ));
        $sdGroup->addDate('start_date_fixed', array('style'=>'display:none; font-size: xx-small'));

        $rgroups = Am_Di::getInstance()->productTable->getRenewalGroups();
        
        
        if($rgroups){
            $roptions = array_filter($rgroups);
            $roptions [''] = ___('-- Please Select --');
        }
        else 
            $roptions = array(''=>'');
        
        $fieldSet->addSelect('renewal_group', array(), 
            array('intrinsic_validation' => false, 'options' => $roptions))
                ->setLabel('Renewal Group');
        
        $fieldSet->addElement('text', 'sort_order')
                ->setLabel(___(
                    "Sorting order\n" .
                    "This is a numeric field. Products are sorted\n".
                    "according to this field value, then alphabetically"));


        /* Additional Options */
        $fieldSet = $this->addElement('fieldset', 'additional')
                ->setId('additional')
                ->setLabel(___('Additional'));

        $this->insertAdditionalFields();
    }


    function insertAvailablilityFields($fieldSet) {
        
        $fieldSet->addAdvCheckbox('is_disabled')->setLabel(___("Is Disabled?\n".
            "disable product ordering, hide it from signup and renewal forms"));
        // add require another subscription field
        
        $require_options = array(/*''  => "Don't require anything (default)"*/);
        $prevent_options = array(/*''  => "Don't prevent anything (default)"*/);
        foreach (Am_Di::getInstance()->productTable->getOptions() as $id => $title) {
            $title = Am_Controller::escape($title);
            $require_options['ACTIVE-'. $id] = ___('ACTIVE subscription for %s', '"'.$title.'"');
            $require_options['EXPIRED-'.$id] = ___('EXPIRED subscription for %s', '"'.$title.'"');
            $prevent_options['ACTIVE-'. $id] = ___('ACTIVE subscription for %s', '"'.$title.'"');
            $prevent_options['EXPIRED-'.$id] = ___('EXPIRED subscription for %s', '"'.$title.'"');
        }


        $require_group_options = array();
        $prevent_group_options = array();
        foreach (Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions() as $id => $title) {
            $title = Am_Controller::escape($title);
            $require_group_options['CATEGORY-ACTIVE-'. $id] = ___('ACTIVE subscription for group %s', '"'.$title.'"');
            $require_group_options['CATEGORY-EXPIRED-'.$id] = ___('EXPIRED subscription for group %s', '"'.$title.'"');
            $prevent_group_options['CATEGORY-ACTIVE-'. $id] = ___('ACTIVE subscription for group %s', '"'.$title.'"');
            $prevent_group_options['CATEGORY-EXPIRED-'.$id] = ___('EXPIRED subscription for group %s', '"'.$title.'"');
        }


        if (count($require_group_options)) {
            $rOptions = array(
                ___('Products') => $require_options,
                ___('Product Categories') => $require_group_options
            );
            $pOptions = array(
                ___('Products') => $prevent_options,
                ___('Product Categories') => $prevent_group_options
            );
        } else {
            $rOptions = $require_options;
            $pOptions = $prevent_options;
        }

        $fieldSet->addMagicSelect('require_other', array('multiple'=>'multiple', 'class'=>'magicselect'))
                ->setLabel(___("To order this product user must have an\n".
                "when user orders this subscription, it will be checked\n".
                "that user has one from the following subscriptions"
                ))
                ->loadOptions($rOptions);

        $fieldSet->addMagicSelect('prevent_if_other', array('multiple'=>'multiple', 'class'=>'magicselect'))
                ->setLabel(___("Disallow ordering of this product if user has\n".
                "when user orders this subscription, it will be checked\n".
                "that he has no any from the following subscriptions"
                ))
                ->loadOptions($pOptions);

    }

    function insertAdditionalFields() {
        $fieldSet = $this->getElementById('additional');
        $fields = Am_Di::getInstance()->productTable->customFields()->getAll();
        $exclude = array();
        foreach ($fields as $k => $f)
            if (!in_array($f->name, $exclude))
                $el = $f->addToQf2($fieldSet);
    }

}

class Am_Grid_Filter_Product extends Am_Grid_Filter_Abstract
{
    
    protected function applyFilter()
    {
        $query = $this->grid->getDataSource();
        if ($s = @$this->vars['filter']['text'])
        {
            $query->add(new Am_Query_Condition_Field('title', 'LIKE', '%'.$s.'%'))
                ->_or(new Am_Query_Condition_Field('product_id', '=', $s));
        }
        if ($category_id = @$this->vars['filter']['category_id'])
        {
            $query->leftJoin("?_product_product_category", "ppc");
            $query->addWhere("ppc.product_category_id=?d", $category_id);
        }

        if (@$this->vars['filter']['dont_show_disabled'])
        {
            $query->addWhere("t.is_disabled=0");
        }
    }
    public function renderInputs()
    {
        $options = array('' => '-' . ___('Filter by Category') . '-');
        $options = $options + 
            Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions(
                array(ProductCategoryTable::COUNT=>true)); 
        $options = Am_Controller::renderOptions(
            $options,
            @$this->vars['filter']['category_id']
        );
        $out = sprintf('<select onchange="this.form.submit()" name="_product_filter[category_id]">%s</select>&nbsp;'.PHP_EOL, $options);
        $this->attributes['value'] = (string)$this->vars['filter']['text'];
        $out .= $this->renderInputText('filter[text]');
        $out .= '<br />' . $this->renderShowDisabled();
        return $out;
    }

    public function renderShowDisabled()
    {
        return sprintf('<label>
                <input type="hidden" name="%s_filter[dont_show_disabled]" value="0" />
                <input type="checkbox" name="%s_filter[dont_show_disabled]" value="1" %s /> %s</label>',
                $this->grid->getId(), $this->grid->getId(),
                (@$this->vars['filter']['dont_show_disabled'] == 1 ? 'checked' : ''),
                Am_Controller::escape(___('do not show disabled products'))
            );
    }
}

class Am_Grid_Action_Group_ProductAssignCategory extends Am_Grid_Action_Group_Abstract
{
    protected $needConfirmation = true;
    protected $remove = false;
    public function __construct($removeCategory = false)
    {
        $this->remove = (bool)$removeCategory;
        parent::__construct(
            !$removeCategory ? "product-assign-category":"product-remove-category", 
            !$removeCategory ? ___("Assign Category"):___("Remove Category")
            );
    }
    
    public function renderConfirmationForm($btn = "Yes, assign", $page = null, $addHtml = null) 
    {
        $select = sprintf('
            <select name="%s_category_id">
            %s
            </select><br /><br />'.PHP_EOL,
            $this->grid->getId(),
            Am_Controller::renderOptions(Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions())
            );
        return parent::renderConfirmationForm($this->remove ? ___("Yes, remove category") :  ___("Yes, assign category"), null, $select);
    } 
    
    /**
     * @param int $id
     * @param Product $record
     */
    public function handleRecord($id, $record)
    {
        $category_id = $this->grid->getRequest()->getInt('category_id');
        if (!$category_id) throw new Am_Exception_InternalError("category_id empty");
        $categories = $record->getCategories();
        if ($this->remove)
        {
            if (!in_array($category_id, $categories)) return;
            foreach ($categories as $k => $id)
                if ($id == $category_id) unset($categories[$k]);
        } else {
            if (in_array($category_id, $categories)) return;
            $categories[] = $category_id;
        }
        $record->setCategories($categories);
    }
}

class Am_Grid_Action_CopyProduct extends Am_Grid_Action_Abstract
{
    protected $id = 'copy';
    protected $privilege = 'insert';
    public function run()
    {
        $record = $this->grid->getRecord();

        $vars = $record->toRow();
        unset($vars['product_id']);
        $vars['title'] = ___('Copy of') . ' ' . $record->title;

        $back = @$_SERVER['HTTP_X_REQUESTED_WITH'];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $controller = new AdminProductsController_Copy(new Am_Request(), new Zend_Controller_Response_Http(),
            array('di' => Am_Di::getInstance()));

        $controller->valuesToForm($vars, $record);
        $plan = array();
        foreach ($vars['_plan'] as $p) {
            $id = is_null($id) ? 0 : time() . rand(100, 999);
            $p['plan_id'] = $id;
            $plan[$id] = $p;
        }
        $vars['_plan'] = $plan;
        $controller->setPlan($plan);

        $request = new Am_Request($vars + array($this->grid->getId() . '_a' => 'insert',
            $this->grid->getId() . '_b' => $this->grid->getBackUrl()), Am_Request::METHOD_POST);

        $controller->setRequest($request);
        

        $request->setModuleName('default')
            ->setControllerName('admin-products')
            ->setActionName('index')
            ->setDispatched(true);

        $controller->dispatch('indexAction');
        $response = $controller->getResponse();
        $response->sendResponse();
        $_SERVER['HTTP_X_REQUESTED_WITH'] = $back;
    }
}


class AdminProductsController extends Am_Controller_Grid
{
    
    public function preDispatch()
    {
        parent::preDispatch();
        $this->getDi()->billingPlanTable->toggleProductCache(false);
        $this->getDi()->productTable->toggleCache(false);
    }
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_product');
    }
    
    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->productTable);
        $ds->setOrderRaw("0+IFNULL(sort_order,0),title");
        $grid = new Am_Grid_Editable('_product', ___("Products"), $ds, $this->_request, $this->view);
        $grid->setRecordTitle(___('Product'));
        $grid->actionAdd(new Am_Grid_Action_Group_ProductAssignCategory(false));
        $grid->actionAdd(new Am_Grid_Action_Group_ProductAssignCategory(true));
        $grid->actionAdd(new Am_Grid_Action_Group_Delete);
        $grid->addGridField(new Am_Grid_Field('product_id', '#', true, '', null, '5%'));
        $grid->addGridField(new Am_Grid_Field('title', ___('Title'), true, '', null, '50%'));
        $grid->addGridField(new Am_Grid_Field('pgroup', ___('Product Categories'), false))->setRenderFunction(array($this, 'renderPGroup'));
        $grid->addGridField(new Am_Grid_Field('terms', ___('Billing Terms'), false))->setRenderFunction(array($this, 'renderTerms'));
        $grid->addGridField(new Am_Grid_Field('sort_order', ___('Sort Order')));
        $grid->actionGet('edit')->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('sort_order'));
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('title'));

        $grid->setFormValueCallback('start_date', array('RECORD', 'getStartDate'),  array('RECORD', 'setStartDate'));
        $grid->setFormValueCallback('require_other', array('RECORD', 'unserializeList'),  array('RECORD', 'serializeList'));
        $grid->setFormValueCallback('prevent_if_other', array('RECORD', 'unserializeList'),  array('RECORD', 'serializeList'));
        
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_SAVE, array($this, 'afterSave'));
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, array($this, 'valuesToForm'));
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this, 'getTrAttribs'));
        
        $grid->setForm(array($this, 'createForm'));
        $grid->setFilter(new Am_Grid_Filter_Product);
        $grid->setEventId('gridProduct');
        
        $grid->actionAdd(new Am_Grid_Action_Url('categories', ___('Edit Categories'),
                REL_ROOT_URL . '/admin-product-categories'))
            ->setType(Am_Grid_Action_Abstract::NORECORD)
            ->setTarget('_top');

        $grid->actionAdd(new Am_Grid_Action_Url('upgrades', ___('Manage Product Upgrade Paths'),
                REL_ROOT_URL . '/admin-products/upgrades'))
            ->setType(Am_Grid_Action_Abstract::NORECORD)
            ->setTarget('_top');

        $grid->actionAdd(new Am_Grid_Action_CopyProduct())->setTarget('_top');

        return $grid;
    }

    public function getTrAttribs(& $ret, $record)
    {
        if ($record->is_disabled)
        {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }

    function renderPGroup(Product $p)
    {
        $res = array();
        $options = $this->getDi()->productCategoryTable->getAdminSelectOptions();
        foreach ($p->getCategories() as $pc_id)
        {
            $res[] = $options[$pc_id];
        }
        return $this->renderTd(implode(", ", $res));
    }
    
    function renderTerms(Product $record)
    {
        if (!$record->getBillingPlan(false)) return;
        $plans = $record->getBillingPlans();
        $t = array();
        foreach ($plans as $plan)
            $t[] = sprintf(count($plans) > 1 && $plan->pk() == $record->default_billing_plan_id ? '<strong>%s</strong>' : '%s',
                $this->escape($plan->getTerms()));
        $t = implode('<br />', $t);
        return $this->renderTd($t, false);
    }

    function createForm() {
        $record = $this->grid->getRecord();
        $plans = array();
        foreach ($record->getBillingPlans() as $plan)
            $plans[$plan->pk()] = $plan->toArray();
        $form = new Am_Form_Admin_Product($plans);
        return $form;
    }

    function valuesToForm(& $ret, Product $record){
        if ($record->isLoaded()) {
            $ret['_categories'] = $record->getCategories();
            $ret['_plan'] = array();
            foreach ($record->getBillingPlans() as $plan)
            {
                $arr = $plan->toArray();
                if (!empty($arr['rebill_times']))
                {
                    $arr['_rebill_times'] = $arr['rebill_times'];
                    if (!in_array($arr['rebill_times'], array(0,1,IProduct::RECURRING_REBILLS)))
                        $arr['_rebill_times'] = 'x';
                };
                foreach (array('first_period', 'second_period') as $f)
                    if (array_key_exists($f, $arr)) {
                        $arr[$f] = new Am_Period($arr[$f]);
                    }
                $ret['_plan'][$plan->pk()] = $arr;
            }
        }
    }
    
    public function afterSave(array &$values, Product $product) {
        $this->updatePlansFromRequest($product, $values, $product->getBillingPlans());
        $product->setCategories(empty($values['_categories']) ? array() : $values['_categories']);
    }
    /** @return array BillingPlan including existing, $toDelete - but existing not found in request */
    public function updatePlansFromRequest(Product $record, $values, $existing = array())
    {
        // we access "POST" directly here as there is no access to new added
        // fields from the form!
        $plans = $_POST['_plan'];
        unset($plans['TPL']);

        //we should use output of getValue to set additional fields
        //in order to value be correct
        $this->getDi()->billingPlanTable->customFields()->getAll();
        $form = new Am_Form_Admin();
        foreach ($this->getDi()->billingPlanTable->customFields()->getAll() as $f) {
           $f->addToQf2($form);
        }

        foreach($plans as $k => $plan) {
            $form->setDataSources(array(
                new HTML_QuickForm2_DataSource_Array($plan)
            ));
            $plans[$k] = array_merge($plan, $form->getValue());
        }

        foreach ($plans as $k => & $arr)
        {
            if ($arr['_rebill_times']!='x')
                $arr['rebill_times'] = $arr['_rebill_times'];
            try {
                $p = new Am_Period($arr['first_period']['c'], $arr['first_period']['u']);
                $arr['first_period'] = (string)$p;
            } catch (Am_Exception_InternalError $e) {
                unset($plans[$k]);
                continue;
            }
            try {
                $p = new Am_Period($arr['second_period']['c'], $arr['second_period']['u']);
                $arr['second_period'] = (string)$p;
            } catch (Am_Exception_InternalError $e) {
                $arr['second_period'] = '';
            }
            if (empty($arr['variable_qty']))
                $arr['variable_qty'] = 0;
            if (empty($arr['qty']))
                $arr['qty'] = 1;
        }
        foreach ($existing as $k => $plan)
            if (empty($plans[$plan->pk()]))
            {
                $plan->delete();
            } else {
                $plan->setForUpdate($plans[$plan->pk()]);
                $plan->update();
                unset($plans[$plan->pk()]);
            }
        foreach ($plans as $id => $a)
        {
            $plan = $this->getDi()->billingPlanRecord;
            $plan->setForInsert($a);
            $plan->product_id = $record->pk();
            $plan->insert();
        }
        // temp. stub
        $record->updateQuick('default_billing_plan_id', $this->getDi()->db->selectCell(
            "SELECT MIN(plan_id) FROM ?_billing_plan WHERE product_id=?d
                AND (disabled IS NULL OR disabled = 0)",
            $record->product_id));
    }
    public function upgradesAction()
    {
        $billingTableRecords = $this->getDi()->billingPlanTable->findBy();
        $productOptions = $this->getDi()->productTable->getOptions();
        $planOptions = array();
        foreach ($billingTableRecords as $bp)
        {
            if (!isset($productOptions[$bp->product_id]))
                continue;
            /* @var $bp BillingPlan */
            if (!$terms = $bp->terms)
            {
                $tt = new Am_TermsText($bp);
                $terms = $tt->getString();
            }
            $planOptions[ $bp->pk() ] = $productOptions[$bp->product_id] . '/' . $bp->title . ' (' . $terms . ')';
        }
        
        
        
        $ds = new Am_Query($this->getDi()->productUpgradeTable);
        $grid = new Am_Grid_Editable('_upgrades', ___("Product Upgrades"), $ds, $this->_request, $this->view);
        $grid->_planOptions = $planOptions;
        $grid->addField(new Am_Grid_Field_Enum('from_billing_plan_id', ___('Upgrade From')))->setTranslations($planOptions);
        $grid->addField(new Am_Grid_Field_Enum('to_billing_plan_id', ___('Upgrade To')))->setTranslations($planOptions);
        $grid->addField('surcharge', ___('Surcharge'));
        $grid->setForm(array($this, 'createUpgradesForm'));
        $grid->runWithLayout('admin/layout.phtml');
    }
    public function createUpgradesForm(Am_Grid_Editable $grid)
    {
        $form = new Am_Form_Admin;
        $options = $grid->_planOptions;
        $from = $form->addSelect('from_billing_plan_id', null, array('options'=>$options))->setLabel(___('Upgrade From'));
        $to = $form->addSelect('to_billing_plan_id', null, array('options' => $options))->setLabel(___('Upgrade To'));
        $to->addRule('neq', ___('[From] and [To] billing plans must not be equal'), $from);
        $form->addText('surcharge', array('placeholder' => '0.0'))->setLabel(___(
                "Surcharge\nto be additionally charged when customer moves [From]->[To] plan\naMember will not charge First Price on upgrade, use Surcharge instead"));
        return $form;
    }
    public function init()
    {
        parent::init();
        $this->view->headStyle()->appendStyle("
.billing-plan.collapsed .row { display: none; }
.billing-plan.collapsed .row.terms-text-row { display: block; }
.billing-plan .terms-text-row { font-decoration: italic; font-size: 120%; padding: 4px;}
#plan-TPL { display: none; }
        ");
        $this->view->headScript()->appendFile(REL_ROOT_URL . "/application/default/views/public/js/adminproduct.js");
        $this->view->headScript()->appendFile(REL_ROOT_URL . "/application/default/views/public/js/ckeditor/ckeditor.js");
        $this->getDi()->plugins_payment->loadEnabled()->getAllEnabled();
    }
}

class AdminProductsController_Copy extends AdminProductsController {
    protected $plan = array();

    function setPlan($plan)
    {
        $this->plan = $plan;
    }

    function createForm()
    {
        return new Am_Form_Admin_Product($this->plan);
    }
}