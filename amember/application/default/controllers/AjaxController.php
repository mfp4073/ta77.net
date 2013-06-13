<?php


class AjaxController extends Am_Controller
{
    function ajaxError($msg){
        $this->ajaxResponse(array('msg' => $msg));
    }

    function ajaxGetStates($vars){
        return $this->ajaxResponse($this->getDi()->stateTable->getOptions($vars['country']));
    }

    function ajaxCheckUniqLogin($vars){
        $login = htmlentities($vars['login']);
        $msg = null;
        do {
            // check for valid login first
            if ($vars['login'] == '' ||
                strlen($vars['login']) < $this->getDi()->config->get('login_min_length', 1) ||
                strlen($vars['login']) > $this->getDi()->config->get('login_max_length', 64))
            {
                $msg = sprintf(___('Please enter valid Login Name. It must contain at least %d characters'), $this->getDi()->config->get('login_min_length'), $this->getDi()->config->get('login_max_length'));
                break;
            }
            if (!preg_match($this->getDi()->userTable->getLoginRegex(), $vars['login'])) {
                $msg = $config['login_disallow_spaces'] ? ___('Username contains invalid characters - please use digits, letters or spaces') : ___('Username contains invalid characters - please use digits and letters');
                break;
            }
            // check if it is available
            $r = UserTable::checkUniqLoginPassEmail($vars['login'], $vars['email'], $vars['pass']);
            if (!$r){
                $msg =
                    sprintf(___('Username %s has been taken by another user'), htmlentities($login)) . ".<br />" .
                    ___('Please select a different log-in name') . "<br />" .
                    sprintf(___('If that is your account, please go to %syour membership page%s to login into your subscription.'), "<a href='member' target='blank'>", "</a>");
                break;
            } else {
                $msg = true;
                break;
            }
        } while (false);
        return $this->ajaxResponse($msg);
    }
    function ajaxCheckCoupon($vars){
        $coupon = htmlentities($vars['coupon']);
        $coupon = $this->getDi()->couponTable->findFirstByCode($coupon);
        $msg = $coupon ? $coupon->validate() : ___('No coupons found with such coupon code');
        return $this->ajaxResponse($msg === null ? true : $msg);
    }
    function indexAction()
    {
        header("Content-type: text/plain; charset=utf-8");
        $vars = $this->_request->toArray();
        switch ($this->_request->getFiltered('do')){
            case 'get_states':
                $this->ajaxGetStates($vars);
                break;
            case 'check_uniq_login':
                $this->ajaxCheckUniqLogin($vars);
                break;
            case 'check_coupon':
                $this->ajaxCheckCoupon($vars);
                break;
            default:
                $this->ajaxError('Unknown Request: ' . $vars['do']);
        }
    }
    function unsubscribedAction()
    {
        $v = $this->_request->getPost('unsubscribed');
        if (strlen($v) != 1) 
            throw new Am_Exception_InputError("Wrong input");
        $v = ($v > 0) ? 1 : 0;
        if (($s = $this->getFiltered('s')) && ($e = $this->getParam('e')) &&
            Am_Mail::validateUnsubscribeLink($e, $s))
        {
            $user = $this->getDi()->userTable->findFirstByEmail($e);
        } else {
            $user = $this->getDi()->user;
        }
        if (!$user) 
            return $this->ajaxError("You must be logged-in to run this action");
        if ($user->unsubscribed != $v)
        {
            $user->set('unsubscribed', $v)->update();            
            $this->getDi()->hook->call(Am_Event::USER_UNSUBSCRIBED_CHANGED, 
                array('user'=>$user, 'unsubscribed' => $v));
        }
        $this->ajaxResponse(array('status' => 'OK', 'value' => $v));
    }
}