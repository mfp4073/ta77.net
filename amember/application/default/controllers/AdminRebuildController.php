<?php

/**
 * @todo remove NewsletterTable slowdown ! 
 */

class Am_Core_Rebuild
{
    const NEED_REBUILD = '_need_rebuild';
    function getDi()
    {
        return Am_Di::getInstance();
    }
    function getTitle()
    {
        return ___("Core");
    }
    function onRebuild(Am_Event_Rebuild $event)
    {
        // disable htpasswd from hooks if enabled
        foreach (Am_Di::getInstance()->plugins_protect->loadEnabled()->getAllEnabled() as $pl)
        {
            try {
                $pl->destroy();
            } catch (Exception $e) {  }
        }
        ///
        $batch = new Am_BatchProcessor(array($this, 'doWork'), 15);
        $context = $event->getDoneString();
        $batch->run($context) ? $event->setDone() : $event->setDoneString($context);
    }
    function doWork(& $context, Am_BatchProcessor $batch)
    {
        
        if (!strlen($context))
        {
            $changed = Am_Di::getInstance()->userTable->checkAllSubscriptionsFindChanged();
            Am_Di::getInstance()->db->query("DELETE FROM ?_data WHERE `table`='user' AND `key`=?", self::NEED_REBUILD);
            if (!$changed) return;
            Am_Di::getInstance()->db->query("
                INSERT INTO ?_data 
                (`table`, `id`, `key`, `value`)
                SELECT 'user', m.user_id, ?, 1
                FROM ?_user m
                WHERE m.user_id IN (?a)", self::NEED_REBUILD, $changed);
            $context = 0;
            return;
        }
        $pageCount = 10000;
        // now select all changed users from user table and run checkSubscriptions on each
        $q = Am_Di::getInstance()->db->queryResultOnly("
            SELECT m.* 
            FROM ?_user m LEFT JOIN ?_data d ON (d.`table`='user' AND m.user_id=d.`id` AND d.`key`=?)
            WHERE d.value > 0
            LIMIT ?d, ?d", 
            self::NEED_REBUILD, (int)$context, $pageCount);
        $count = 0;
        while ($row = Am_Di::getInstance()->db->fetchRow($q))
        {
            $count++;
            $u = $this->getDi()->userRecord;
            $u->fromRow($row);
            $u->checkSubscriptions(false); // access_cache is batch-updated
            $context++;
            if (!$batch->checkLimits()) return;
        }
        if (!$count) { 
            Am_Di::getInstance()->db->query("DELETE FROM ?_data WHERE `table`='user' AND `key`=?", self::NEED_REBUILD);
            $context = ""; 
            return true; 
        }
    }
}

class Am_Invoice_Rebuild
{
    function getDi()
    {
        return Am_Di::getInstance();
    }
    function getTitle()
    {
        return "Invoices Information";
    }
    function onRebuild(Am_Event_Rebuild $event)
    {
        $batch = new Am_BatchProcessor(array($this, 'doWork'), 15);
        $context = $event->getDoneString();
        $batch->run($context) ? $event->setDone() : $event->setDoneString($context);
    }
    function doWork(& $context, Am_BatchProcessor $batch)
    {
        $pageCount = 5000;
        $q = Am_Di::getInstance()->db->queryResultOnly("
            SELECT *
            FROM ?_invoice
            LIMIT ?d, ?d", 
            (int)$context, $pageCount);
        $count = 0;
        while ($row = Am_Di::getInstance()->db->fetchRow($q))
        {
            $count++;
            $invoice = $this->getDi()->invoiceRecord;
            $invoice->fromRow($row);
            $invoice->updateStatus();
            try {
                $invoice->recalculateRebillDate();
            } catch (Am_Exception_InternalError $e) {}; // ignore error about empty period
            $context++;
            if (!$batch->checkLimits()) return;
        }
        if (!$count) return true; // finished!
    }
}

class AdminRebuildController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    /** @return array id => title */
    function getTargetsList()
    {
        $list = array('core' => ___('Core'), 'invoice' => ___('Invoice'));
        foreach ($this->getDi()->hook->getRegisteredHooks('rebuild') as $hook)
        {
            $hook = $hook->getCallback();
            if (is_array($hook) && is_object($hook[0]))
            {
                $obj = $hook[0];
                $list[$obj->getId()] = $obj->getTitle();
            }
        }
        return $list;
    }
    /** @return callback|null */
    function getTarget($id)
    {
        if ($id == 'core') return array(new Am_Core_Rebuild, 'onRebuild');
        if ($id == 'invoice') return array(new Am_Invoice_Rebuild, 'onRebuild');
        foreach ($this->getDi()->hook->getRegisteredHooks('rebuild') as $hook)
        {
            $hook = $hook->getCallback();
            if (is_array($hook) && is_object($hook[0]))
            {
                $obj = $hook[0];
                if ($obj->getId() == $id) return $hook;
            }
        }
    }
    
    
    function indexAction()
    {
        $plugin_buttons = "";
        foreach ($this->getTargetsList() as $id => $title)
        {
            $plugin_buttons .=
                sprintf('<input class="rebuild-button" type="button" name="%s" value="Rebuild %s Database"/> '.PHP_EOL,
                    $id, $title);
        }
        $this->view->title = "Rebuild Users Database";
        $this->view->content = <<<CUT

<div class="info">Sometimes, after configuration errors and as result of software problems, aMember users database
and third-party scripts databases becomes out of sync. Then you can run rebuild process manually
to get databases fixed.</div>
<form method="post">
    <input type='hidden' name='start' value='core' />
    $plugin_buttons
</form>
<br /><br />
<div id="process" style="width: 70%; height: 20em; overflow: scroll; background-color: white; display: none;">
</div>
<script type="text/javascript">
$(function(){
    var btn;
    function onDataReceived(data)
    {
            /// append retreived data
            $("#process").append(data + "<hr />");
            $("#process").attr({ scrollTop: $("#process").attr("scrollHeight") });
            if (match = data.match(/CONTINUE\((.+)\)$/))
            {
                doPost(match[1]);
            } else { 
                if (data.match(/DONE$/)) {
                    btn.val(btn.val().replace(" ...", "") + " DONE");
                    $(".rebuild-button").prop("disabled", "").removeClass('disabled');
                } else {
                    $("#process").append("<span class=error>Incorrect response received. Stopped!</span>");
                }
            }
    }
    function doPost(doString)
    {
        $.post(window.rootUrl + '/admin-rebuild/do', { 'do' : doString }, onDataReceived);
    }

    $(".rebuild-button").click(function(){
        btn = $(this)
        $("#process").show();
        btn.val(btn.val().replace(" DONE", "")+" ...");
        $(".rebuild-button").prop("disabled", "disabled").addClass('disabled');
        doPost(btn.attr('name'));
    });
});
</script>
CUT;
        $this->view->display('admin/layout.phtml');
    }

    function doAction()
    {
        if (!$this->_request->isPost() || !$this->_request->get('do'))
            throw new Am_Exception_InputError("Wrong request");

        @ignore_user_abort(true);
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');
        
        $do = $this->_request->getFiltered('do');
        @list($do, $doneString) = explode("-", $do, 2);
        $callback = $this->getTarget($do);
        if (!$callback)
            throw new Am_Exception_InputError("Wrong request - plugin [$do] not found");
            
        $this->printStarted($callback[0]->getTitle() . " Db");
        $event = new Am_Event_Rebuild;
        $event->setDoneString($doneString);
        call_user_func($callback, $event);
        $this->printFinished($do, $event);
    }
    function printStarted($what)
    {
        echo "Rebuilding $what...\n\n";
        ob_end_flush(); ob_end_flush();
    }
    function printFinished($plugin, Am_Event_Rebuild $event)
    {
        if ($event->needContinue())
            echo "CONTINUE($plugin-".htmlentities($event->getDoneString()).")";
        else    
            echo "DONE";
    }
}