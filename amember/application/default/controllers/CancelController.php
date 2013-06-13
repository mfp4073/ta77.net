<?php                                                        
/*
*  User's cancel payment page. Displayed after failed payment.
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: User's failed payment page
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedbacks to the cgi-central support
* http://www.cgi-central.net/support/
*                                                                          
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*                                                                                 
*/
class CancelController extends Am_Controller {
    /** @var Invoice */
    protected $invoice;

    public function preDispatch()
    {
        parent::preDispatch();
        $this->view->invoice = null;
        $this->view->id = null;
        $this->view->paysystems = array();
        $this->invoice = $this->getDi()->invoiceTable->findBySecureId($this->getFiltered('id'), "CANCEL");
        if ($this->invoice)
        {
            if ($this->invoice->isPaid())
                throw new Am_Exception_InputError("Invoice #$id is already paid");
            
            $this->getDi()->plugins_payment->loadEnabled();
            
            $this->view->paysystems = $this->getDi()->paysystemList->getAllPublicAsArrays();
            $this->view->invoice = $this->invoice;
            $this->view->id = $this->getFiltered('id');
        }
            
    }
    
    function repeatAction()
    {
        if (!$this->invoice)
            throw new Am_Exception_InputError("No invoice found, cannot repeat");
        if ($this->invoice->isPaid())
            throw new Am_Exception_InputError("Invoice #$id is already paid");
        $found = false;
        foreach ($this->view->paysystems as $ps)
            if ($ps['paysys_id'] == $this->getFiltered('paysys_id'))
            {
                $found = true; break;
            }
        if (!$found) return $this->indexAction();
        
        $this->invoice->updateQuick('paysys_id', $this->getFiltered('paysys_id'));
        
        if ($err = $this->invoice->validate())
            throw new Am_Exception_InputError($err[0]);
        
        $payProcess = new Am_Paysystem_PayProcessMediator($this, $this->invoice);
        $result = $payProcess->process();
        if ($result->isFailure())
        {
            $this->view->error = $result->getErrorMessages();
            return $this->indexAction();
        }
    }
    
    function indexAction(){
        $this->view->display('cancel.phtml');
    }
}
