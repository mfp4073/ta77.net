<?php
/*
*
*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.cgi-central.net
*    Details: Admin accounts
*    FileName $RCSfile$
*    Release: 4.2.17 ($Revision: 4649 $)
*
* Please direct bug reports,suggestions or feedback to the cgi-central forums.
* http://www.cgi-central.net/forum/
*                                                                          
* aMember PRO is a commercial software. Any distribution is strictly prohibited.
*/

class Am_Grid_Action_CopySavedForm extends Am_Grid_Action_Abstract
{
    protected $id = 'copy';
    protected $privilege = 'insert';
    public function run()
    {
        $record = $this->grid->getRecord();

        $record->generateCode();
        $vars = $record->toRow();
        unset($vars['saved_form_id']);
        unset($vars['default_for']);
        $vars['title'] = ___('Copy of') . ' ' . $record->title;

        $back = @$_SERVER['HTTP_X_REQUESTED_WITH'];
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $request = new Am_Request($vars + array($this->grid->getId() . '_a' => 'insert-' . $record->type,
            $this->grid->getId() . '_b' => $this->grid->getBackUrl()), Am_Request::METHOD_POST);
        $controller = new AdminSavedFormController($request, new Zend_Controller_Response_Http(),
            array('di' => Am_Di::getInstance()));

        $request->setModuleName('default')
            ->setControllerName('admin-saved-form')
            ->setActionName('index')
            ->setDispatched(true);

        $controller->dispatch('indexAction');
        $response = $controller->getResponse();
        $response->sendResponse();
        $_SERVER['HTTP_X_REQUESTED_WITH'] = $back;
    }
}


class Am_Form_Admin_SavedForm extends Am_Form_Admin
{
    /** @var SavedForm */
    protected $record;
    /** @var Am_Form_Element_BricksEditor */
    protected $brickEditor;
    
    public function __construct(SavedForm $record) {
        $this->record = $record;
        parent::__construct();
    }

    public function init() {
        parent::init();
        
        $typeDef = $this->record->getTypeDef();

        $type = $this->addSelect('type', null, array('options' => Am_Di::getInstance()->savedFormTable->getTypeOptions()));
        $type->setLabel(___('Form Type'));
        if (!empty($this->record->type)) $type->toggleFrozen(true);

        $title = $this->addText('title', array('size' => 40))->setLabel(
            ___("Custom Signup Form Title\n".
            "keep empty to use default title"));
        
        $comment = $this->addText('comment', array('size' => 40))
            ->setLabel(
            ___("Comment\nfor admin reference"));

        if ($this->record->isSignup())
        {
        if (!empty($typeDef['generateCode']))
            $code = $this->addText('code')
                ->setLabel(___("Secret Code\n".
                    "if form is not choosen as default, this code\n".
                    "(inside URL) will be necessary to open form"))
                ->addRule('regex', ___('Value must be alpha-numeric'), '/[a-zA-Z0-9_]/');
        }


        $this->brickEditor = $this->addElement(new Am_Form_Element_BricksEditor('fields', array(), $this->record->createForm()))
            ->setLabel(array('Fields'));

        if ($this->record->isSignup())
        {
            $this->addSelect('tpl')
                ->setLabel(___("Template\nalternative template for signup page").
                               "\n" . 
                           ___("aMember will look for templates in [application/default/views/signup/] folder\n".
                               "and in theme's [signup/] folder\n".
                               "and template filename must start with [signup]"))
                ->loadOptions($this->getSignupTemplates());
        }

    }
    
    public function render(HTML_QuickForm2_Renderer $renderer)
    {
        return parent::render($renderer);
    }

    static function getSignupTemplates()
    {
        $folders = array(
            APPLICATION_PATH . '/default/views/' => 1,
            APPLICATION_PATH . '/default/themes/' . Am_Di::getInstance()->config->get('theme') => 2,
        );
        $ret = array();
        foreach (array_keys($folders) as $f)
        {
            foreach ((array)glob($f . '/signup/signup*.phtml') as $file)
            {
                if (!strlen($file)) continue;
                $file = basename($file);
                $ret[$file == 'signup.phtml' ? null : $file] = $file;
            }
        }
        return $ret;
    }
    public function renderEpilog()
    {
        return $this->brickEditor->renderConfigForms();
    }
}


