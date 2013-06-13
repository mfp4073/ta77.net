<?php 
/*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin Info /
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class AdminBackupController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_BACKUP_RESTORE);
    }
    
    function backupAction(){
        check_demo();
        if (!$this->_request->isPost())
            throw new Am_Exception_InputError("Backup can be runned by POST request only");

        $dat = date('Y_m_d-Hi');
        $host = strtolower(preg_replace('/[^a-zA-Z0-9\.]/', '', 
            preg_replace('/^www\./', '', $_SERVER['HTTP_HOST']))
        );
        $fn = "amember-$host-$dat.sql";
        
        while (@ob_end_clean());        
        $bp = new Am_BackupProcessor;
        
        if ($bp->isGzip())
            header("Content-Type: application/x-gzip");
        else
            header("Content-Type: text/sql");

        header("Content-Disposition: attachment; filename=$fn" .
               ($bp->isGzip() ? ".gz" : "" ) );
        
        $stream = fopen('php://output', 'wb');
        if (!$stream) throw new Am_Exception_InternalError("Could not open php://output stream");
        
        $bp->run($stream);
        
        $this->getDi()->adminLogTable->log('Downloaded backup');
        exit(); // no any output later!
    }
    
    function indexAction()
    {
        if (in_array('cc', $this->getDi()->modules->getEnabled()))
           throw new Am_Exception_AccessDenied("Online backup is disabled if you have CC payment plugins enabled. Use offline backup instead");
        $this->view->display('admin/backup.phtml');
    }
}
