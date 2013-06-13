<?php 

/*
*
*
*    Author: Alex Scott
*    Email: alex@cgi-central.net
*    Web: http://www.cgi-central.net
*    Details: Coupons management
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class Am_Form_Admin_CouponBatch extends Am_Form_Admin {

    const ACCESSIBILITY_ALL = 'all';
    const ACCESSIBILITY_RECURRING = 'recurring';
    const ACCESSIBILITY_NONE = 'none';

    /** @var CouponBatch */
    protected $record;

    function __construct($id, $record)
    {
        $this->record = $record;
        parent::__construct($id);
    }

    function init() {
        $this->addDataSource(new HTML_QuickForm2_DataSource_Array(array(
            '_count' => 1,
            'use_count' => 10,
            'discount' => 10,
            'discount_type' => '%',
            'user_use_count' => 1,
            '_code_len' => 8,
        )));
        
        if (!$this->record->isLoaded()) 
        {
            $this->addElement('text', '_count', array('size' => 20))
                ->setLabel(___("Coupons Count\nhow many coupons need to be generated"))
                ->addRule('gt', 'Should be greater than 0', 0);
        }
        $this->addElement('text', 'use_count', array('size' => 20))
            ->setLabel(___("Coupons Usage Count\n".
                "how many times coupon can be used"));
        $discountGroup = $this->addElement('group')
            ->setLabel(array('Discount'));
        $discountGroup->addElement('text', 'discount', array('size' => 5))
                ->addRule('gt', 'must be greater than 0', 0);
        $discountGroup->addElement('select', 'discount_type')
            ->loadOptions(array(
                Coupon::DISCOUNT_PERCENT => '%',
                Coupon::DISCOUNT_NUMBER  => Am_Currency::getDefault()
            ));

        $this->addElement('textarea', 'comment')
            ->setLabel(___("Comment\nfor admin reference"));

        /// advanced settings
        
        $fs = $this->addAdvFieldset('advanced')->setLabel(___('Advanced Settings'));
        if (!$this->record->isLoaded()) 
        {
            $fs->addElement('text', '_code_len', array('size' => 20))
                ->setLabel(array(___("Code Length\ngenerated coupon code length\nbetween 5 and 32")))
                ->addRule('gt', 'Should be greater than 4', 4)
                ->addRule('lt', 'Should be less then 33', 33);
        }
        
        $fs->addElement('text', 'user_use_count', array('size' => 20))
            ->setLabel(___("User Coupon Usage Count\nhow many times a coupon code can be used by customer"));
            
        $dateGroup = $fs->addElement('group')
            ->setLabel(___("Dates\ndate range when coupon can be used"));
        $dateGroup->addCheckbox('_date-enable', array('class'=>'enable_date'));
        $begin = $dateGroup->addDate('begin_date');
        $expire = $dateGroup->addDate('expire_date');

        $fs->addElement('advcheckbox', 'is_recurring')
            ->setLabel(___("Apply to recurring?\n".
                "apply coupon discount to recurring rebills?"));

        $fs->addElement('advcheckbox', 'is_disabled')
            ->setLabel(___("Is Disabled?\n".
                "If you disable this coupons batch, it will\n".
                "not be available for new purchases.\n".
                "Existing invoices are not affected.\n"
                ));


        $product_categories = array();
        foreach (Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions() as $id => $title) {
            $product_categories['CATEGORY-'. $id] = $title;
        }

        $fs->addElement('select', 'product_ids', 
                array('multiple' =>1, 
                      'class'=>'magicselect', ))
            ->loadOptions(array(
                ___('Products') => Am_Di::getInstance()->productTable->getOptions(),
                ___('Product Categories') => $product_categories))
            ->setLabel(___("Products\n".
               "coupons can be used with selected products only.\n".
               "if nothing selected, coupon can be used with any product"));
        
        
        $jsCode = <<<CUT
$(".enable_date").prop("checked", $("input[name=expire_date]").val() ? "checked" : "");   
$(".enable_date").live("change", function(){
    var dates = $(this).parent().find("input[type=text]");
    dates.prop('disabled', $(this).prop("checked") ? '' : 'disabled');
}).trigger("change");
CUT;
        
        $fs->addScript('script')->setScript($jsCode);

    }
}

class Am_Form_Admin_Coupon extends Am_Form_Admin 
{
    function init() {
        $this->addElement('hidden', 'batch_id');
        $this->addElement('text', 'code', array('size' => 20))
            ->setLabel('Code');
    }
}

