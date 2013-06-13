<?php
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: upgrade DB from ../amember.sql
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision$)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*
*/

class AdminUpgradeDbController extends Am_Controller
{
    protected $db_version;
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    function convert_to_new_keys()
    {
        return;
        if (!$this->getDi()->modules->isEnabled('cc'))
            return;
        if (!file_exists(APPLICATION_PATH . '/configs/key.php')) return;
        $key = require_once APPLICATION_PATH . '/configs/key.php';
        
        $cryptNew = $this->getDi()->crypt;
        if ($cryptNew->compareKeySignatures() == 0) return;
        if (!file_exists(APPLICATION_PATH . '/configs/key-old.inc.php')) {
            print "
    <div style='color: red'><br />To convert your encrypted values to use new keystring,
    please copy old file <i>amember/application/confgigs/key.php</i> to
    <i>amember/application/confgigs/key-old.inc.php</i> and run this 'Upgrade Db' utility again.
    <br /><br />
    <b>It is also required to make backup of your database before conversion. GGI-Central is not responsible for any damage
    the conversion may result to if you have no backup saved before conversion. Please make backup first, then go back here for conversion.</b>
    <br />
    <br /> Once you made backup of the database and key file, please click <a href='admin-upgrade-db?refresh=".time()."'>this link</a> to run upgrade script again.
    </div>
            " ;
            return false;
        }
        $cryptOld = new Am_Crypt_Strong(require APPLICATION_PATH . '/configs/key-old.inc.php');
        $q = $this->getDi()->db->queryResultOnly("SELECT * FROM ?_cc");
        // dry run
        print "<br />Checking CC Records with old key..."; ob_flush();
        $count = 0;
        while ($r = mysql_fetch_assoc($q)){
            $cc = $this->getDi()->ccRecordRecord;
            $cc->setCrypt($cryptOld);
            $cc->fromRow($r);
            if (preg_match('/[^\s\d-]/', $cc->cc_number)) {
                print "<div style='color: red'>Problem with converting to new encryption key:</br>
                    cc record# {$cc->cc_id} could not be converted, it seems the old key has been specified incorrectly. Conversion cancelled.</div>";
                return;
            }
            $count++;
        }
        print "OK ($counts)\n<br />";
        print "Converting CC records with new key..."; ob_flush();
        // real run
        $q = $this->getDi()->db->queryResultOnly("SELECT * FROM ?_cc");
        $count = 0;
        while ($r = mysql_fetch_assoc($q)){
            $cc = $this->getDi()->ccRecordRecord;
            $cc->setCrypt($cryptOld);
            $cc->fromRow($r);
            if (preg_match('/[^\s\d-]/', $cc->cc_number)) {
                print "<div style='color: red'>Problem with converting to new encryption key:</br>
                    cc record# {$cc->cc_id} could not be converted, it seems the old key has been specified incorrectly. Conversion cancelled.</div>";
                return;
            }
            $cc->setCrypt($cryptNew);
            $cc->update();
            $count++;
        }
        $cryptNew->saveKeySigunature();

        print "OK ($count)\n<br />"; ob_flush();
        $this->getDi()->db->query("OPTIMIZE TABLE ?_cc"); // to remove stalled records
    }
    function indexAction()
    {
        $this->getDi()->db->setLogger(false);

        $t = new Am_View;
        set_time_limit(0);
        ini_set('memory_limit', '256M');

        $this->db_version = $this->getDi()->store->get('db_version');
        
        if (defined('AM_DEBUG')) ob_start();
        ?><html>
        <head><title>aMember Database Upgrade</title>
        <body>
        <h1>aMember Database Upgrade</h1>
        <hr />
        <?php


        /* ******************************************************************************* *
         *                  M A I N
         */
        $this->fixNotUniqueRecordInRebillLog();
        $this->getDi()->app->dbSync(true);
        $this->convert_to_new_keys();
        $this->checkInvoiceItemTotals();
        $this->convertTax();
        $this->convertAutoresponderPrefix();
        $this->enableSkipIndexPage();
        $this->manuallyApproveInvoices();
        $this->addCountryCodes();
        $this->fillResourceAccessSort();
        $this->upgradeFlowPlayerKey();
        $this->fixCryptSavedPass();
        $this->updateStateInfo();
        $this->getDi()->hook->call(new Am_Event(Am_Event::DB_UPGRADE, array('version' => $this->db_version)));

        echo "
        <br/><strong>Upgrade finished successfully.
        Go to </strong><a href='".REL_ROOT_URL."/admin/'>aMember Admin CP</a>.
        <hr />
        </body></html>";
    }

