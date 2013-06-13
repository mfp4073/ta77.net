<?php 
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin Payments
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class Am_Grid_Filter_Payments extends Am_Grid_Filter_Abstract
{
    protected $dateField = 'dattm';
    public function isFiltered()
    {
        foreach ((array)$this->vars['filter'] as $v)
            if ($v) return true;
    }
    public function setDateField($dateField)
    {
        $this->dateField = $dateField;
    }
    protected function applyFilter()
    {
        class_exists('Am_Form', true);
        $filter = (array)$this->vars['filter'];
        $q = $this->grid->getDataSource();
        $dateField = $this->dateField;
        /* @var $q Am_Query */
        if ($filter['dat1']) 
            $q->addWhere("t.$dateField >= ?", Am_Form_Element_Date::createFromFormat(null, $filter['dat1'])->format('Y-m-d 00:00:00'));
        if ($filter['dat2']) 
            $q->addWhere("t.$dateField <= ?", Am_Form_Element_Date::createFromFormat(null, $filter['dat2'])->format('Y-m-d 23:59:59'));
        if (@$filter['text'])
            switch (@$filter['type'])
            {
                case 'invoice':
                    $q->leftJoin('?_invoice', 'i', 't.invoice_id=i.invoice_id');
                    $q->addWhere('i.invoice_id=? OR i.public_id=?', $filter['text'], $filter['text']);
                    break;
                case 'login':
                    $q->addWhere('login=?', $filter['text']);
                    break;
                case 'receipt':
                    if ($q->getTableName() == '?_invoice') {
                        $q->leftJoin('?_invoice_payment', 'p');
                    }
                    $q->addWhere('receipt_id LIKE ?', '%'.$filter['text'].'%');
                    break;
                case 'coupon':
                    $q->leftJoin('?_invoice', 'i', 't.invoice_id=i.invoice_id');
                    $q->leftJoin('?_coupon', 'c', 'i.coupon_id=c.coupon_id');
                    $q->addWhere('code=?', $filter['text']);
                    break;
            }
    }
    public function renderInputs()
    {
        $filter = (array)$this->vars['filter'];
        $filter['dat1'] = Am_Controller::escape(@$filter['dat1']);
        $filter['dat2'] = Am_Controller::escape(@$filter['dat2']);
        $filter['text'] = Am_Controller::escape(@$filter['text']);
        
        $options = Am_Controller::renderOptions(array(
            '' => '***', 
            'invoice' => ___('Invoice'),
            'receipt' => ___('Payment Receipt'),
            'login' => ___('Username'),
            'coupon' => ___('Coupon Code')
            ), @$filter['type']);
    
        $start = ___("Start Date");
        $end   = ___("End Date");
        $tfilter = ___("Filter");
        $prefix = $this->grid->getId();
        return <<<CUT
<b>$start</b>        
<input type="text" name="{$prefix}_filter[dat1]" class='datepicker' value="{$filter['dat1']}" />
<b>$end</b>        
<input type="text" name="{$prefix}_filter[dat2]" class='datepicker' value="{$filter['dat2']}" />
<b>$tfilter</b>        
<input type="text" name="{$prefix}_filter[text]" value="{$filter['text']}" />
<select name="{$prefix}_filter[type]">
$options
</select>
CUT;
    }
    
    public function renderStatic()
    {
        return <<<CUT
<script type="text/javascript">
$(function(){
    $(".grid-wrap").ajaxComplete(function(){
        $('input.datepicker').datepicker({
                defaultDate: window.uiDefaultDate,
                dateFormat:window.uiDateFormat,
                changeMonth: true,
                changeYear: true
        }).datepicker("refresh");
    });
});
</script>
CUT;
    }
}

class Am_Grid_Filter_Invoices extends Am_Grid_Filter_Payments {

    protected $dateField = 'tm_added';

    public function renderInputs()
    {
        return parent::renderInputs() . '<br />' . $this->renderDontShowPending();
    }

    public function renderDontShowPending()
    {
        $filter = (array)$this->vars['filter'];
        return sprintf('<label>
                <input type="hidden" name="%s_filter[dont_show_pending]" value="0" />
                <input type="checkbox" name="%s_filter[dont_show_pending]" value="1" %s /> %s</label>',
                $this->grid->getId(), $this->grid->getId(),
                (@$this->vars['filter']['dont_show_pending'] == 1 ? 'checked' : ''),
                Am_Controller::escape(___('do not show pending invoices'))
            );
    }

