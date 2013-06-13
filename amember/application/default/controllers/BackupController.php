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

class BackupController extends Am_Controller
{
    function cronAction() {
        check_demo();
        if (!$this->getDi()->config->get('email_backup_frequency')) {
            throw new Am_Exception_InternalError("Email Backup feature is disabled at Setup/Configuration -> Advanced");
        }
        
        $key = $this->getParam('k');
        if ($key != $this->getDi()->app->getSiteHash('backup-cron', 10)) {
            throw new Am_Exception_AccessDenied("Incorrect access key");
        }       
        
        $dat = date('Y_m_d');
         
        $stream = fopen('php://temp', 'w+b');
        if (!$stream) throw new Am_Exception_InternalError("Could not open php://temp stream");
        
        $bp = new Am_BackupProcessor;
        
        $stream = $bp->run($stream);
        rewind($stream);
        
        $filename = $bp->isGzip() ? "amember-$dat.sql.gz" : "amember-$dat.sql";
        $mimeType = $bp->isGzip() ? 'application/x-gzip' : 'text/sql';
        
        $m = new Am_Mail();
        $m->addTo($this->getDi()->config->get('email_backup_address'))
                ->setSubject('Email Backup ' . $dat)
                ->setFrom($this->getDi()->config->get('admin_email'));
        $m->setBodyText(sprintf("File with backup for %s is attached. Backup was done at %s",
                $this->getDi()->config->get('root_url'), $this->getDi()->sqlDate
                ));
        $m->createAttachment($stream, $mimeType, Zend_Mime::DISPOSITION_ATTACHMENT, Zend_Mime::ENCODING_BASE64, $filename);
        $m->setPeriodic(Am_Mail::ADMIN_REQUESTED);
        $m->send();
        
        $this->getDi()->adminLogTable->log('Email backup to ' . $this->getDi()->config->get('email_backup_address'));
    }
}
