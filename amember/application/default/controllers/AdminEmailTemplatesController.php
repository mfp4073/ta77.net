<?php

/*
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

class AdminEmailTemplatesController extends Am_Controller
{

    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission(Am_Auth_Admin::PERM_EMAIL);
    }

    public function deleteAction()
    {
        $this->getDi()->emailTemplateTable->load($this->getRequest()->getParam('id'))->delete();
        if (!$this->getRequest()->isAjax())
        {
            $this->_redirect($this->getRequest()->getParam('b'), array('prependBase' => false));
        }
    }

    protected function getDefaultNotificationTemplate($name)
    {
        $txt = '';

        if ($name == 'pending_to_user') {
            $txt = <<<CUT
Dear %user.name_f% %user.name_l%,

Thank you for signup. Your payment status is PENDING.
Please complete payment as described.

   Your User ID: %user.login%

Your may log-on to your member pages at:
%root_url%/member
and check your subscription status.

Best Regards,
Site Team
CUT;
        }

        return array(
            'subject' => 'Pending Payment',
            'txt' => $txt,
            'format' => 'text'
        );
    }

    public function editPendingNotificationRuleAction()
    {
        $form = $this->createPendingNotificationRulesForm();
        $form->addHidden('id')
            ->setValue($this->getRequest()->getParam('id'));
        $tpl = $this->getDi()->emailTemplateTable->load($this->getParam('id'));
        if ($form->isSubmitted())
        {
            $form->setDataSources(array(
                $this->getRequest()
            ));
        }
        else
        {
            $form->setDataSources(array(
                new HTML_QuickForm2_DataSource_Array(
                    array(
                        'attachments' => $this->prepareAttachments($tpl->attachments, $isReverse = true),
                        'conditions' => $this->prepareAttachments($tpl->conditions, $isReverse = true),
                        'days' => $this->prepareAttachments($tpl->days, $isReverse = true),
                    ) +
                    $tpl->toArray()
                )
            ));
        }

        if ($form->isSubmitted() && $form->validate())
        {
            $vars = $form->getValue();
            unset($vars['label']);

            $tpl->setForUpdate($vars);
            $tpl->conditions = $this->prepareAttachments($this->getParam('conditions'));
            $tpl->attachments = $this->prepareAttachments($this->getParam('attachments'));
            $days = $this->getParam('days');
            sort($days);
            $tpl->days = $this->prepareAttachments($days);
            $tpl->save();
            $el = new Am_Form_Element_PendingNotificationRules($tpl->name);
            $el->setLabel($this->getRequest()->getParam('label'));
            $this->ajaxResponse(array(
                'content' => (string) $el
            ));
        }
        else
        {
            echo $form;
        }
    }

    public function addPendingNotificationRuleAction()
    {
        $form = $this->createPendingNotificationRulesForm();
        if ($form->isSubmitted())
        {
            $form->setDataSources(array(
                $this->getRequest()
            ));
        }
        else
        {
            $form->setDataSources(array(
                new HTML_QuickForm2_DataSource_Array($this->getDefaultNotificationTemplate($this->getRequest()->getParam('name')))
            ));
        }

        if ($form->isSubmitted() && $form->validate())
        {
            $vars = $form->getValue();
            unset($vars['label']);
            $tpl = $this->getDi()->emailTemplateRecord;

            $tpl->setForInsert($vars);
            $tpl->conditions = $this->prepareAttachments($this->getParam('conditions'));
            $tpl->attachments = $this->prepareAttachments($this->getParam('attachments'));
            $days = $this->getParam('days');
            sort($days);
            $tpl->days = $this->prepareAttachments($days);
            $tpl->save();
            $el = new Am_Form_Element_PendingNotificationRules($tpl->name);
            $el->setLabel($this->getParam('label'));
            $this->ajaxResponse(array(
                'content' => (string) $el
            ));
        }
        else
        {
            echo $form;
        }
    }

    protected function createPendingNotificationRulesForm()
    {
        $form = new Am_Form_Admin('EmailTemplate');

        $form->addElement(new Am_Form_Element_Html('info'))
            ->setLabel(___('Template'))
            ->setHtml(
                sprintf('<div><strong>%s</strong><br /><small>%s</small></div>', $this->escape($this->getParam('name')), $this->escape($this->getParam('label'))
                )
        );

        $form->addElement('hidden', 'name');

//        $langOptions = $this->getLanguageOptions(
//                                $this->getDi()->config->get('lang.enabled',
//                                    array($this->getDi()->config->get('lang.default', 'en')))
//                        );
//        /* @var $lang HTML_QuickForm2_Element */
//        $lang = $form->addElement('select', 'lang')
//                ->setId('lang')
//                ->setLabel(___('Language'))
//                ->loadOptions($langOptions);
//        if (count($langOptions)==1) $lang->toggleFrozen(true);
//        $lang->addRule('required');

        $form->addMagicSelect('days')
            ->setLabel(___('Days to Send'))
            ->loadOptions($this->getDayOptions());

        $form->addMagicSelect('conditions')
            ->setLabel(___('Optional Conditions (By Product)') . "\n" . ___('notification will be sent in case of one of selected products exits in invoice'))
            ->loadOptions($this->getConditionProductOptions());

        $form->addMagicSelect('conditions')
            ->setLabel(___('Optional Conditions (By Paysystem)') . "\n" . ___('notification will be sent in case of one of selected payment system was used for invoice'))
            ->loadOptions($this->getConditionPaysysOptions());

        $body = $form->addElement(new Am_Form_Element_MailEditor($this->getParam('name'), array('upload-prefix'=>'email-pending')));

        $form->addElement('hidden', 'label')
            ->setValue($this->getRequest()->getParam('label'));

        return $form;
    }

    public function editAction()
    {

        if (!$this->getParam('name'))
            throw new Am_Exception_InputError('Name of template is undefined');

        $form = $this->createForm();
        $tpl = $this->getTpl($this->getParam('copy_from', null));

        if ($form->isSubmitted())
        {
            $form->setDataSources(array(
                $this->getRequest()
            ));
        }
        else
        {
            $form->setDataSources(array(
                new HTML_QuickForm2_DataSource_Array(
                    array(
                        'attachments' => $this->prepareAttachments($tpl->attachments, $isReverse = true),
                        'conditions' => $this->prepareAttachments($tpl->conditions, $isReverse = true),
                        'days' => $this->prepareAttachments($tpl->days, $isReverse = true),
                    ) +
                    $tpl->toArray()
                )
            ));
        }

        if ($form->isSubmitted() && $form->validate())
        {
            $vars = $form->getValue();
            unset($vars['label']);
            $tpl->isLoaded() ? $tpl->setForUpdate($vars) : $tpl->setForInsert($vars);
            $tpl->conditions = $this->prepareAttachments($this->getParam('conditions'));
            $tpl->attachments = $this->prepareAttachments($this->getParam('attachments'));
            $tpl->save();
        }
        else
        {
            echo $this->createActionsForm($tpl)
            . "\n"
            . $form
            . "\n"
            . $this->getJs(!$tpl->isLoaded());
        }
    }

    protected function getDayOptions()
    {

        $options = array(
            '0' => 'Immediately',
            '1' => 'Next Day'
        );
        for ($i = 2; $i <= 40; $i++)
            $options[$i] = $this->getNumberString($i) . ' day';

        return $options;
    }

    protected function getNumberString($i)
    {
        switch ($i)
        {
            case 2 :
                return $i . 'nd';
                break;
            case 3 :
                return $i . 'rd';
                break;
            default :
                return $i . 'th';
        }
    }

    protected function getConditionProductOptions()
    {
        $product_options = array();
        foreach (Am_Di::getInstance()->productTable->getOptions() as $id => $title)
        {
            $product_options['PRODUCT-' . $id] = 'Product: ' . Am_Controller::escape(___($title));
        }

        $group_options = array();
        foreach (Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions() as $id => $title)
        {
            $group_options['CATEGORY-' . $id] = 'Product Category: ' . Am_Controller::escape(___($title));
        }

        $options = array(
            ___('Products') => $product_options
        );
        if (count($group_options))
        {
            $options[___('Product Categories')] = $group_options;
        }

        return $options;
    }

    protected function getConditionPaysysOptions()
    {
        $options = array();
        foreach ($this->getDi()->paysystemList->getAllPublic() as $ps)
        {
            $options['PAYSYSTEM-' . $ps->getId()] = Am_Controller::escape(___($ps->getTitle()));
        }

        return $options;
    }

    protected function getTpl($copy_from = null)
    {
        if ($copy_from)
            return $this->getCopiedTpl($copy_from);

        $tpl = $this->getDi()->emailTemplateTable->getExact(
                $this->getParam('name'), $this->getParam('lang', $this->getDefaultLang()), $this->getParam('day', null)
        );

        if (!$tpl)
        {
            $tpl = $this->getDi()->emailTemplateRecord;
            $tpl->name = $this->getParam('name');
            $tpl->lang = $this->getParam('lang', $this->getDefaultLang());
            $tpl->subject = $this->getParam('name');
            $tpl->day = $this->getParam('day', null);
            $tpl->format = 'text';
            $tpl->plain_txt = null;
            $tpl->txt = null;
            $tpl->attachments = null;
        }

        return $tpl;
    }

    protected function getCopiedTpl($copy_from)
    {
        $sourceTpl = $this->getDi()->emailTemplateTable->getExact(
                $this->getParam('name'), $copy_from
        );

        if (!$sourceTpl)
        {
            throw new Am_Exception_InputError('Trying to copy from unexisting template : ' . $copy_from);
        }

        $sourceTpl->lang = $this->getParam('lang', $this->getDefaultLang());

        return $sourceTpl;
    }

    protected function createForm()
    {
        $form = new Am_Form_Admin('EmailTemplate');

        $form->addElement(new Am_Form_Element_Html('info'))
            ->setLabel(___('Template'))
            ->setHtml(
                sprintf('<div><strong>%s</strong><br /><small>%s</small></div>', $this->escape($this->getParam('name')), $this->escape($this->getParam('label'))
                )
        );

        $form->addElement('hidden', 'name');

        $langOptions = $this->getLanguageOptions(
                $this->getDi()->config->get('lang.enabled',
                    array($this->getDi()->config->get('lang.default', 'en')))
        );
        /* @var $lang HTML_QuickForm2_Element */
        $lang = $form->addElement('select', 'lang')
                ->setId('lang')
                ->setLabel(___('Language'))
                ->loadOptions($langOptions);
        if (count($langOptions) == 1)
            $lang->toggleFrozen(true);
        $lang->addRule('required');

        $body = $form->addElement(new Am_Form_Element_MailEditor($this->getParam('name')));

        $form->addElement('hidden', 'label')
            ->setValue($this->getParam('label'));

        return $form;
    }

    protected function createActionsForm(EmailTemplate $tpl)
    {
        $form = new Am_Form_Admin('EmailTemplate_Actions');

        $form->addElement('hidden', 'name')
            ->setValue($tpl->name);

        $langOptions = $this->getLanguageOptions(
                $this->getDi()->emailTemplateTable->getLanguages(
                    $tpl->name, null, $tpl->lang
                )
        );

        if (count($langOptions))
        {
            $lang_from = $form->addElement('select', 'copy_from')
                    ->setId('another_lang')
                    ->setLabel(___('Copy from another language'))
                    ->loadOptions(array('0' => '--' . ___('Please choose') . ' --') + $langOptions)
                    ->setValue(0);
        }

        if (isset($tpl->lang) && $tpl->lang)
        {
            $form->addElement('hidden', 'lang')
                ->setValue($tpl->lang);
        }

        $form->addElement('hidden', 'label')
            ->setValue($this->getParam('label'));

        //we do not show action's form if there is not any avalable action
        if (!count($langOptions))
        {
            $form = null;
        }

        return $form;
    }

    protected function prepareAttachments($att, $isReverse = false)
    {
        if ($isReverse)
        {
            return (!($att == '' || is_null($att)) ? explode(',', $att) : array());
        }
        else
        {
            return (is_array($att) ? implode(',', $att) : null);
        }
    }

    protected function getLanguageOptions($languageCodes)
    {
        $languageNames = $this->getDi()->languagesListUser;
        $options = array();
        foreach ($languageCodes as $k)
        {
            list($k, ) = explode('_', $k);
            $options[$k] = "[$k] " . $languageNames[$k];
        }
        return $options;
    }

    protected function getDefaultLang()
    {
        list($k, ) = explode('_', $this->getDi()->app->getDefaultLocale());
        return $k;
    }

    protected function exportAction()
    {

        $this->_helper->sendFile->sendData(
            $this->getDi()->emailTemplateTable->exportReturnXml(array('email_template_id')), 'text/xml', 'amember-email-templates-' . $this->getDi()->sqlDate . '.xml');
    }

    function importAction()
    {
        $form = new Am_Form_Admin;

        $import = $form->addFile('import')
                ->setLabel('Upload file [email-templates.xml]');

        $form->addStatic('')->setContent('WARNING! All existing e-mail templates will be removed from database!');
        //$import->addRule('required', 'Please upload file');
        //$form->addAdvCheckbox('remove')->setLabel('Remove your existing templates?');
        $form->addSaveButton(___('Upload'));

        if ($form->isSubmitted() && $form->validate())
        {
            $value = $form->getValue();

            $fn = DATA_DIR . '/import.email-templates.xml';

            if (!move_uploaded_file($value['import']['tmp_name'], $fn))
                throw new Am_Exception_InternalError("Could not move uploaded file");

            $xml = file_get_contents($fn);
            if (!$xml)
                throw new Am_Exception_InputError("Could not read XML");

            $count = $this->getDi()->emailTemplateTable->deleteBy(array())->importXml($xml);
            $this->view->content = "Import Finished. $count templates imported.";
        } else
        {
            $this->view->content = (string) $form;
        }
        $this->view->title = "Import E-Mail Templates from XML file";
        $this->view->display('admin/layout.phtml');
    }

    function getJs($showOffer = false)
    {

        $offerText = json_encode((nl2br(___("This email template is empty in given language.\n" .
                        "Press [Copy] to copy template from default language [English]\n" .
                        "Press [Skip] to type it manually from scratch."))));
        $copy = ___("Copy");
        $skip = ___("Skip");
        if ($showOffer)
        {
            $jsOffer = <<<CUT
var div = $('<div><div>');
div.append($offerText+"<br />")
$('body').append(div);
div.dialog({
        autoOpen: true,
        modal : true,
        title : "",
        width : 350,
        position : ['center', 'center'],
        buttons: {
            "$copy" : function() {
                $("#another_lang").val('en');
                $("#another_lang").closest('form').ajaxSubmit({
                    success : function(data) {
                        $('#email-template-popup').empty().append(data);
                    }
                });
                $(this).dialog("close");
            },
            "$skip" : function() {
                $(this).dialog("close");
            }
        },
        close : function() {
            div.remove();
        }
    });          
CUT;
        }
        else
        {
            $jsOffer = '';
        }


        return <<<CUT
<script type="text/javascript">   
(function($){
setTimeout(function(){
    $("#lang").change(function(){
        var importantVars = new Array(
            'lang', 'name', 'label'
        );
        $.each(this.form, function() {
            if ($.inArray(this.name, importantVars) == -1) {
                if (this.name == 'format') {
                    this.selectedIndex = null;
                } else {
                    this.value='';
                }
            }
        })
        $(this.form).ajaxSubmit({
                        success : function(data) {
                            $('#email-template-popup').empty().append(data);
                        }
                    });
    });

    $("#another_lang").change(function(){
        if (this.selectedIndex == 0) return;
        $(this.form).ajaxSubmit({
                        success : function(data) {
                            $('#email-template-popup').empty().append(data);
                        }
                    });
    });
    
    $jsOffer
}, 100);

})(jQuery)
</script>
CUT;
    }

}