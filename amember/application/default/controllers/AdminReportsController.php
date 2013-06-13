<?php

/*
 * @todo more reports!
 * @todo check Am_Query_Quant - compare to mysql calcs check boundaries
 * @todo one page report layout
 *      choose report in scrollable radio list
 *      [ajax loadable form or pre-selected pre-filled form if report chosen]
 *      [[the report output]]
 * 
 * @todo choose date from pre-selected constants
 * @todo save last used reports in admin profile
 *
 *     Author: Alex Scott
 *      Email: alex@cgi-central.net
 *        Web: http://www.cgi-central.net
 *    Details: Admin index
 *    FileName $RCSfile$
 *    Release: 4.2.17 ($Revision$)
 *
 * Please direct bug reports,suggestions or feedback to the cgi-central forums.
 * http://www.cgi-central.net/forum/
 *
 * aMember PRO is a commercial software. Any distribution is strictly prohibited.
 *
 */


/** Plugin can load own report classes when it is called */
class AdminReportsController_Index extends Am_Controller
{

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_REPORT);
    }

    public function saveAction()
    {
        $savedReport = $this->getDi()->savedReportRecord;
        $savedReport->setForInsert(array(
            'request' => $this->getRequest()->getParam('request'),
            'title' => $this->getRequest()->getParam('title'),
            'report_id' => $this->getRequest()->getParam('report_id'),
            'admin_id' => $this->getDi()->authAdmin->getUser()->pk()));
        $savedReport->save();
        if ($this->getRequest()->getParam('add-to-dashboard')) {
            $pref_default = array(
                'top' => array(),
                'bottom' => array(),
                'main' => array('users'),
                'aside' => array('sales')
            );

            $pref = $this->getDi()->authAdmin->getUser()->getPref(Admin::PREF_DASHBOARD_WIDGETS);
            $pref = is_null($pref) ? $pref_default : $pref;
            $pref['main'][] = 'saved-report-' . $savedReport->pk();
            $this->getDi()->authAdmin->getUser()->setPref(Admin::PREF_DASHBOARD_WIDGETS, $pref);
        }
        $this->ajaxResponse(array(
            'status' => 'OK'
        ));
    }

    function runAction()
    {
        if (!$this->_request->isPost())
            throw new Am_Exception_InputError('Only POST accepted');
        $reportId = $this->getFiltered('report_id');
        if (!$reportId)
            throw new Am_Exception_InternalError("Empty report id passed");
        $r = Am_Report_Abstract::createById($reportId);
        $r->applyConfigForm($this->_request);
        $this->view->form = $r->getForm();
        $this->view->report = $r;

        if (!$r->hasConfigErrors())
        {
            $this->view->serializedRequest = serialize($this->_request->toArray());
            $this->view->reportId = $reportId;
            $this->view->saveReportForm = $this->createSaveReportForm($r->getTitle());

            $result = $r->getReport();
            foreach ($r->getOutput($result) as $output)
                $this->view->content .= $output->render() . "<br /><br />";
            // default
            $default = $r->getForm()->getValue();
            unset($default['_save_']);
            unset($default['save']);
            $this->getSession()->reportDefaults = $default;
        }
        $this->view->display('admin/report_output.phtml');
    }

    function indexAction()
    {
        $reports = Am_Report_Abstract::getAvailableReports();
        $defaults = @$this->getSession()->reportDefaults;
        if ($defaults)
        {
            foreach ($reports as $r)
            {
                $r->getForm()->setDataSources(array(new HTML_QuickForm2_DataSource_Array($defaults)));
            }
        }
        $this->view->assign('reports', $reports);
        $this->view->display('admin/report.phtml');
    }

    function savefrequencyAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->getDi()->authAdmin->getUser()->setPref(Admin::PREF_REPORTS_SEND_FREQUENCY, $this->getRequest()->getParam('fr'));
        }
    }

    function createSaveReportForm($title)
    {
        $form = new Am_Form_Admin();
        $form->addText('title', array('style'=>'width:90%'))
            ->setLabel(___('Title of Report for your Reference'))
            ->setValue($title)
            ->addRule('required');
        $form->addAdvCheckbox('add-to-dashboard')
            ->setLabel(___('Add Report to My Dashboard'))
            ->setValue(1);

        return $form;
    }
}

