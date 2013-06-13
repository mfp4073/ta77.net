<?php

class UnsubscribeController extends Am_Controller
{
    function indexAction()
    {
        $e = $this->getParam('e');
        if (!$e)
            throw new Am_Exception_InputError("Empty e-mail parameter passed - wrong url");
        
        $s = $this->getFiltered('s');
        if (!Am_Mail::validateUnsubscribeLink($e, $s, Am_Mail::LINK_USER))
            throw new Am_Exception_InputError(___('Wrongly signed URL, please contact site admin'));

        $this->view->user = $this->getDi()->userTable->findFirstByEmail($e);
        if (!$this->view->user)
            throw new Am_Exception_InputError(___("Wrong parameters, error #1253"));

        if ($this->_request->get('yes'))
        {
            $this->view->user->unsubscribed = 1;
            $this->view->user->update();
            $this->getDi()->hook->call(Am_Event::USER_UNSUBSCRIBED_CHANGED, 
                array('user'=>$this->view->user, 'unsubscribed' => 1));
            return $this->_redirect('member');
        } elseif ($this->_request->get('no')) {
            return $this->_redirect('member');
        }
        
        
        
        $this->view->e = $e;
        $this->view->s = $s;
        
        if (!$this->getDi()->blocks->get($this->view, 'unsubscribe'))
        {
            $this->getDi()->blocks->add(new Am_Block('unsubscribe', ___('Unsubscribe'), 'unsubscribe-std', 
                null, 'unsubscribe-std.phtml'));
        }
        $this->view->display('unsubscribe.phtml');
    }
}