<?php

class Am_Helpdesk_Grid_My extends Am_Helpdesk_Grid_Admin {
    public function __construct(Am_Request $request, Am_View $view) {
        parent::__construct($request, $view);
        $this->getDataSource()->getDataSourceQuery()->addWhere('t.owner_id=?d', $this->getDi()->authAdmin->getUserId());
    }
    public function getGridTitle() {
        return ___("Tickets Assigned to Me");
    }
}


class Helpdesk_AdminMyController extends Am_Controller_Pages {

    public function checkAdminPermissions(Admin $admin) {
        return $admin->hasPermission('helpdesk');
    }

    function preDispatch() {
        $this->view->headLink()->appendStylesheet($this->view->_scriptCss('helpdesk-admin.css'));
        $this->setActiveMenu('helpdesk-tickets-my');
        parent::preDispatch();
    }

    public function initPages() {
        $this->addPage('Am_Helpdesk_Grid_My', 'index', ___('Tickets'))
                ->addPage(array($this, 'createController'), 'view', ___('Conversation'));
    }

    public function renderTabs() {
        return '';
    }

    public function createController($id, $title, $grid) {
        return new Am_Helpdesk_Controller($grid->getRequest(), $grid->getResponse(), $this->_invokeArgs);
    }
}