    function fixNotUniqueRecordInRebillLog()
    {
        //to set unique index (invoice_id,rebill_date)
        if (version_compare($this->db_version, '4.2.15') < 0)
        {
            $db = $this->getDi()->db;
            try { //to handle situation when ?_cc_rebill table does not exists
                $db->query('CREATE TEMPORARY TABLE ?_cc_rebill_temp (
                    cc_rebill_id int not null,
                    tm_added datetime not null,
                    paysys_id varchar(64),
                    invoice_id int,
                    rebill_date date,
                    status smallint,
                    status_tm datetime,
                    status_msg varchar(255),
                    UNIQUE INDEX(invoice_id, rebill_date))');

                $db->query('
                    INSERT IGNORE INTO ?_cc_rebill_temp
                    SELECT * FROM ?_cc_rebill
                ');
                
                $db->query("TRUNCATE ?_cc_rebill");
                
                $db->query('
                    INSERT INTO ?_cc_rebill
                    SELECT * FROM ?_cc_rebill_temp
                ');
                
                $db->query("DROP TABLE ?_cc_rebill_temp");
            } catch (Exception $e) {
                
            }
        }
    }

    function fillResourceAccessSort()
    {
        $this->getDi()->resourceAccessTable->syncSortOrder();
    }
    
    function manuallyApproveInvoices(){
        if((version_compare($this->db_version, '4.2.4') <0) || 
            ((version_compare(AM_VERSION, '4.2.7')<=0) && !$this->getDi()->config->get('manually_approve_invoice'))
            )
        {
            echo "Manually approve old invoices...";     
            @ob_end_flush();
            
            $this->getDi()->db->query("update ?_invoice set is_confirmed=1");
            echo "Done<br/>\n";
        }
    }
    
    function checkInvoiceItemTotals()
    {
        if (version_compare($this->db_version, '4.1.8') < 0)
        {
            echo "Update invoice_item.total columns...";     
            @ob_end_flush();
            $this->getDi()->db->query("
                UPDATE ?_invoice_item
                SET 
                    first_total = first_price*qty - first_discount + first_shipping + first_tax,
                    second_total = second_price*qty - second_discount + second_shipping + second_tax
                WHERE 
                    ((first_total IS NULL OR first_total = 0) AND first_price > 0)
                OR 
                    ((second_total IS NULL OR second_total = 0) AND second_price > 0)
                ");
            echo "Done<br>\n";
        }
    }
    function convertTax()
    {
        if (version_compare($this->db_version, '4.2.0') < 0)
        {
            echo "Move product.no_tax -> product.tax columns...";     
            @ob_end_flush();
            try {
                $this->getDi()->db->query("
                UPDATE ?_product
                SET tax_group = IF(IFNULL(no_tax, 0) = 0, 0, 1)
                ");
//                $this->getDi()->db->query("ALTER TABLE ?_product DROP no_tax");
            } catch (Am_Exception_Db $e) { } 
            
            echo "Move invoice_item.no_tax -> invoice_item.tax_group columns...";     
            @ob_end_flush();
            try {
               $this->getDi()->db->query("
                UPDATE ?_invoice_item
                SET tax_group = IF(IFNULL(no_tax, 0) = 0, 0, 1)
                ");
//                $this->getDi()->db->query("ALTER TABLE ?_invoice_item DROP no_tax");
            } catch (Am_Exception_Db $e) { } 
            echo "Done<br>\n";
            
            echo "Migrate tax settings..."; 
            if ($this->getDi()->config->get('use_tax'))
            {
                $config = $this->getDi()->config;
                $config->read();
                switch ($this->getDi()->config->get('tax_type'))
                {
                    case 1:
                        $config->set('plugins.tax', array('global-tax'));
                        $config->set('tax.global-tax.rate', $config->get('tax_value'));
                        break;
                    case 2:
                        $config->set('plugins.tax', array('regional')); 
                        $config->set('tax.regional.taxes', $config->get('regional_taxes'));
                        break;
                }
                $arr = $config->getArray();
                unset($arr['tax_type']);
                unset($arr['regional_taxes']);
                unset($arr['tax_value']);
                unset($arr['use_tax']);
                $config->setArray($arr);
                $config->save();
            }
            echo "Done<br>\n";
        }
    }

    function convertAutoresponderPrefix()
    {
        if (version_compare($this->db_version, '4.2.0') < 0)
        {
            echo "Convert Autoresponder Prefix From [emailtemplate] to [email-messages]";
            @ob_end_flush();
            try {
                $rows = $this->getDi()->db->query("
                SELECT * FROM ?_email_template
                WHERE name IN ('autoresponder', 'expire') AND attachments IS NOT NULL
                ");

                $upload_ids = array();
                foreach ($rows as $row) {
                    $upload_ids = array_merge($upload_ids, explode(',', $row['attachments']));
                }
                
                if (count($upload_ids)) {
                    $templates = array();
                    foreach ($upload_ids as $id) {
                        $rows = $this->getDi()->db->query("
                            SELECT * FROM ?_email_template
                            WHERE name NOT IN ('autoresponder', 'expire')
                            AND (attachments=? OR attachments LIKE ?
                            OR attachments LIKE ? OR attachments LIKE ?)",
                            $id,
                            '%,'.$id,
                            $id.',%',
                            '%,'.$id.',%'
                            );
                        $templates = array_merge($templates, $rows);
                    }



                    if (count($templates)) {
                        $names = array();
                        foreach ($templates as $tpl) {
                            $names[] = sprintf('%s [%s]', $tpl['name'], $tpl['lang']);
                        }

                        echo sprintf(' <span style="color:red">Please reupload attachments for the following templates: %s</span><br />',
                            implode(', ', $names));
                    }

                    $this->getDi()->db->query("UPDATE ?_upload SET prefix=? WHERE upload_id IN (?a)",
                        'email-messages', $upload_ids);
                }

            } catch (Am_Exception_Db $e) { }
            echo "Done<br>\n";
        }
    }
    function checkResourceAccessEmailTemplates(){
        if (version_compare($this->db_version, '4.1.14') < 0)
        {
            echo "Update resource access table ...";     
            @ob_end_flush();
            $this->getDi()->db->query("
                    UPDATE ?_resource_access
                    SET 
                    start_days = (SELECT day FROM ?_email_template WHERE email_template_id=resource_id),
                    stop_days = (SELECT day FROM ?_email_template WHERE email_template_id=resource_id)
                    WHERE resource_type = 'emailtemplate' AND fn='free' and start_days IS NULL
                    ");
            echo "Done<br>\n";
            
        }   
    }

    function enableSkipIndexPage() {
        if (version_compare($this->db_version, '4.1.16') < 0)
        {
            echo "Enable skip_index_page option...";
            if (ob_get_level()) ob_end_flush();
            $str = $this->getDi()->db->selectCell("SELECT config FROM ?_config WHERE name = ?", 'default');
            $config = unserialize($str);
            if (!isset($config['skip_index_page'])) {
                $config['skip_index_page'] = 1;
                $this->getDi()->db->selectCol("UPDATE ?_config SET config=? WHERE name = ?", serialize($config), 'default');
            }

            echo "Done<br>\n";

        }
    }

    function addCountryCodes() {
        if (version_compare($this->db_version, '4.2.10') < 0)
        {
            echo "Add country codes...";
            if (ob_get_level()) ob_end_flush();
            $query = file_get_contents(ROOT_DIR . '/setup/sql-country.sql');
            $query = str_replace('@DB_MYSQL_PREFIX@', '?_', $query);
            $this->getDi()->db->query($query);
            echo "Done<br>\n";
        }
    }

    function upgradeFlowPlayerKey() {
        if (version_compare($this->db_version, '4.2.16') < 0)
        {
            echo "Update Flowplayer License Key...";
            if (ob_get_level()) ob_end_flush();
            $request = new Am_HttpRequest('https://www.amember.com/fplicense.php', Am_HttpRequest::METHOD_POST);
            $request->addPostParameter('root_url', $this->getDi()->config->get('root_url'));
            try {
                $response = $request->send();
            } catch (Exception $e) {
                echo "request failed " . $e->getMessage() . "\n<br />";
                return;
            }
            if ($response->getStatus() == 200) {
                $body = $response->getBody();
                $res = Am_Controller::decodeJson($body);
                if ($res['status'] == 'OK' && $res['license'])
                {
                    Am_Config::saveValue('flowplayer_license', $res['license']);
                }
            }
            echo "Done<br>\n";
        }
    }

    function fixCryptSavedPass()
    {
        if (version_compare($this->db_version, '4.2.16') < 0)
        {
            echo "Fix crypt saved pass...";
            if (ob_get_level()) ob_end_flush();
            $this->getDi()->db->query("UPDATE ?_saved_pass SET salt=pass WHERE format=?", 'crypt');
            echo "Done<br>\n";
        }
    }

    function updateStateInfo() {
        if (version_compare($this->db_version, '4.2.16') < 0)
        {
            echo "Update State Info...";
            if (ob_get_level()) ob_end_flush();
            $query = file_get_contents(ROOT_DIR . '/setup/sql-state.sql');
            $query = str_replace('@DB_MYSQL_PREFIX@', '?_', $query);
            $this->getDi()->db->query($query);
            echo "Done<br>\n";
        }
    }
}

