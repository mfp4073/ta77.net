<?php

class IndexController extends Am_Controller
{
    function indexAction()
    {
        if(!$this->getDi()->auth->getUserId())
            $this->getDi()->auth->checkExternalLogin($this->getRequest());
        if($this->getDi()->auth->getUserId() && $this->getDi()->config->get('skip_index_page'))
            Am_Controller::redirectLocation ($this->getUrl ('member', 'index'));
        $this->view->display("index.phtml");
    }
}