    protected function applyFilter()
    {
        parent::applyFilter();
        $filter = (array)$this->vars['filter'];
        $q = $this->grid->getDataSource();
        if (@$filter['dont_show_pending'])
           $q->addWhere('t.status<>?', Invoice::PENDING);
    }
}

class AdminPaymentsController extends Am_Controller_Pages
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_payment');
    }
    public function initPages()
    {
        $this->addPage(array($this, 'createPaymentsPage'), 'index', ___('Payment'));
        $this->addPage(array($this, 'createInvoicesPage'), 'invoices', ___('Invoice'));
        if($this->getDi()->config->get('manually_approve_invoice'))
            $this->addPage(array($this, 'createInvoicesPage'), 'not-approved', ___('Not Approved'));
    }
    function createPaymentsPage()
    {
        $query = new Am_Query($this->getDi()->invoicePaymentTable);
        $query->leftJoin('?_user', 'm', 'm.user_id=t.user_id')
            ->addField("(SELECT GROUP_CONCAT(item_title SEPARATOR ', ') FROM ?_invoice_item WHERE invoice_id=t.invoice_id)", 'items')
            ->addField('m.login', 'login')
            ->addField('m.email', 'email')
            ->addField('m.street', 'street')
            ->addField('m.city', 'city')
            ->addField('m.state', 'state')
            ->addField('m.country', 'country')
            ->addField('m.phone', 'phone')
            ->addField('m.zip', 'zip')
            ->addField("concat(m.name_f,' ',m.name_l)", 'name');
        $query->setOrder("invoice_payment_id", "desc");
        
        $grid = new Am_Grid_Editable('_payment', ___("Payments"), $query, $this->_request, $this->view);
        $grid->actionsClear();
        $grid->addField(new Am_Grid_Field_Date('dattm', ___('Date/Time')));
        
        $grid->addField('invoice_id', ___('Invoice'))->addDecorator(
            new Am_Grid_Field_Decorator_Link(
                'admin-user-payments/index/user_id/{user_id}#invoice-{invoice_id}', '_blank')
        );
        $grid->addField('receipt_id', ___('Receipt'));
        $grid->addField('paysys_id', ___('Payment System'));
        $grid->addField('amount', ___('Amount'))->setGetFunction(array($this, 'getAmount'));
        $grid->addField('tax', ___('Tax'))->setGetFunction(array($this, 'getTax'));
        $grid->addField('items', ___('Items'));
        $grid->addField('login', ___('Username'), false)->addDecorator(
            new Am_Grid_Field_Decorator_Link(
                'admin-users?_u_a=edit&_u_b={THIS_URL}&_u_id={user_id}', '_blank')
        );
        $grid->addField('name', ___('Name'), false);
        $grid->setFilter(new Am_Grid_Filter_Payments);
        
        $action = new Am_Grid_Action_Export();
        $action->addField(new Am_Grid_Field('dattm', ___('Date Time')))
                ->addField(new Am_Grid_Field('receipt_id', ___('Receipt')))
                ->addField(new Am_Grid_Field('paysys_id', ___('Payment System'))) 
                ->addField(new Am_Grid_Field('amount', ___('Amount')))
                ->addField(new Am_Grid_Field('tax', ___('Tax')))
                ->addField(new Am_Grid_Field('login', ___('Username')))
                ->addField(new Am_Grid_Field('name', ___('Name')))
                ->addField(new Am_Grid_Field('email', ___('Email')))
                ->addField(new Am_Grid_Field('street', ___('Street')))
                ->addField(new Am_Grid_Field('city', ___('City')))
                ->addField(new Am_Grid_Field('state', ___('State')))
                ->addField(new Am_Grid_Field('country', ___('Country')))
                ->addField(new Am_Grid_Field('phone', ___('Phone')))
                ->addField(new Am_Grid_Field('zip', ___('Zip Code')))
                ->addField(new Am_Grid_Field('items', ___('Items')))
                ->addField(new Am_Grid_Field('invoice_id', ___('Invoice')))
            ;
        $grid->actionAdd($action);
        
        return $grid;
    }
    
    function getAmount(InvoicePayment $p)
    {
        return Am_Currency::render($p->amount, $p->currency);
    }
    
    function getTax(InvoicePayment $p)
    {
        return Am_Currency::render($p->tax, $p->currency);
    }
    
    function _getInvoiceNum(Invoice $invoice)
    {
        return $invoice->invoice_id . '/' . $invoice->public_id;
    }
    
    function createInvoicesPage($page)
    {
        $query = new Am_Query($this->getDi()->invoiceTable);
        if($page =='not-approved') $query->addWhere('is_confirmed<1');
        $query->leftJoin('?_user', 'm', 'm.user_id=t.user_id')
            ->addField("(SELECT GROUP_CONCAT(item_title SEPARATOR ', ') FROM ?_invoice_item WHERE invoice_id=t.invoice_id)", 'items')
            ->addField('m.login', 'login')
            ->addField('m.email', 'email')
            ->addField('m.street', 'street')
            ->addField('m.city', 'city')
            ->addField('m.state', 'state')
            ->addField('m.country', 'country')
            ->addField('m.phone', 'phone')
            ->addField('m.zip', 'zip')
            ->addField("concat(m.name_f,' ',m.name_l)", 'name');
        $query->setOrder("invoice_id", "desc");
        
        $grid = new Am_Grid_Editable('_invoice', ___("Invoices"), $query, $this->_request, $this->view);
        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_Delete());
        $grid->addField(new Am_Grid_Field_Date('tm_added', ___('Added')));
        
        $grid->addField('invoice_id', ___('Invoice'))->setGetFunction(array($this, '_getInvoiceNum'))->addDecorator(
            new Am_Grid_Field_Decorator_Link(
                'admin-user-payments/index/user_id/{user_id}#invoice-{invoice_id}', '_blank')
        );
        $grid->addField('status', ___('Status'))->setRenderFunction(array($this, 'renderInvoiceStatus'));
        $grid->addField('paysys_id', ___('Payment System'));
        $grid->addField('_total', ___('Total'))->setGetFunction(array($this, 'getInvoiceTotal'));
        $grid->addField('items', ___('Items'));
        $grid->addField('login', ___('Username'), false)->addDecorator(
            new Am_Grid_Field_Decorator_Link(
                'admin-users?_u_a=edit&_u_b={THIS_URL}&_u_id={user_id}', '_blank')
        );
        $grid->addField('name', ___('Name'), false);
        $filter = new Am_Grid_Filter_Invoices();
        $grid->setFilter($filter);
        
        $action = new Am_Grid_Action_Export();
        $action->addField(new Am_Grid_Field('tm_started', ___('Date Time')))
                ->addField(new Am_Grid_Field('invoice_id', ___('Invoice').'#'))
                ->addField(new Am_Grid_Field('paysys_id', ___('Payment System'))) 
                ->addField(new Am_Grid_Field('first_total', ___('First Total')))
                ->addField(new Am_Grid_Field('first_tax', ___('First Tax')))
                ->addField(new Am_Grid_Field('email', ___('Email')))
                ->addField(new Am_Grid_Field('login', ___('Username')))
                ->addField(new Am_Grid_Field('name', ___('Name')))
                ->addField(new Am_Grid_Field('street', ___('Street')))
                ->addField(new Am_Grid_Field('city', ___('City')))
                ->addField(new Am_Grid_Field('state', ___('State')))
                ->addField(new Am_Grid_Field('country', ___('Country')))
                ->addField(new Am_Grid_Field('phone', ___('Phone')))
                ->addField(new Am_Grid_Field('zip', ___('Zip Code')))
                ->addField(new Am_Grid_Field('item_title', ___('Product Title')));
        $action->setGetDataSourceFunc(array($this, 'getExportDs'));
        $grid->actionAdd($action);
        if($this->getDi()->config->get('manually_approve_invoice'))
            $grid->actionAdd(new Am_Grid_Action_Group_Callback('approve', ___("Approve"), array($this, 'approveInvoice')));
        
        
        return $grid;
    }

    public function getExportDs(Am_Query $ds)
    {
        return $ds->leftJoin('?_invoice_item', 'ii', 'ii.invoice_id=t.invoice_id')
                    ->addField('ii.item_title', 'item_title');
    }
    
    public function getInvoiceTotal(Invoice $invoice)
    {
        return $invoice->getTerms();
    } 
    
    public function renderInvoiceStatus(Invoice $invoice)
    {
        return '<td>'.$invoice->getStatusTextColor().'</td>';
    }
    
    public function approveInvoice($id, Invoice $invoice){
        $invoice->approve();
    }
}
