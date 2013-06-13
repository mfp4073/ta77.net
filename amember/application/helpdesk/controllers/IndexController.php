<?php

class Am_Helpdesk_Grid_Member extends Am_Helpdesk_Grid {

    public function initGridFields() {
        $this->addField(new Am_Grid_Field('ticket_mask', '#', true, '', null, '10%'));
        $this->addField(new Am_Grid_Field('subject', ___('Subject'), true, '', array($this, 'renderSubject'), '35%'));
        $this->addField(new Am_Grid_Field('updated', ___('Updated'), true, '', array($this, 'renderTime'), '20%'));
        $this->addField(new Am_Grid_Field('status', ___('Status'), true, '', array($this, 'renderStatus'), '5%'));
        $this->addField(new Am_Grid_Field('msg_cnt', ___('Messages'), true, 'center', null, '5%'));

        $this->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this, 'cbGetTrAttribs'));
    }

    public function getStatusIconId($id, $record) {
        return $id == 'awaiting' && $record->status == HelpdeskTicket::STATUS_AWAITING_USER_RESPONSE ?
            $id . '-me' : $id;
    }
    
    public function createDs() {
        $query = parent::createDS();
        $query->addWhere('t.user_id=?',
                Am_Di::getInstance()->auth->getUserId()
        );
        return $query;
    }

}

class Helpdesk_IndexController extends Am_Controller_Pages {
    protected $layout = 'member/layout.phtml';

    function preDispatch() {
        $this->getDi()->auth->requireLogin(ROOT_URL . '/helpdesk');
        $this->view->headLink()->appendStylesheet($this->view->_scriptCss('helpdesk-user.css'));
        parent::preDispatch();
    }

    public function initPages() {
        $this->addPage('Am_Helpdesk_Grid_Member', 'index', ___('Tickets'))
             ->addPage(array($this, 'createController'), 'view', ___('Conversation'));
    }

    public function renderTabs() {
        $intro = $this->getDi()->config->get('helpdesk.intro');
        return $intro ? sprintf('<div class="am-info">%s</div>', $intro) : '';
    }

    public function createController($id, $title, $grid) {
        return new Am_Helpdesk_Controller($grid->getRequest(), $grid->getResponse(), $this->_invokeArgs);
    }
}