<?php

class Aff_AdminController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('affiliates');
    }
    function subaffTabAction()
    {
        $ds = new Am_Query($this->getDi()->userTable);
        $ds = $ds->addField("concat(name_f, ' ', name_l)", 'name')
                ->addWhere('is_affiliate=?', 1)
                ->addWhere('aff_id=?', $this->getParam('user_id'));
        $grid = new Am_Grid_ReadOnly('_subaff', ___('Subaffiliate'), $ds, $this->getRequest(), $this->getView(), $this->getDi());
        $grid->addField(new Am_Grid_Field('login', ___('Username'), true));
        $grid->addField(new Am_Grid_Field('name', ___('Name'), true));
        $grid->addField(new Am_Grid_Field('email', ___('E-Mail Address'), true));
        $grid->runWithLayout('admin/user-layout.phtml');
    }

    function infoTabAction()
    {
        require_once APPLICATION_PATH . '/default/controllers/AdminUsersController.php';
        require_once 'Am/Report.php';
        require_once 'Am/Report/Standard.php';
        include_once APPLICATION_PATH . '/aff/library/Reports.php';
        $this->setActiveMenu('users-browse');

        $rs = new Am_Report_AffStats();
        $rs->setAffId($this->user_id);
        $rc = new Am_Report_AffClicks();
        $rc->setAffId($this->user_id);
        
        $form = $rs->getForm();
        if ($form->isSubmitted() && $form->validate())
            $rs->applyConfigForm($this->_request);
        else 
        {
            $rs->setInterval('-1 month', 'now')->setQuantity(new Am_Report_Quant_Day());
            $form->addDataSource(new Am_Request(array('start' => $rs->getStart(), 'stop' => $rs->getStop())));
        }
        $rc->setInterval($rs->getStart(), $rs->getStop())->setQuantity(clone $rs->getQuantity());
            
        $result = $rs->getReport();
        $rc->getReport($result);
        
        $result->sortPoints();
        
        $this->view->form = $form;
        $this->view->form->setAction($this->_request->getRequestUri());
        
        $output = new Am_Report_Graph_Line($result);
        $output->setSize('100%', 300);
        $this->view->report = $output->render();

        $this->view->result = $result;
        
        $this->view->display('admin/aff/info-tab.phtml');
    }
    function infoTabDetailAction()
    {
        $date = $this->getFiltered('date');
        
        $this->view->date = $date;
        $this->view->commissions = $this->getDi()->affCommissionTable->fetchByDate($date, $this->user_id);
        $this->view->clicks = $this->getDi()->affClickTable->fetchByDate($date, $this->user_id);
        $this->view->display('admin/aff/info-tab-detail.phtml');
    }
    function preDispatch()
    {
        $this->user_id = $this->getInt('user_id');
        if (!$this->user_id)
            throw new Am_Exception_InputError("Wrong URL specified: no member# passed");
        $this->view->user_id = $this->user_id;
    }
}