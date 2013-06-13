<?php
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Fix corrupted MySQL tables
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class AdminRepairTablesController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    function indexAction()
    {
        $tables = $this->getDi()->db->selectCol("SHOW TABLES LIKE ?", $this->getDi()->config->get('db.mysql.prefix').'%');

        foreach ($tables as $t){
            print "Reparing {$db->config[prefix]}$t..."; ob_end_flush();
            $db->query("REPAIR TABLE {$db->config[prefix]}$t");
            print "OK<br />";
        }
        print "tables restored.";
    }
}