class AdminReportsController_Saved extends Am_Controller_Grid {
    protected $layout = null;


    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_REPORT);
    }

    function createGrid()
    {
        $ds = new Am_Query($this->getDi()->savedReportTable);
        $ds->addWhere('admin_id=?', $this->getDi()->authAdmin->getUserId());

        $grid = new Am_Grid_Editable('_report', ___('Saved Reports'), $ds, $this->_request, $this->view);
        $grid->setPermissionId(Am_Auth_Admin::PERM_REPORT);
        $grid->addField(new Am_Grid_Field('title', ___('Title'), true));
        $grid->actionsClear();
        $grid->actionAdd(new Am_Grid_Action_LiveEdit('title'));
        $grid->actionAdd(new Am_Grid_Action_Url('run-report', 'Run Report', '__ROOT__/default/admin-reports/p/saved/runsaved/report_id/__ID__'))->setTarget('_top');
        $grid->actionAdd(new Am_Grid_Action_Delete());
        $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_TABLE, array($this, 'onRenderGridContent'));
        return $grid;
    }

    function runsavedAction()
    {
        $reportId = $this->getFiltered('report_id');
        if (!$reportId)
            throw new Am_Exception_InternalError("Empty report id passed");

        $report = $this->getDi()->savedReportTable->load($reportId);
        if ($report->admin_id != $this->getDi()->authAdmin->getUserId())
            throw new Am_Exception_AccessDenied();

        $r = Am_Report_Abstract::createById($report->report_id);
        $r->applyConfigForm(new Am_Request(unserialize($report->request)));
        $result = $r->getReport();
        $content = '';
        foreach ($r->getOutput($result) as $output)
            $content .= $output->render() . "<br /><br />";

        $this->view->enableReports();
        echo sprintf('<h1>%s</h1> %s', $this->escape($report->title), $content);
    }

    function onRenderGridContent(& $out)
    {
        $email = $this->escape($this->getDi()->authAdmin->getUser()->email);
        $txt = $this->escape(___('Send reports to my email'));
        $options = array(
            '' => ___('Never'),
            Am_Event::DAILY => ___('Daily'),
            Am_Event::WEEKLY => ___('Weekly'),
            Am_Event::MONTHLY => ___('Monthly')
        );

        $optionsHtml  = $this->renderOptions($options, $this->getDi()->authAdmin->getUser()->getPref(Admin::PREF_REPORTS_SEND_FREQUENCY));

        $html = <<<CUT
<div> $txt (<strong>$email</strong>)
<select id="reports-send-frequency">
 {$optionsHtml}
</select>
</div>
<script type="text/javascript">
<!--
$('#reports-send-frequency').change(function(){
    $.post(window.rootUrl + '/default/admin-reports/p/index/savefrequency', {'fr' : $(this).val()}, function(){
        flashMessage('Preference has been updated');
    })
})
-->
</script>
CUT;
        $out .= $html;

    }
}

class AdminReportsController extends Am_Controller_Pages {
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_REPORT);
    }

    public function preDispatch()
    {
        class_exists('Am_Report', true);
        require_once 'Am/Report/Standard.php';
    }

    public function initPages() {
        $this->addPage(array($this, 'createIndexController'), 'index', ___('Reports'))
            ->addPage(array($this, 'createSavedController'), 'saved', ___('Saved Reports'));
    }

    public function createIndexController($id, $title, Am_Controller $controller) {
        return new AdminReportsController_Index($controller->getRequest(), $controller->getResponse(), $this->_invokeArgs);
    }

    public function createSavedController($id, $title, Am_Controller $controller) {
        return new AdminReportsController_Saved($controller->getRequest(), $controller->getResponse(), $this->_invokeArgs);
    }
}