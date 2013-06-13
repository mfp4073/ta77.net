<?php

class Am_Grid_Action_RunPayout extends Am_Grid_Action_Abstract
{
    protected $type = self::NORECORD;
   
    public function run()
    {
        Am_Di::getInstance()->affCommissionTable->runPayout(sqlDate('now'));
        echo ___('Payout generated') . '.';
        echo $this->renderBackUrl();
    }
}

class Am_Grid_Action_ExportPayout extends Am_Grid_Action_Abstract
{
    protected $type = self::SINGLE;
    
    public function __construct($id = null, $title = null)
    {
        parent::__construct('export', ___('Export'));
    }

    public function run()
    {
        $payout = $this->grid->getRecord();
        /* @var $payout AffPayout */
        if ($this->grid->getCompleteRequest()->get('run'))
        {
            $m = $payout->getPayoutMethod();
            if (!$m) throw new Am_Exception_InputError("Payout method [$payout->type] is disabled or misconfigured");
            $details = new Am_Query($this->grid->getDi()->affPayoutDetailTable);
            $details->addWhere('payout_id=?d', $payout->pk());
            $response = new Zend_Controller_Response_Http;
            $m->export($payout, $details, $response);
            $response->sendResponse();
            exit();
        } else {
            $link = $this->grid->getActionUrl('export', $payout->pk()) . '&run=1';
            printf("<a href='%s' target=_blank>Download CSV File</a>", Am_Controller::escape($link));
        }
    }
}

class Am_Grid_Action_PayoutMarkPaid extends Am_Grid_Action_Group_Abstract
{
    public function doRun(array $ids)
    {
        Am_Di::getInstance()->db->query("UPDATE ?_aff_payout_detail SET is_paid=1 
            WHERE payout_detail_id IN (?a)", 
            $ids);
        echo $this->renderDone();
    }

    public function handleRecord($id, $record)
    {
    }
}
class Am_Grid_Action_PayoutMarkNotPaid extends Am_Grid_Action_Group_Abstract
{
    public function doRun(array $ids)
    {
        Am_Di::getInstance()->db->query("UPDATE ?_aff_payout_detail SET is_paid=0 
            WHERE payout_detail_id IN (?a)", 
            $ids);
        echo $this->renderDone();
    }
    public function handleRecord($id, $record)
    {
    }
}

class Aff_AdminPayoutController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('affiliates');
    }
    function indexAction()
    {
        // display payouts list date | method | total | paid |
        $ds = new Am_Query($this->getDi()->affPayoutTable);
        $ds->leftJoin('?_aff_payout_detail', 'd', 'd.payout_id=t.payout_id AND d.is_paid>0');
        $ds->addField('SUM(amount)', 'paid');
        $ds->setOrder('date', 'DESC');
        $grid = new Am_Grid_Editable('_payout', ___("Payouts"), $ds, $this->_request, $this->view);
        $grid->setPermissionId('affiliates');
        $grid->actionsClear();
        $grid->addField('date', ___('Date'));
        $grid->addField('thresehold_date', ___('Thresehold Date'));
        $grid->addField('type', ___('Payout Method'));
        $grid->addField('total', ___('Total to Pay'));
        $grid->addField('paid', ___('Total Paid'));
        //$grid->actionAdd(new Am_Grid_Action_Url('run', ___('Run'), '__ROOT__/aff/admin-payout/run?payout_id=__ID__'));
        $grid->actionAdd(new Am_Grid_Action_Url('view', ___('View'), '__ROOT__/aff/admin-payout/view?payout_id=__ID__'))
            ->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_RunPayout('run_payout', ___('Generate Payout Manually')));
        $grid->actionAdd(new Am_Grid_Action_ExportPayout());
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this, 'cbGetTrAttribs'));
        $grid->runWithLayout();
    }

    public function cbGetTrAttribs(& $ret, $record)
    {
        if ($record->total <= $record->paid)
        {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }

    function viewAction()
    {
        // display payouts list date | method | total | paid |
        $id = $this->getInt('payout_id');
        
        if (!$id) throw new Am_Exception_InputError("Not payout_id passed");
        $ds = new Am_Query($this->getDi()->affPayoutDetailTable);
        $ds->leftJoin('?_aff_payout', 'p', 'p.payout_id=t.payout_id');
        $ds->leftJoin('?_user', 'u', 't.aff_id=u.user_id');
        $ds->addField('u.*');
        $ds->addField('p.type', 'type');
        $ds->addWhere('t.payout_id=?d', $id);
        
        $grid = new Am_Grid_Editable('_d', ___("Payout %d Details", $id), $ds, $this->_request, $this->view);
        $grid->setPermissionId('affiliates');
        $grid->addCallback(Am_Grid_Editable::CB_RENDER_TABLE, array($this, 'addBackLink'));
        
        $grid->addField('email', ___('E-Mail'));
        $grid->addField('name_f', ___('First Name'));
        $grid->addField('name_l', ___('Last Name'));
        $grid->addField('type', ___('Payout Method'));
        $grid->addField('amount', ___('Amount'));
//        $grid->addField('receipt_id', ___('Receipt Id'));
        $grid->addField('is_paid', ___('Is Paid?'));
        $grid->addField(new Am_Grid_Field_Expandable('_details', ___('Payout Details')))
             ->setGetFunction(array($this, 'getPayoutDetails'));
        $grid->actionsClear();
        //$grid->actionAdd(new Am_Grid_Action_LiveEdit('receipt_id'));
        $grid->actionAdd(new Am_Grid_Action_PayoutMarkPaid('mark_paid', ___("Mark Paid")));
        $grid->actionAdd(new Am_Grid_Action_PayoutMarkNotPaid('mark_notpaid', ___("Mark NOT Paid")));
        $grid->runWithLayout();
        // detail payout records date | method | paid | receipt_id | aff. payout fields
    }
    function getPayoutDetails($obj)
    {
        $obj = $this->getDi()->userTable->createRecord($obj->toArray());
        
        $type = $obj->aff_payout_type;
        $pattern = 'aff_' . $type . '_';
        $out = "";
        foreach ($obj->data()->getAll() as $k => $v)
        {
            if (strpos($k, $pattern) !== 0) continue;
            $out .= sprintf("<b>%s</b> : %s <br />\n", 
                Am_Controller::escape(ucfirst(substr($k, strlen($pattern)))),
                Am_Controller::escape($v));
        }
        return $out ? $out : '-no details-';
    }
    function addBackLink(& $out, Am_Grid_ReadOnly $grid)
    {
        $out = "<a href='".ROOT_URL."/aff/admin-payout'>".___('Return to Payouts List')."</a><br /><br />" . $out;
    } 
    function runAction()
    {
        //
    }
}
