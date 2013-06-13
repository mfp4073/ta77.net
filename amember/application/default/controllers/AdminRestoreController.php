<?php

/*
 *
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

class AdminRestoreController extends Am_Controller {

    public function checkAdminPermissions(Admin $admin) {
        return $admin->hasPermission(Am_Auth_Admin::PERM_BACKUP_RESTORE);
    }

    function restoreAction() {
        check_demo();
        if (!$this->_request->isPost())
            throw new Am_Exception_InputError("Only POST requests allowed here");

        @ini_set('memory_limit', '256M');
        $db = $this->getDi()->db;

        $f = file_get_contents($_FILES['file']['tmp_name']);
        if (!preg_match('/^(.+?)[\r\n]+(.+?)[\r\n]+/ms', $f, $regs)) {
            throw new Am_Exception_InputError("Uploaded file has wrong format or empty");
        }
        $first_line = trim($regs[1]);
        $second_line = trim($regs[2]);

        $this->view->assign('backup_header', "$first_line<br />$second_line");

        if (!preg_match('/^### aMember Pro .+? database backup/', $first_line))
            throw new Am_Exception_InputError("Uploaded file is not valid aMember Pro backup");
        foreach (preg_split('/;[\r\n]+/', $f) as $sql)
            if (strlen($sql))
            {
                $db->query($sql);
            }
        $this->getDi()->adminLogTable->log("Restored from $first_line");
        $this->displayRestoreOk();
    }

    function displayRestoreOk() {
        ob_start();
        $this->view->title = "Restored Successfully";

        echo <<<CUT
aMember database has been successfully restored from backup.<br />
<i>Backup file header:</i>
<pre>
{$this->view->backup_header}
</pre>
CUT;
        $this->view->content = ob_get_clean();
        $this->view->display('admin/layout.phtml');
    }

    function indexAction() {
        $url = $this->escape($this->getUrl(null, 'restore'));
        ob_start();
        echo <<<CUT
<div class="info">        
    <p>To restore the aMember database please pick a previously saved aMember Pro backup.</p>
    <p><b><font color=red>WARNING! ALL YOUR CURRENT AMEMBER TABLES
    AND RECORDS WILL BE REPLACED WITH THE CONTENTS OF THE BACKUP!</font></b></p>
</div>        
<div class="am-form">
    <form action="$url" method=post enctype="multipart/form-data"
    onsubmit="return confirm('It will replace all your exising database with backup. Do you really want to proceed?')">
    <div class="row">
        <div class="element-title">
            <label>File</label>
        </div>
        <div class="element">
            <input type=file name=file class="styled">
        </div>
    </div>
    <div class="row">
        <div class="element-title"></div>
        <div class="element">
            <input type=submit value=Restore >
        </div>
    </div>
    </form>
</div>
CUT;
        $this->view->title = "Restore Database from Backup";
        $this->view->content = ob_get_clean();
        $this->view->display('admin/layout.phtml');
    }

    public function preDispatch() {
        parent::preDispatch();
        if (in_array('cc', $this->getDi()->modules->getEnabled()))
            throw new Am_Exception_AccessDenied("Online backup is disabled if you have CC payment plugins enabled. Use offline backup instead");
    }

}
