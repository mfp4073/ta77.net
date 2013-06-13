<?php

class AdminController extends Am_Controller
{
    public function checkAdminPermissions(Admin $admin)
    {
        return (bool) $admin;
    }

    public function preDispatch()
    {
        $db_version = $this->getDi()->store->get('db_version');
        if (empty($db_version))
        {
            $this->getDi()->store->set('db_version', AM_VERSION);
        }
        elseif ($db_version != AM_VERSION)
        {
            $this->redirectLocation(REL_ROOT_URL . '/admin-upgrade-db');
        }
        parent::preDispatch();
    }

    function getDefaultWidgets()
    {
        return array(
            new Am_Widget('users', ___('Last Users List'), array($this, 'renderWidgetUsers'), Am_Widget::TARGET_ANY, array($this, 'createWidgetUsersConfigForm'), 'grid_u'),
            new Am_Widget('payments', ___('Last Payments List'), array($this, 'renderWidgetPayments'), Am_Widget::TARGET_ANY, array($this, 'createWidgetPaymentsConfigForm'), 'grid_payment'),
            new Am_Widget('report-users', ___('Users Report'), array($this, 'renderWidgetReportUsers'), Am_Widget::TARGET_ANY, null, Am_Auth_Admin::PERM_REPORT),
            new Am_Widget('sales', ___('Sales Statistic'), array($this, 'renderWidgetSales'), Am_Widget::TARGET_ANY, array($this, 'createWidgetSalesConfigForm'), Am_Auth_Admin::PERM_REPORT),
            new Am_Widget('invoices', ___('Last Invoices List'), array($this, 'renderWidgetInvoices'), Am_Widget::TARGET_ANY, array($this, 'createWidgetInvoicesConfigForm'), Am_Auth_Admin::PERM_REPORT),
        );
    }

    function getSavedReportWidgets()
    {
        $res = array ();
        foreach ($this->getDi()->savedReportTable->findByAdminId($this->getDi()->authAdmin->getUser()->pk()) as $report) {
            $res[] = new Am_Widget('saved-report-' . $report->pk(),
                $report->title, array($this, 'renderWidgetReport'), Am_Widget::TARGET_ANY,
                array($this, 'createWidgetReportConfigForm'), Am_Auth_Admin::PERM_REPORT,
                array('savedReport' => $report));
        }
        return $res;
    }

    function getAvailableWidgets()
    {
        $event = new Am_Event(Am_Event::LOAD_ADMIN_DASHBOARD_WIDGETS);
        $this->getDi()->hook->call($event);
        
        $widgets = array();
        foreach (array_merge($this->getDefaultWidgets(),
                    $this->getSavedReportWidgets(),
                    $event->getReturn()) as $widget) {
            
            $widgets[$widget->getId()] = $widget;
        }

        return $widgets;
    }

    /**
     * Retrieve widget by id
     *
     * @param string $id
     * @return Am_Widget
     */
    function getWidget($id)
    {
        $availableWidgets = $this->getAvailableWidgets();
        return isset($availableWidgets[$id])  ? $availableWidgets[$id] : null;
    }

    function customizeDashboardAction()
    {
        $widgets = $this->getAvailableWidgets();
        foreach ($widgets as $k => $widget)
        {
            if (!$widget->hasPermission($this->getDi()->authAdmin->getUser()))
            {
                unset($widgets[$k]);
            }
        }
        $this->view->widgets = $widgets;
        $this->view->config = $this->getWidgetConfig();
        $this->view->pref = $this->getPref();
        $this->view->display('admin/customize.phtml');
    }

    function getWidgetConfigFormAction()
    {
        $id = $this->getRequest()->getParam('id');
        $widget = $this->getWidget($id);
        if (!$widget) throw new Am_Exception_InputError(sprintf('Unknown Widget with Id [%s]', $id));
        if (!$widget->hasConfigForm()) throw new Am_Exception_InputError(sprintf('Widget with Id [%s] has not config form', $id));

        $form = $widget->getConfigForm();
        $config = $this->getWidgetConfig($widget->getId());
        if ($config) {
            $form->setDataSources(array(
                new HTML_QuickForm2_DataSource_Array($config)
            ));
        }

        echo $form;
    }

    protected function getPref()
    {
        $pref_default = array(
            'top' => array(),
            'bottom' => array(),
            'main' => array('users'),
            'aside' => array('sales')
        );

        $pref = $this->getDi()->authAdmin->getUser()->getPref(Admin::PREF_DASHBOARD_WIDGETS);
        return is_null($pref) ? $pref_default : $pref;
    }