class AdminSavedFormController extends Am_Controller_Grid
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->isSuper();
    }
    public function createGrid()
    {
        $this->view->headScript()
            ->appendFile(REL_ROOT_URL . "/application/default/views/public/js/jquery/jquery.json.js");
        $this->view->headScript()
            ->appendScript($this->getJs());

        $table = $this->getDi()->savedFormTable;
        $ds = new Am_Query($table);
        $ds->addWhere('`type` in (?a)', array_keys($table->getTypeDefs()));
        $ds->addOrderRaw("`type`='signup' DESC");
        $grid = new Am_Grid_Editable('_s', ___("Forms Editor"), $ds, $this->_request, $this->view);
        $grid->setForm(array($this, 'createForm'));
        $grid->setRecordTitle(' ');
        
        //$grid->addGridField(new Am_Grid_Field('saved_form_id', '#', true, '', null, '5%'));
        
        $grid->addGridField(SavedForm::D_SIGNUP, ___('Default Signup'), false)
            ->setWidth('5%')
            ->setRenderFunction(array($this, 'renderDefault'));
        $grid->addGridField(SavedForm::D_MEMBER, ___('Default for Members'), false)
            ->setWidth('5%')
            ->setRenderFunction(array($this, 'renderDefault'));
        
        $existingTypes = $this->getDi()->savedFormTable->getExistingTypes();
        
        $grid->actionGet('edit')->setTarget('_top');
        
        $grid->actionDelete('insert');
        foreach ($this->getDi()->savedFormTable->getTypeDefs() as $type => $typeDef)
        {
            if (!empty($typeDef['isSingle']) && in_array($type, $existingTypes))
                continue;
            $grid->actionAdd(new Am_Grid_Action_Insert('insert-'.$type))
                ->addUrlParam('type', $type)->setTitle(___('New %s', $typeDef['title']));
        }
        $grid->addCallback(Am_Grid_Editable::CB_BEFORE_SAVE, array($this, 'beforeSave'));
        $grid->addGridField(new Am_Grid_Field('type', ___('Type')));
        $grid->addGridField(new Am_Grid_Field('title', ___('Title')));
        $grid->addGridField(new Am_Grid_Field('comment', ___('Comment')));
        $grid->addGridField(new Am_Grid_Field('code', ___('Code')));
        $grid->addGridField(new Am_Grid_Field('url', ___('URL')))
            ->setRenderFunction(array($this, 'renderUrl'));
        $grid->actionGet('delete')->setIsAvailableCallback(create_function('$record', 
            'return $record->canDelete();'));
        $grid->actionAdd(new Am_Grid_Action_CopySavedForm())->setIsAvailableCallback(create_function('$record',
            'return !$record->isSingle();'));
        return $grid;
    }
    public function beforeSave(array & $values)
    {
        $fields = json_decode($values['fields'],true);
        foreach($fields as &$f)
            if(isset($f['labels']))
                foreach($f['labels'] as $k=>$l)
                    $f['labels'][$k] = preg_replace("/\r\n/","\n",$l);
        $values['fields'] = json_encode($fields);
        if (($values['type'] == 'signup') && !strlen($values['code']))
        {
            $values['code'] = $this->getDi()->app->generateRandomString(8);
        }
    }
    public function getJs()
    {
        return <<<CUT
$(function(){
    $("input.set-default").change(function(){
        $(this).closest("form").submit();
    });
});
CUT;
    }
    public function renderDefault(SavedForm $record, $field)
    {
        $html = "";
        if ($record->type == SavedForm::T_SIGNUP)
        {
            $checked = $record->isDefault($field) ? "checked='checked'" : "";
            $html = sprintf('
                <form method="post" action="%s" target="_top">
                <input type="radio" class="set-default" name="default[%s]" value="%d" %s/>
                </form>
                ',
                $this->escape(REL_ROOT_URL . '/admin-saved-form/set-default'),
                $field, $record->saved_form_id,
                $checked);
        } 
        return $this->renderTd($html, false);
    }
    public function setDefaultAction()
    {
        foreach ($this->getRequest()->getPost('default') as $d => $id)
            $this->getDi()->savedFormTable->setDefault($d, $id);
        $this->_redirect('admin-saved-form');
    }
    public function createForm() 
    {
        $record = $this->grid->getRecord();
        $post = $this->grid->getCompleteRequest()->getPost();
        if (!$record->isLoaded())
        {
            if ($type = $this->_request->getFiltered('type'))
                $record->type = $type;
            if ($record->type && empty($post['type'])) // form was not submitted yet
                $record->setDefaults();
        }
        $form = new Am_Form_Admin_SavedForm($record);
        $form->addRule('callback', '-error-', array($this, 'validate'));
        return $form;
    }
    public function renderUrl(SavedForm $record)
    {
        $content = sprintf('<a target="_blank" href="%s">%s</a>',
            $record->getUrl(ROOT_URL . '/'), $record->getUrl(""));
        return $this->renderTd($content, false);
    }
    public function validate(array $value)
    {
        /// check for unique code
        $el = $this->grid->getForm()->getElementById('code-0');
        if ($el && strlen($code = $el->getValue()))
        {
            if ($id = $this->getDi()->db->selectCell("SELECT saved_form_id 
                    FROM ?_saved_form
                    WHERE code=? AND saved_form_id<>?d", 
                        $code, 
                        (int)@$value['_s_id']))
            {
                $code = $this->escape($code);
                $el->setError("The code [$code] is already used by signup form #$id, please choose another code");
                return false;
            }
        }
        return true;
    }
    public function preDispatch() {
        parent::preDispatch();
        class_exists('Am_Form_Brick', true); //pre-load
    }
}