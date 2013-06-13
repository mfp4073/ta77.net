<?php
/*
*   Members page. Used to renew subscription.
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Member display page
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision: 5371 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/


class FileController extends Am_Controller {

    const CD_INLINE = 'inline';
    const CD_ATTACHMENT = 'attachment';

    public function downloadAction()
    {
       return $this->_getFile(self::CD_ATTACHMENT);
    }

    public function getAction()
    {
       return $this->_getFile(self::CD_INLINE);
    }

    protected function _getFile($attachment)
    {
        $file = $this->getDi()->uploadTable->findFirstByPath($this->getParam('path'));
        if ($file && 
                $this->getDi()->uploadAcl->checkPermission($file, 
                    Am_Upload_Acl::ACCESS_READ, 
                    $this->getDi()->auth->getUser())) {
                return $this->pushFile($file, $attachment);
        }
        throw new Am_Exception_AccessDenied();
    }

    protected function pushFile(Upload $file, $attachment=self::CD_INLINE)
    {
        header('Cache-Control: maxage=3600');
        header('Pragma: public');
        header('Content-type: ' . $file->getType());
        header('Content-Disposition: '.$attachment.'; filename="' . $file->getName() . '"');
        
        readfile($file->getFullPath());
    }
}
