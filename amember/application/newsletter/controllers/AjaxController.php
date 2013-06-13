<?php

class Newsletter_AjaxController extends Am_Controller
{
    function updateSubscriptionAction()
    {
        if (($s = $this->getFiltered('s')) && ($e = $this->getParam('e')) &&
            Am_Mail::validateUnsubscribeLink($e, $s))
        {
            $user = $this->getDi()->userTable->findFirstByEmail($e);
        } else {
            $user = $this->getDi()->user;
        }
        if (!$user) throw new Am_Exception_InputError("You must be logged-in to use this function");
        
        $allowed = array();
        foreach ($this->getDi()->newsletterListTable->getAllowed($user) as $r)
            $allowed[$r->pk()] = $r;
        
        $subs = array();
        foreach ($this->getDi()->newsletterUserSubscriptionTable->findByUserId($user->pk()) as $s)
            $subs[$s->list_id] = $s;
        
        $post = $this->getRequest()->getPost();
        $ret = array('status' => 'OK');
        foreach ($post as $k => $v)
        {
            if (!is_int($k)) continue;
            switch ($v)
            {
                case 0:
                    if (!empty($subs[$k]))
                        $subs[$k]->unsubscribe();
                    $ret[(int)$k] = (int)$v;
                    break;
                case 1:
                    $this->getDi()->newsletterUserSubscriptionTable->add($user, 
                        $this->getDi()->newsletterListTable->load($k),
                        NewsletterUserSubscription::TYPE_USER);
                    $ret[(int)$k] = (int)$v;
                    break;
                default:
                    throw new Am_Exception_InputError("Wrong value submitted");
            }
        }
        $this->ajaxResponse($ret);
    }
}