    protected function getWidgetConfig($widget_id = null)
    {
        $config = $this->getDi()->authAdmin->getUser()->getPref(Admin::PREF_DASHBOARD_WIDGETS_CONFIG);

        if (is_null($widget_id)) return $config;
        return isset($config[$widget_id]) ? $config[$widget_id] : null;
    }

    function saveDashboardAction()
    {
        if ($this->getRequest()->isPost())
        {
            $this->getDi()->authAdmin->getUser()->setPref(Admin::PREF_DASHBOARD_WIDGETS, $this->getRequest()->getParam('pref', array()));
        }
    }

    function saveDashboardConfigAction()
    {
        if ($this->getRequest()->isPost()) {

            /* @var $widget Am_Widget */
            $widget = $this->getWidget($this->getRequest()->getParam('id'));
            /* @var $form Am_Form */
            $form = $widget->getConfigForm();
            if (!$form)
                throw new Am_Exception_InputError(sprintf('Can not save config for dashboard widget without config form [%s]',
                    $this->getRequest()->getParam('id')));

            $form->setDataSources(array($this->getRequest()));

            $config = $this->getDi()->authAdmin->getUser()->getPref(Admin::PREF_DASHBOARD_WIDGETS_CONFIG, array());
            if ($form->validate()) {

                $vars = $form->getValue();
                unset($vars['id']);
                $config[$widget->getId()] = $vars;

                $this->getDi()->authAdmin->getUser()->setPref(Admin::PREF_DASHBOARD_WIDGETS_CONFIG, $config);

                $html = $widget->render($this->view, $config[$widget->getId()]);
                $this->ajaxResponse(array(
                    'status' => 'OK',
                    'html' => $html,
                    'id' => $widget->getId()
                ));
            } else {
                $this->ajaxResponse(array(
                    'status' => 'ERROR',
                    'html' => (string)$form,
                    'id' => $widget->getId()
                ));
            }
        }
    }



    public function renderWidgetReport(Am_View $view, $config=null, $invokeArgs = array())
    {
        require_once 'Am/Report.php';
        require_once 'Am/Report/Standard.php';
        $view->enableReports();

        /* @var $savedReport SavedReport */
        $savedReport = $invokeArgs['savedReport'];

        $request = new Am_Request(unserialize($savedReport->request));

        $r = Am_Report_Abstract::createById($savedReport->report_id);
        $r->applyConfigForm($request);

        $result = $r->getReport();
        $result->setTitle($savedReport->title);

        $type = is_null($config) ? 'graph-line' : $config['type'];

        switch ($type) {
            case 'graph-line' :
                $output = new Am_Report_Graph_Line($result);
                $output->setSize('100%', 250);
                break;
            case 'graph-bar' :
                $output = new Am_Report_Graph_Bar($result);
                $output->setSize('100%', 250);
                break;
            case 'table' :
                $output = new Am_Report_Table($result);
                break;
            default :
                throw new Am_Exception_InputError('Unknown report display type [%s]', $type);
        }
        
        return sprintf('<div class="admin-index-report report-%s">%s</div>', $savedReport->report_id, $output->render());
    }

    public function createWidgetReportConfigForm() {
        $form = new Am_Form_Admin();
        $form->addSelect('type')
            ->setLabel(___('Display Type'))
            ->setValue('graph-line')
            ->loadOptions(array(
                'graph-line' => ___('Graph Line'),
                'graph-bar' => ___('Graph Bar'),
                'table' => ___('Table')
             ));

        return $form;
    }

    public function renderWidgetUsers(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/users.phtml');
    }

    public function createWidgetUsersConfigForm() {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Users to display'))
            ->setValue(5);

        return $form;
    }

    public function renderWidgetPayments(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        return $view->render('admin/widget/payments.phtml');
    }
    
    public function renderWidgetInvoices(Am_View $view, $config=null)
    {
        $view->num = is_null($config) ? 5 : $config['num'];
        $view->statuses = $config['statuses'];
        return $view->render('admin/widget/invoices.phtml');
    }

    public function createWidgetPaymentsConfigForm() {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Payments to display'))
            ->setValue(5);

        return $form;
    }

