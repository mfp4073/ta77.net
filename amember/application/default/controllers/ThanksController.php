<?php                                                        

class ThanksController extends Am_Controller {
    /** @var Invoice */
    protected $invoice;
    function indexAction(){
        $id = $this->_request->getFiltered('id');
        if (empty($id)) $id = filterId(@$_GET['id']);
        $this->invoice = null;
        if ($id) 
        {
            $this->invoice = $this->getDi()->invoiceTable->findBySecureId($id, 'THANKS');
            if (!$this->invoice)
                throw new Am_Exception_InputError("Invoice #$id not found");
            $tm = max($this->invoice->tm_started, $this->invoice->tm_added);
            if (($this->getDi()->time - strtotime($tm)) > 48*3600)
                throw new Am_Exception_InputError("Link expired");
            $this->view->invoice = $this->invoice;
            $p = $this->getDi()->invoicePaymentRecord;
            foreach ($this->invoice->getPaymentRecords() as $p);
            $this->view->payment = $p;
            
            if (!$this->invoice->tm_started)
            {
                $this->view->show_waiting = true;
                $this->view->refreshTime = "<span id='am-countdown'>00:10</span> ".___("seconds");
            }
            $this->view->script = $this->getJs(10);
        }
        
        $this->getDi()->hook->call(Am_Event::THANKS_PAGE, array(
            'controller' => $this,
            'invoice'    => $this->invoice,
        ));
        
        $this->view->display('thanks.phtml');
    }
    function getJs($seconds)
    {
        return <<<CUT
$(function(){
    var left = $seconds;
    var f = function() 
    {
        left--;
        var m = Math.floor(left / 60);
        var s = left - m*60;
        if (m<10) m = "0"+m;
        if (s<10) s = "0"+s;
        $("#am-countdown").text(m+":"+s);
        if (!left) {
            window.location.href = window.location.href; // reload page
        } else {
            setTimeout(f, 1000);
        }
    };
    f();
});
CUT;
    }
}
