<?php
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin Access log
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision: 4649 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class Helpdesk_AdminController extends Am_Controller_Pages {

    public function checkAdminPermissions(Admin $admin) {
        return $admin->hasPermission('helpdesk');
    }

    function preDispatch() {
        $this->view->headLink()->appendStylesheet($this->view->_scriptCss('helpdesk-admin.css'));
        $this->setActiveMenu('helpdesk-tickets');
        parent::preDispatch();
    }

    public function initPages() {
        $this->addPage('Am_Helpdesk_Grid_Admin', 'index', ___('Tickets'))
                ->addPage(array($this, 'createController'), 'view', ___('Conversation'));
    }

    public function renderTabs() {
        return '';
    }

    public function createController($id, $title, $grid) {
        return new Am_Helpdesk_Controller($grid->getRequest(), $grid->getResponse(), $this->_invokeArgs);
    }
}