    public function createWidgetInvoicesConfigForm() {
        $form = new Am_Form_Admin();
        $form->addInteger('num')
            ->setLabel(___('Number of Invoices to display'))
            ->setValue(5);
        $form->addMagicselect('statuses')
            ->setLabel(___('Show invoices with selected statuses') . "\n" . ___('leave it empty in case if you want to show all invoices'))
            ->loadOptions(Invoice::$statusText);
        return $form;
    }
    
    public function renderWidgetReportUsers(Am_View $view)
    {
        $view->enableReports();
        $view->report = $this->getReportUsers();
        return $view->render('admin/widget/report-users.phtml');
    }

    protected function getReportUsers()
    {
        require_once 'Am/Report.php';
        require_once 'Am/Report/Standard.php';
        $res = $this->getDi()->db->select("SELECT status as ARRAY_KEY, COUNT(*) as `count`
            FROM ?_user
            GROUP BY status");
        $total = array_sum($res);
        for ($i = 0; $i <= 2; $i++)
            $res[$i]['count'] = (int) @$res[$i]['count'];
        $active_paid = $this->getDi()->db->selectCell("
            SELECT COUNT(DISTINCT p.user_id) AS active
            FROM ?_invoice_payment p 
                INNER JOIN ?_user u USING (user_id)
            WHERE u.status = 1
        ");
        $active_free = $res[1]['count'] - $active_paid;
        $result = new Am_Report_Result;
        $result->setTitle("Users Breakdown");
        $result->addPoint(new Am_Report_Point(0, "Pending"))->addValue(0, (int) $res[0]['count']);
        $result->addPoint(new Am_Report_Point(1, "Active"))->addValue(0, (int) $active_paid);
        $result->addPoint(new Am_Report_Point(4, "Active(free)"))->addValue(0, (int) $active_free);
        $result->addPoint(new Am_Report_Point(2, "Expired"))->addValue(0, (int) $res[2]['count']);
        $result->addLine(new Am_Report_Line(0, "# of users"));

        $output = new Am_Report_Graph_Bar($result);
        $output->setSize('100%', 250);
        return $output;
    }

    public function renderWidgetSales(Am_View $view, $config = null)
    {
        $intervals = is_null($config) ? array(Am_Interval::PERIOD_TODAY) : (array)$config['interval'];
        $out = '';
        foreach ($intervals as $interval) {
            list($start, $stop) = $this->getDi()->interval->getStartStop($interval);

            $view->start = $start;
            $view->stop = $stop;
            $view->reportTitle = $this->getDi()->interval->getTitle($interval);
            $view->controller = $this;
            $out .= $view->render('admin/widget/sales.phtml');
        }
        return $out;
    }

    public function createWidgetSalesConfigForm() {
        $form = new Am_Form_Admin();
        $form->addMagicSelect('interval')
            ->setLabel(___('Period'))
            ->setValue(array(Am_Interval::PERIOD_TODAY))
            ->loadOptions($this->getDi()->interval->getOptions());

        return $form;
    }

    function getSalesStats($start, $stop)
    {
        $row = $this->getDi()->db->selectRow("
            SELECT
                COUNT(*) AS cnt,
                SUM(amount) AS total
            FROM ?_invoice_payment
            WHERE dattm BETWEEN ? AND ?
            ", sqlTime(strtotime($start)), sqlTime(strtotime($stop)));
        return array((int) $row['cnt'], moneyRound($row['total']));
    }

    function getCancelsStats($start, $stop)
    {
        return $this->getDi()->db->selectCell("
            SELECT COUNT(*)
            FROM ?_invoice
            WHERE tm_cancelled BETWEEN ? AND ?
            ", sqlTime(strtotime($start)), sqlTime(strtotime($stop)));
    }

    function getPlannedRebills($start, $stop)
    {
        $row = $this->getDi()->db->selectRow("
            SELECT
                COUNT(*) AS cnt,
                SUM(second_total) AS total
            FROM ?_invoice
            WHERE rebill_date BETWEEN DATE(?) AND DATE(?)
            AND tm_cancelled IS NULL
            ", sqlTime(strtotime($start)), sqlTime(strtotime($stop)));
        return array((int) $row['cnt'], moneyRound($row['total']));
    }

    function getSignupsCount($start, $stop)
    {
        return $this->getDi()->db->selectCell("
            SELECT
                COUNT(*) AS cnt
            FROM ?_user
            WHERE added BETWEEN ? AND ?
            ", sqlTime(strtotime($start)), sqlTime(strtotime($stop)));
    }

    function getErrorLogCount()
    {
        $time = $this->getDi()->time;
        $tm = date('Y-m-d H:i:s', $time - 24 * 3600);
        return $this->getDi()->db->selectCell(
            "SELECT COUNT(*)
            FROM ?_error_log
            WHERE dattm BETWEEN ? AND ?",
            $tm, $this->getDi()->sqlDateTime);
    }

    function getAccessLogCount()
    {
        $tm = date('Y-m-d H:i:s', $this->getDi()->time - 24 * 3600);
        return $this->getDi()->db->selectCell(
            "SELECT COUNT(log_id)
            FROM ?_access_log
            WHERE dattm BETWEEN ? AND ?",
            $tm,
            $this->getDi()->sqlDateTime);
    }

    function getWarnings()
    {
        $warn = array();
        $setupUrl = REL_ROOT_URL . "/admin-setup";

        if (!$this->getDi()->config->get('maintenance'))
        {
            // cron run
            $t = Am_Cron::getLastRun();
            $diff = time() - $t;
            $tt = $t ? ('at ' . amTime($t)) : "NEVER (oops! no records that it has been running at all!)";
            if ($diff > 24 * 3600)
                $warn[] = "Cron job has been running last time $tt, it is more than 24 hours before.<br />
                Most possible external cron job has been set incorrectly. It may cause very serious problems with the script.<br />
                You can find info how to set up cron job for your installation <a href=\"http://www.amember.com/docs/Cron\" target=\"_blank\">here</a>.";
        }
        ////
        if (!$this->getDi()->productTable->count())
            $warn[] = "You have not added any products, your signup forms will not work until you <a href='admin-products'>add at least one product</a>";


        
        // Check for not approved users. 
        if($this->getDi()->config->get('manually_approve'))
        {
            $na_users = $this->getDi()->db->selectCell('select count(*) from ?_user where is_approved<1');
            if($na_users)
            {
                $warn[] = sprintf(
                    ___('Number of users who require approval: %d. %sClick here%s to review these users.'), 
                    $na_users, 
                    '<a href="'.REL_ROOT_URL.'/admin-users?_u_search[field-is_approved][val]=0">',
                    '</a>'
                    );
            }
        }

        // Check for not approved invoices. 
        if($this->getDi()->config->get('manually_approve_invoice'))
        {
            $na_invoices = $this->getDi()->db->selectCell('select count(*) from ?_invoice where status=?', Invoice::NOT_CONFIRMED);
            if($na_invoices)
            {
                $warn[] = sprintf(
                    ___('Number of invoices which require approval: %d. %sClick here%s to review these invoices.'), 
                    $na_invoices, 
                    '<a href="'.REL_ROOT_URL.'/default/admin-payments/p/not-approved/index">',
                    '</a>'
                    );
            }
        }
        // @todo email_queue_enabled enabled without external_cron

        // load all plugins
        try
        {
            foreach ($this->getDi()->plugins as $m)
                $m->loadEnabled();
        }
        catch (Exception $e)
        {
            
        }

        $event = $this->getDi()->hook->call(Am_Event::ADMIN_WARNINGS);
        $warn = array_merge($warn, $event->getReturn());

        // return
        return $warn;
    }

    function hasPermissions($perm, $priv = null)
    {
        return $this->getDi()->authAdmin->getUser()->hasPermission($perm);
    }

    function showQuickstart()
    {
        return!$this->getDi()->config->get('quickstart-disable');
    }

    function disableQuickstartAction()
    {
        Am_Config::saveValue('quickstart-disable', true);
        $this->getDi()->config->set('quickstart-disable', true);
        return $this->indexAction();
    }

    function indexAction()
    {
        $widgets = array(
            'top' => array(),
            'bottom' => array(),
            'main' => array(),
            'aside' => array()
        );

        $pref = $this->getPref();
        $availableWidgets = $this->getAvailableWidgets();

        foreach ($pref as $target => $enabledWidgets)
        {
            foreach ($enabledWidgets as $id)
            {
                if (isset($availableWidgets[$id]) && $availableWidgets[$id]->hasPermission($this->getDi()->authAdmin->getUser()))
                {
                    $widgets[$target][] = $availableWidgets[$id];
                }
            }
        }
        $this->view->widgets = $widgets;
        $this->view->config = $this->getWidgetConfig();

        $this->view->showQuickstart = $this->showQuickstart();
        $this->view->warnings = $this->getWarnings();
        $this->view->display('admin/index.phtml');
    }

}