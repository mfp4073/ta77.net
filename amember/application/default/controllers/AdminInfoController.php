<?php 
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin Info / PHP
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class AdminInfoController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    
    function indexAction()
    {
        check_demo();

        $this->view->title = "Version Info";
        $trial = "";
        if ('==TRIAL==' != '=='.'TRIAL==')
        {
            $trial = "Trial Version (expires ==TRIAL_EXPIRES==)";
        }
        $am_version = AM_VERSION;
        $phpversion = phpversion() . " (".php_sapi_name().")";
        $os=substr(php_uname(),0,28);
        if (strlen($os)==28) $os="$os...";
        $mysql = $this->getDi()->db->selectCell("SELECT VERSION()");
        $root  = ROOT_DIR;
        
        $modules = array();
        foreach ($this->getDi()->modules->getEnabled() as $m)
        {
            $fn = APPLICATION_PATH . '/' . $m . '/module.xml';
            if (!file_exists($fn)) continue;
            $xml = simplexml_load_file($fn);
            if (!$xml) continue;
            
            $version = "(" . $xml->version . ")";
            $modules[] = "$m $version";
        }
        $modules = join("<br />", $modules);
        
        $plugins = "";
        foreach (array_merge(
            $this->getDi()->plugins_payment->loadEnabled()->getAllEnabled(),
            $this->getDi()->plugins_protect->loadEnabled()->getAllEnabled()) as $p) {
            $rClass = new ReflectionClass(get_class($p));
            $plugins .= sprintf("%s (%s - %s) <br />\n",
                $p->getId(),
                preg_replace('/\$'.'Revision: (\d+).*/', '$1', $rClass->getConstant('PLUGIN_REVISION')),
                preg_replace('/\$'.'Date: (.+?)\s+.+/', '$1',  $rClass->getConstant('PLUGIN_DATE')));
        
        }
        $amInfo = <<<CUT
<div class="grid-container">
<table class='grid'>
<tr>
    <th colspan="2">Software version info</th>
</tr>
<tr>
    <td align=right>&nbsp;aMember </td>
    <td> <b>$am_version</b>
    $trial    
    </td>
</tr>
<tr class="odd">
    <td align=right>&nbsp;PHP </td>
    <td> <b>$phpversion</b></td>
</tr>
<tr>
    <td align=right>OS </td>
    <td> <b>$os</b></td>
</tr>
<tr class="odd">
    <td align=right>&nbsp;MySQL </td>
    <td> <b>$mysql</b></td>
</tr>
<tr>
    <td align=right>Root Folder </td>
    <td> <b>$root</b></td>
</tr>

<tr class="odd">
    <td align='right'>Modules</td>
    <td>$modules</td>
</tr>

<tr>
    <td align='right'>Plugins</td>
    <td>$plugins</td>
</tr>

</td></tr></table>
</div>
<br /><br />
CUT;

        ob_start();
        phpinfo(1|4|8|16|32);
        $phpInfo = ob_get_clean();

        $phpStyles = <<<CUT
#phpinfo {background-color: #ffffff; color: #000000;}
#phpinfo td, #phpinfo th, #phpinfo h1, #phpinfo h2 {font-family: sans-serif;}
#phpinfo pre {margin: 0px; font-family: monospace;}
#phpinfo a:link {color: #000099; text-decoration: none; background-color: #ffffff;}
#phpinfo a:hover {text-decoration: underline;}
#phpinfo table {border-collapse: collapse;}
#phpinfo .center {text-align: center;}
#phpinfo .center table { margin-left: auto; margin-right: auto; text-align: left;}
#phpinfo .center th { text-align: center !important; }
#phpinfo td, #phpinfo th { border: 1px solid #000000; font-size: 75%; vertical-align: baseline;}
#phpinfo h1 {font-size: 150%;}
#phpinfo h2 {font-size: 125%;}
#phpinfo .p {text-align: left;}
#phpinfo .e {background-color: #ccccff; font-weight: bold; color: #000000;}
#phpinfo .h {background-color: #9999cc; font-weight: bold; color: #000000;}
#phpinfo .v {background-color: #cccccc; color: #000000;}
#phpinfo .vr {background-color: #cccccc; text-align: right; color: #000000;}
#phpinfo img {float: right; border: 0px;}
#phpinfo hr {width: 600px; background-color: #cccccc; border: 0px; height: 1px; color: #000000;}
CUT;

        preg_match('/<body>(.*)<\/body>/s', $phpInfo, $matches);
        $phpInfo = $matches[1];

        $content = sprintf('<style type="text/css">%s</style>%s<h1>PHP info</h1><div id="phpinfo" class="grid-container"><br />%s</div>',
                $phpStyles, $amInfo, $phpInfo);

        $this->view->assign('content', $content);
        $this->view->display("admin/layout.phtml");
    }
}
