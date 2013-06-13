<?php 

/**
 * Display rendered form to continue payment
 * 
 * Form must be returned without <h2> headers and common fields
 * like "confirm"
 * 
 * @package Am_Paysystem
 */
class Am_Paysystem_Action_Form extends Am_Paysystem_Action_Redirect
{
    function getVars(){
        return $this->_params;
    }
    function getUrl(){
        return $this->_url;
    }
    public function process(Am_Controller $action = null)
    {
        $action->view->url = $this->getURL();
        $action->view->vars = $this->getVars();
        $action->render('payment', '',true); 
        throw new Am_Exception_Redirect($this->getURL());
    }

}