class Am_Grid_Filter_Coupon extends Am_Grid_Filter_Abstract
{
    public function getTitle()
    {
        return ___("Filter By Coupon#");
    }

    protected function applyFilter()
    {
        if ($this->isFiltered())
        {
            $q = $this->grid->getDataSource();
            /* @var $q Am_Query */
            $q->leftJoin('?_coupon', 'cc')
              ->addWhere('cc.code=?', $this->vars['filter']);
        }
    }

    public function renderInputs()
    {
        return $this->renderInputText();
    }
}

class AdminCouponsController extends Am_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_coupon');
    }
    public function createGrid()
    {
        $ds = new Am_Query($this->getDi()->couponBatchTable);
        $ds->addField('COUNT(c.coupon_id) AS coupons_count');
        $ds->addField('SUM(c.used_count) AS used_count');
        $ds->leftJoin('?_coupon', 'c', 't.batch_id = c.batch_id');
        
        $ds->setOrder('batch_id', 'desc');
        $grid = new Am_Grid_Editable('_coupon', ___("Coupons Batches"), $ds, $this->_request, $this->view);
        $grid->addField('batch_id', ___('Batch ID'), true, '', null, '5%');
        $grid->addField(new Am_Grid_Field_Date('begin_date', ___('Begin Date')))->setFormatDate();
        $grid->addField(new Am_Grid_Field_Date('expire_date', ___('Expire Date')))->setFormatDate();
        $grid->addField(new Am_Grid_Field_IsDisabled());
        $grid->addField('is_recurring', ___('Recurring'), true, 'center', null, '1%');
        $grid->addField('discount', ___('Discount'), true, '', array($this, 'renderDiscount'), '5%');
        $grid->addField('product_ids', ___('Products'), false, '', array($this, 'renderProducts'), '25%');
        $grid->addField('comment', ___('Comment'), true, '', null, '15%');
        $grid->addField('used_count', ___('Used Count'), true, 'center', array($this, 'renderUsedCount'), '5%');
        $grid->addField('coupons_count', ___('Coupons Count'), true, 'center', null, '5%');
        $grid->setForm(array($this, 'createForm'));
        $grid->actionGet('edit')->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_Url("view", ___("View Coupons"), "javascript:amOpenCoupons(__ID__)"))
            ->setAttribute("class", "coupons-link");
        $grid->actionAdd(new Am_Grid_Action_Url('export', ___("Export"), REL_ROOT_URL . "/admin-coupons/export/id/__ID__"))->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('comment'));
        $grid->actionAdd(new Am_Grid_Action_LiveCheckbox('is_recurring'));
        $grid->setFormValueCallback('product_ids', array('RECORD', 'unserializeList'), array('RECORD', 'serializeList'));
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, array($this, 'beforeSave'));
        $grid->addCallback(Am_Grid_Editable::CB_AFTER_INSERT, array($this, 'afterInsert'));
        $grid->setFilter(new Am_Grid_Filter_Coupon);
        return $grid;
    }
    
    public function init()
    {
        parent::init();
        $this->view->placeholder('after-content')->append('<div id="coupons" style="display:none"></div>');
        $this->view->headScript()->appendScript(<<<CUT
function amOpenCoupons(id)
{
    var url = window.rootUrl + '/admin-coupons/detail/id/'+id 
                + '?_detail_filter='
                + escape($("input[name='_coupon_filter']").val());
    $("#coupons").load(url, 
        function(){
            $("#coupons .grid-wrap").ngrid();
            $("#coupons").dialog({
                autoOpen: true
                ,width: 800
                ,height: 600
                ,closeOnEscape: true
                ,title: "Coupons"
                ,modal: true
            });
        }
    );
}
CUT
            );
    }
    function renderDiscount($record) {
        return sprintf("<td>%s&nbsp;%s</td>",
            $record->discount,
            ($record->discount_type == Coupon::DISCOUNT_PERCENT ? '%' : $this->getDi()->config->get('currency'))
        );
    }
    function renderUsedCount($record) {
        return sprintf("<td align='center'>%d<span style='color:#aaa'>/%d</span></td>",
            $record->used_count,
            $record->use_count * $record->coupons_count
        );
    }
    function renderProducts($record) {
        /* @var $record CouponBatch */
        $product_ids = $record->getOnlyApplicableProductIds();
        $category_ids = $record->getOnlyApplicableCategoryIds();

        $res = array();

        if ($product_ids) {
            $titles = $this->getDi()->productTable->getProductTitles($product_ids);
            $titles = implode(', ', $titles);
            $res[] = sprintf("<strong>%s:</strong> %s", ___('Products'), $titles);
        }

        if ($category_ids) {
            $options = $this->getDi()->productCategoryTable->getAdminSelectOptions();
            $titles = array();
            foreach ($category_ids as $id)
                $titles[] = $options[$id];

            $titles = implode(', ', $titles);
            $res[] = sprintf("<strong>%s:</strong> %s", ___('Product Categories'), $titles);
        }

        if (!$product_ids && !$category_ids) {
            $res[] = sprintf('<strong>%s</strong>', ___('All'));
        }

        return sprintf('<td>%s</td>',
            implode('; ', $res)
        );
    }
    
    function createForm() 
    {
        return new Am_Form_Admin_CouponBatch(get_class($this), $this->grid->getRecord());
    }
    function beforeSave(& $values)
    {
        if (!$values['_date-enable'])
        {
            $values['begin_date'] = $values['expire_date'] = null;
        };
    }
    public function afterInsert(array & $values, CouponBatch $record) {
        $couponsCount = intval();
        $code_len = intval($vars['_code_len']);
        $record->generateCoupons(
            (int)$values['_count'], 
            (int)$values['_code_len'], 
            (int)$values['_code_len']);
    }
    
    public function detailAction()
    {
        $id = (int)$this->getParam('id');
        if (!$id) throw new Am_Exception_InputError("Empty id passed to " . __METHOD__);
        
        $ds = new Am_Query($this->getDi()->couponTable);
        $ds->addWhere('batch_id=?d', $id);
        
        $grid = new Am_Grid_Editable('_detail', ___("Coupons"), $ds, $this->_request, $this->view);
        $grid->setPermissionId('grid_coupon');
        $grid->actionsClear();
        $grid->addField('code', ___('Code'), true, null);
        $grid->addGridField(new Am_Grid_Field_Expandable('used_count', 'Used For', false))
            ->setGetFunction(array($this, 'getUsedCount'))
            ->setPlaceholder(array($this, 'getPlaceholder'))
            ->setEscape(false);
        $grid->setFilter(new Am_Grid_Filter_Text(___("Filter by Code"), array('code' => 'LIKE')));
        $grid->setForm('Am_Form_Admin_Coupon');
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('code'));
        $grid->isAjax(false);
        $response = $grid->run();
        $response->sendHeaders();
        $response->sendResponse();
    }

    public function getUsedCount($obj)
    {
        $invoices = Am_Di::getInstance()->invoiceTable->findByCouponId($obj->coupon_id);
        if (!$invoices) return '';

        $ret = array();
        $count = $amount = 0;
        $out = "<strong>Payments with this coupon:</strong>";
        $out .= '<table class="grid">';
        $out .= "<tr><th>User</th><th>Date/Time</th><th>Receipt#</th><th>Amount</th></tr>";
        foreach ($invoices as $invoice) {
            foreach ($invoice->getPaymentRecords() as $payment) {
                $user = Am_Di::getInstance()->userTable->load($payment->user_id);
                $ret[] = sprintf("<tr><td>%s (%s %s)</td><td>%s</td><td>%s</td><td>%.2f</td></tr>",
                    $this->getDi()->view->escape($user->login),
                    $this->getDi()->view->escape($user->name_f),
                    $this->getDi()->view->escape($user->name_l),
                    amDatetime($payment->dattm),
                    $this->getDi()->view->escape($payment->receipt_id),
                    $this->getDi()->view->escape($payment->amount)
                    );
            }
        }
        $out .= implode("\n", $ret);
        $out .= "</table>";

        return $out;
    }

    public function getPlaceholder($val, $obj)
    {
        return sprintf ('Coupon used for %d payments',
            $obj->used_count
        );
    }

    public function exportAction()
    {
        $id = (int)$this->getParam('id');
        if (!$id) throw new Am_Exception_InputError("Empty id passed to " . __METHOD__);
        
        header('Cache-Control: maxage=3600');
        header('Pragma: public');
        header("Content-type: application/csv");
        $dat = date('YmdHis');
        header("Content-Disposition: attachment; filename=amember_coupons-$id-$dat.csv");

        foreach($this->getDi()->couponTable->findBy(array('batch_id' => $id)) as $c)
            echo $c->code."\r\n";
    }
}