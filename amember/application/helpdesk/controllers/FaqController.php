<?php

class Helpdesk_FaqController extends Am_Controller {
    function preDispatch() {
        $this->getDi()->auth->requireLogin(ROOT_URL . '/helpdesk/faq');
        $this->view->headLink()->appendStylesheet($this->view->_scriptCss('helpdesk-user.css'));
        parent::preDispatch();
    }

    public function indexAction()
    {
        $this->view->categories = $this->getDi()->helpdeskFaqTable->getCategories();
        $this->view->catActive = $this->getParam('cat');
        $this->view->faq = $this->getDi()->helpdeskFaqTable->findByCategory($this->getParam('cat', ''));

        $this->view->display('helpdesk/faq.phtml');
    }

    public function itemAction()
    {
        $this->view->faq = $this->getDi()->helpdeskFaqTable->findFirstByTitle($this->getParam('title'));
        $this->view->display('helpdesk/faq-item.phtml');
    }
}