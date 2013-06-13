<?php

class Aff_SignupController extends Am_Controller
{
    /** @var Am_Form_Signup */
    protected $form;
    /** @var array */
    protected $vars;
    /** @var SavedForm */
    protected $record;
    
    function indexAction()
    {
        if ($this->getDi()->auth->getUserId())
            $this->_redirect('aff/aff'); // there are no reasons to use this form if logged-in
        $form = $this->getDi()->savedFormTable->getByType(SavedForm::D_AFF);
        if (!$form) {
            throw new Am_Exception_QuietError(___('There are no form available for affiliate signup.'));
        }
        $this->record = $form;
        $this->view->title = $this->record->title;
        $this->form = new Am_Form_Signup();
        $this->form->setParentController($this);
        $this->form->initFromSavedForm($this->record);
        $this->form->run();
    }
    function display(Am_Form $form, $pageTitle)
    {
        $this->view->form = $form;
        $this->view->title = $this->record->title;
        if ($pageTitle) $this->view->title = $pageTitle;
        $this->view->display($this->record->tpl ? ('signup/' . basename($this->record->tpl)) : 'signup/signup.phtml');
    }
    function process(array $vars, $name, HTML_QuickForm2_Controller_Page $page)
    {
        $this->vars = $vars;
        $em = $page->getController()->getSessionContainer()->getOpaque('EmailCode');
        // do actions here
        $this->user = $this->getDi()->userRecord;
        $this->user->setForInsert($this->vars); // vars are filtered by the form !
        $this->user->is_affiliate = 1;
        if($this->getDi()->config->get('aff.signup_type')==2)
            $this->user->is_approved =0;
            
        
        if (empty($this->user->login))
            $this->user->generateLogin();

        if (empty($this->vars['pass']))
            $this->user->generatePassword();
        else {
            $this->user->setPass($this->vars['pass']);
        }
        $this->user->insert();
        // remove verification record
        if (!empty($em))
            $this->getDi()->store->delete(Am_Form_Signup_Action_SendEmailCode::STORE_PREFIX . $em);
        $page->getController()->destroySessionContainer();
        $this->getDi()->hook->call(Am_Event::SIGNUP_USER_ADDED, array(
            'vars' => $this->vars,
            'user' => $this->user,
            'form' => $this->form,
        ));
        $this->getDi()->hook->call(Am_Event::SIGNUP_AFF_ADDED, array(
            'vars' => $this->vars,
            'user' => $this->user,
            'form' => $this->form,
        ));
        if($this->user->isApproved()) $this->getDi()->auth->setUser($this->user, $_SERVER['REMOTE_ADDR']);

        if ($this->getDi()->config->get('aff.registration_mail') && $this->user->isApproved())
        {
            $this->getDi()->modules->get('aff')->sendAffRegistrationEmail($this->user);
        }
        
        if(!$this->user->isApproved()){
            $this->user->sendNotApprovedEmail();
        }
        
        $this->_redirect('aff/aff');
        return true;
   }

   function getCurrentUrl()
   {
       $c = $this->getFiltered('c');
       return $this->_request->getScheme() . '://' .
              $this->_request->getHttpHost() .
              $this->_request->getBaseUrl() . '/' .
              $this->_request->getModuleName() . '/' .
              $this->_request->getControllerName();
   }
    
}