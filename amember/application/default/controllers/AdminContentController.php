<?php

class Am_Grid_Filter_Content_Folder extends Am_Grid_Filter_Abstract {
    protected $varList = array('filter_q', 'filter_t');

    protected function applyFilter() {
        $query = $this->grid->getDataSource()->getDataSourceQuery();
        $type = $this->getParam('filter_t');

        if (!in_array($type, array('url','path','title'))) {
            $type='title';
        }
        if ($filter = $this->getParam('filter_q')){
            $condition = new Am_Query_Condition_Field($type, 'LIKE', '%'.$filter.'%');
            $query->add($condition);
        }
    }

    function renderInputs() {
        $filter = '';
        $filter .= $this->renderInputText('filter_q');
        $filter .= ' ';
        $filter .= $this->renderInputSelect('filter_t', array(
            'title' => ___('Title'),
            'url' => ___('URL'),
            'path' => ___('Path')
        ));
        return $filter;
    }

    function getTitle() {
        return ___('Filter by String');
    }
}

class Am_Form_Element_PlayerConfig extends HTML_QuickForm2_Element
{
    protected $value;
    /* @var HTML_QuickForm2_Element_InputHidden */
    protected $elHidden;
    /* @var HTML_QuickForm2_Element_Select */
    protected $elSelect;

    public function __construct($name = null, $attributes = null, array $data = array())
    {

        $this->elHidden = new HTML_QuickForm2_Element_InputHidden($name);
        $this->elHidden->setContainer($this->getContainer());

        $this->elSelect = new HTML_QuickForm2_Element_Select('__' . $name);

        $this->elSelect->loadOptions(array(
            '--global--' => ___('Use Global Settings'),
            '--custom--' => ___('Use Custom Settings')
            )
        );

        $this->addPresets($this->elSelect);
        parent::__construct($name, $attributes, $data);
    }

    public function getType()
    {
        return 'player-config';
    }

    public function getRawValue()
    {
        return $this->elHidden->getRawValue();
    }

    public function updateValue()
    {
        $this->elHidden->setContainer($this->getContainer());
        $this->elHidden->updateValue();
        $this->setValue($this->elHidden->getRawValue());
    }

    public function setValue($value)
    {
        if (!$value)
        {
            $this->elSelect->setValue('--global--');
        }
        elseif (@unserialize($value))
        {
            $this->elSelect->setValue('--custom--');
        }
        else
        {
            $this->elSelect->setValue($value);
        }
        $this->elHidden->setValue($value);
    }

    public function __toString()
    {
        return sprintf('<div class="player-config">%s%s <div class="player-config-edit"><a href="javascript:;">Edit</div><div class="player-config-delete"><a href="javascript:;">Delete Preset</div><div class="player-config-save"><a href="javascript:;">Save As Preset</a></div></div>', $this->elHidden, $this->elSelect) .
        "<script type='text/javascript'>
             $('.player-config').playerConfig();
         </script>";
    }

    protected function addPresets(HTML_QuickForm2_Element_Select $select)
    {
        $result = array();
        $presets = Am_Di::getInstance()->store->getBlob('flowplayer-presets');
        $presets = $presets ? unserialize($presets) : array();
        foreach ($presets as $id => $preset)
        {
            $select->addOption($preset['name'], $id, array('data-config' => serialize($preset['config'])));
        }
    }

}

class Am_Form_Element_DownloadLimit extends HTML_QuickForm2_Element
{
    protected $value = array();
    /* @var HTML_QuickForm2_Element_InputText */
    protected $elText;
    /* @var HTML_QuickForm2_Element_Select */
    protected $elSelect;
    /* @var Am_Form_Element_AdvCheckbox */
    protected $elCheckbox;

    public function __construct($name = null, $attributes = null, array $data = array())
    {

        $this->elText = new HTML_QuickForm2_Element_InputText("__limit_" . $name, array('class' => 'download-limit-limit', 'size' => 4));
        $this->elText->setValue(5); //Default

        $this->elSelect = new HTML_QuickForm2_Element_Select("__period_" . $name, array('class' => 'download-limit-period'));
        $this->elSelect->loadOptions(array(
            FileDownloadTable::PERIOD_HOUR => ___('Hour'),
            FileDownloadTable::PERIOD_DAY => ___('Day'),
            FileDownloadTable::PERIOD_WEEK => ___('Week'),
            FileDownloadTable::PERIOD_MONTH => ___('Month'),
            FileDownloadTable::PERIOD_YEAR => ___('Year'),
            FileDownloadTable::PERIOD_ALL => ___('All Subscription Period')
            )
        )->setValue(FileDownloadTable::PERIOD_MONTH); //Default

        $this->elCheckbox = new Am_Form_Element_AdvCheckbox("__enable_" . $name, array('class' => 'download-limit-enable'));

        parent::__construct($name, $attributes, $data);
    }

    public function getType()
    {
        return 'download-limit';
    }

    public function updateValue()
    {
        $this->elText->setContainer($this->getContainer());
        $this->elText->updateValue();
        $this->elSelect->setContainer($this->getContainer());
        $this->elSelect->updateValue();
        $this->elCheckbox->setContainer($this->getContainer());
        $this->elCheckbox->updateValue();
        parent::updateValue();
    }

    public function getRawValue()
    {
        return $this->elCheckbox->getValue() ? sprintf('%d:%d', $this->elText->getValue(), $this->elSelect->getValue()) : '';
    }

    public function setValue($value)
    {
        if (!$value)
        {
            $this->elCheckbox->setValue(0);
        }
        else
        {
            $this->elCheckbox->setValue(1);
            list($limit, $period) = explode(':', $value);
            $this->elText->setValue($limit);
            $this->elSelect->setValue($period);
        }
    }

    public function __toString()
    {
        $name = Am_Controller::escape($this->getName());

        $ret = "<div class='download-limit' id='downlod-limit-$name'>\n";
        $ret .= $this->elCheckbox;
        $ret .= '<span>';
        $ret .= ___('allow max');
        $ret .= ' ' . (string) $this->elText . ' ';
        $ret .= ___('downloads within');
        $ret .= ' ' . (string) $this->elSelect . ' ';
        $ret .= ___('during subscription period');
        $ret .= "</span>\n";
        $ret .= "</div>";
        $ret .= "
        <script type='text/javascript'>
             $('.download-limit').find('input[type=checkbox]').change(function(){
                $(this).next().toggle(this.checked)
             }).change();
        </script>
        ";
        return $ret;
    }

}

class Am_Form_Element_ResourceAccess extends HTML_QuickForm2_Element
{
    protected $value = array();

    public function getType()
    {
        return 'resource-access';
    }

    public function getRawValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        $name = Am_Controller::escape($this->getName());
        $ret = "<div class='resourceaccess' id='$name'>";

        if (!$this->getAttribute('without_free'))
        {
            $ret .= "<span class='free-switch protected-access'>\n";
            $ret .= ___('Choose Products and/or Product Categories that allows access') . "<br />\n";
            $ret .= ___('or %smake access free%s', "<a href='javascript:' data-access='free'>", '</a>') . "<br /><br />\n";
        }

        $select = new HTML_QuickForm2_Element_Select(null, array('class' => 'access-items'));
        $select->addOption(___('Please select an item...'), '');
        $g = $select->addOptgroup(___('Product Categories'), array('class' => 'product_category_id', 'data-text' => ___("Category")));
        $g->addOption(___('Any Product'), '-1', array('style' => 'font-weight: bold'));
        foreach (Am_Di::getInstance()->productCategoryTable->getAdminSelectOptions() as $k => $v)
        {
            $g->addOption($v, $k);
        }
        $g = $select->addOptgroup(___('Products'), array('class' => 'product_id', 'data-text' => ___("Product")));
        foreach (Am_Di::getInstance()->productTable->getOptions() as $k => $v)
        {
            $g->addOption($v, $k);
        }
        $ret .= (string) $select;

        foreach (Am_Di::getInstance()->resourceAccessTable->getFnValues() as $k)
            $ret .= "<div class='$k-list'></div>";

        $ret .= "</span>\n";
        
        $hide_free_without_login = (bool)$this->getAttribute('without_free_without_login');

        $ret .= "<span class='free-switch free-access' style='display:none;'>".
            nl2br(___("this item is available for %sall registered customers%s.\n"
            ."click to %smake this item protected%s\n"
            ."%sor %smake this item available without login and registration%s\n%s"
            , "<b>" ,"</b>"
            , "<a href='javascript:' data-access='protected'>", "</a>"
            , ($hide_free_without_login ? '<span style="display:none">' : '<span>')
            , "<a href='javascript:' data-access='free_without_login'>", "</a>", '</span>')).
            "</span>";

        $ret .= "<span class='free-switch free_without_login-access' style='display:none;'>".
            nl2br(___("this item is available for %sall visitors (without log-in and registration) and for all members%s\n"
            ."click to %smake this item protected%s\n"
            ."or %smake log-in required%s\n"
            , "<b>" , "</b>"
            , "<a href='javascript:' data-access='protected'>", "</a>"
            , "<a href='javascript:' data-access='free'>", "</a>")).
            "</span>";
        
        $json = array();
        if (
               !empty($this->value['product_category_id']) 
            || !empty($this->value['product_id']) 
            || !empty($this->value['free'])
            || !empty($this->value['free_without_login'])
        )
        {
            $json = $this->value;
            foreach ($json as & $fn)
                foreach ($fn as & $rec)
                {
                    if (is_string($rec))
                        $rec = json_decode($rec, true);
                }
        } else
            foreach ($this->value as $cl => $access)
            {
                $json[$access->getClass()][$access->getId()] = array(
                    'text' => $access->getTitle(),
                    'start' => $access->getStart(),
                    'stop' => $access->getStop(false),
                );
            }

        $json = Am_Controller::escape(Am_Controller::getJson($json));
        $ret .= "<input type='hidden' class='resourceaccess-init' value='$json' />\n";
        $ret .= "</div>";

        $without_period = $this->getAttribute('without_period') ? 'true' : 'false';
        $ret .= "
        <script type='text/javascript'>
             $('.resourceaccess').resourceaccess({without_period: $without_period});
        </script>
        ";
        return $ret;
    }

}

class Am_Grid_Editable_Files extends Am_Grid_Editable_Content
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->addCallback(self::CB_AFTER_DELETE, array($this, 'afterDelete'));
        $this->addCallback(self::CB_AFTER_SAVE, array($this, 'dropCache'));
        $this->addCallback(self::CB_AFTER_DELETE, array($this, 'dropCache'));
        $this->addCallback(self::CB_VALUES_FROM_FORM, array($this, '_valuesFromForm'));
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by Title'), array('title'=>'LIKE')));
    }

    protected function _valuesFromForm(& $values)
    {
        $path = $values['path'];
        $values['mime'] = is_numeric($path) ?
            $this->getDi()->uploadTable->load($path)->getType() :
            Upload::getMimeType($path);
    }

    protected function dropCache()
    {
        $this->getDi()->cache->clean();
    }

    protected function afterDelete(File $record, $grid)
    {
        if (ctype_digit($record->path)
            && !$this->getDi()->fileTable->countBy(array('path' => $record->path)))
        {
            $this->getDi()->uploadTable->load($record->path)->delete();
        }
    }

    protected function initGridFields()
    {
        $this->addGridField('title', ___('Title'))->setRenderFunction(array($this, 'renderAccessTitle'));
        $this->addGridField('path', ___('Filename'))->setRenderFunction(array($this, 'renderPath'));
        parent::initGridFields();
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->fileTable);
    }

    function createForm()
    {
        $form = new Am_Form_Admin;
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('target', '_top');

        $maxFileSize = min(ini_get('post_max_size'), ini_get('upload_max_filesize'));
        $el = $form->addElement(new Am_Form_Element_Upload('path', array(), array('prefix' => 'downloads')))
                ->setLabel(___("File\n(max filesize %s)", $maxFileSize))->setId('form-path');

        $jsOptions = <<<CUT
{
    onFileAdd : function (info) {
        var txt = $(this).closest("form").find("input[name='title']");
        if (txt.data('changed-value')) return;
        txt.val(info.name);
    }
}
CUT;
        $el->setJsOptions($jsOptions);
        $form->addScript()->setScript(<<<CUT
$(function(){
    $("input[name='title']").change(function(){
        $(this).data('changed-value', true);
    });
});
CUT
        );


        $el->addRule('required', ___('File is required'));
        $form->addText('title', array('size' => 50))->setLabel(___('Title'))->addRule('required', 'This field is required');
        $form->addText('desc', array('size' => 50))->setLabel(___('Description'))->addRule('required', 'This field is required');
        $form->addAdvCheckbox('hide')->setLabel(___("Hide\n" . "do not display this item link in members area"));
        $form->addElement(new Am_Form_Element_DownloadLimit('download_limit'))->setLabel(___('Limit Downloads Count'));
        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')->setLabel(___('Access Permissions'));
        return $form;
    }

}

class Am_Grid_Editable_Pages extends Am_Grid_Editable_Content
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by Title'), array('title'=>'LIKE')));
    }

    protected function initGridFields()
    {
        $this->addGridField('title', ___('Title'))->setRenderFunction(array($this, 'renderAccessTitle'));
        parent::initGridFields();
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->pageTable);
    }

    function createForm()
    {
        $form = new Am_Form_Admin;
        $form->addText('title', array('size' => 80))->setLabel('Title')->addRule('required', 'This field is required');
        $form->addText('desc', array('size' => 80))->setLabel(___('Description'));
        $form->addAdvCheckbox('hide')->setLabel(___("Hide\n" . "do not display this item link in members area"));
        $form->addAdvCheckbox('use_layout')->setLabel("Display inside layout\nWhen displaying to customer, will the \nheader/footer from current theme be displayed?");
        $form->addHtmlEditor('html');
        //->setLabel('HTML code')->addRule('required', 'This field is required');
        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')->setLabel(___('Access Permissions'));
        return $form;
    }

}

class Am_Grid_Editable_Links extends Am_Grid_Editable_Content
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by Title'), array('title'=>'LIKE')));
    }

    protected function initGridFields()
    {
        $this->addGridField('title', ___('Title'))->setRenderFunction(array($this, 'renderAccessTitle'));
        parent::initGridFields();
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->linkTable);
    }

    function createForm()
    {
        $form = new Am_Form_Admin;
        $form->addText('title', array('size' => 80))->setLabel(___('Title'))->addRule('required');
        $form->addText('url', array('size' => 80))->setLabel(___('URL'))->addRule('required');
        $form->addAdvCheckbox('hide')->setLabel(___("Hide\n" . "do not display this item link in members area"));
        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')
            ->setLabel(___('Access Permissions'))
            ->setAttribute('without_free_without_login', 'true');
        return $form;
    }

    public function renderContent()
    {
        return '<div class="info"><strong>' . ___("IMPORTANT NOTE: This will not protect content. If someone know link url, he will be able to open link without a problem. This just control what additional links user will see after login to member's area.") . '</strong></div>' . parent::renderContent();
    }

}

class Am_Grid_Editable_Integrations extends Am_Grid_Editable_Content
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by Plugin'), array('plugin'=>'LIKE')));
    }

    public function init()
    {
        parent::init();
        $this->addCallback(self::CB_VALUES_FROM_FORM, array($this, '_valuesFromForm'));
    }

    public function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->integrationTable);
    }

    protected function initGridFields()
    {
        $this->addGridField('plugin', ___('Plugin'))->setRenderFunction(array($this, 'renderPluginTitle'));
        $this->addGridField('resource', ___('Resource'))->setRenderFunction(array($this, 'renderResourceTitle'));
        parent::initGridFields();
        $this->removeField('_link');
    }

    public function renderPluginTitle(Am_Record $r)
    {
        return $this->renderTd($r->plugin);
    }

    public function renderResourceTitle(Am_Record $r)
    {
        try
        {
            $pl = Am_Di::getInstance()->plugins_protect->get($r->plugin);
        }
        catch (Am_Exception_InternalError $e)
        {
            $pl = null;
        }
        $config = unserialize($r->vars);
        $s = $pl ? $pl->getIntegrationSettingDescription($config) : Am_Protect_Abstract::static_getIntegrationDescription($config);
        return $this->renderTd($s);
    }

    public function getGridPageTitle()
    {
        return ___("Integration plugins");
    }

    function createForm()
    {
        $form = new Am_Form_Admin;
        $plugins = $form->addSelect('plugin')->setLabel(___('Plugin'));
        $plugins->addRule('required');
        $plugins->addOption('*** ' . ___('Select a plugin') . ' ***', '');
        foreach (Am_Di::getInstance()->plugins_protect->getAllEnabled() as $plugin)
        {
            if (!$plugin->isConfigured())
                continue;
            $group = $form->addFieldset($plugin->getId())->setId('headrow-' . $plugin->getId());
            $group->setLabel($plugin->getTitle());
            $plugin->getIntegrationFormElements($group);
            // add id[...] around the element name
            foreach ($group->getElements() as $el)
                $el->setName('_plugins[' . $plugin->getId() . '][' . $el->getName() . ']');
            if (!$group->count())
                $form->removeChild($group);
            else
                $plugins->addOption($plugin->getTitle(), $plugin->getId());
        }
        $group = $form->addFieldset('access')->setLabel(___('Access'));
        $group->addElement(new Am_Form_Element_ResourceAccess)
            ->setName('_access')
            ->setLabel(___('Access Permissions'))
            ->setAttribute('without_period', 'true')
            ->setAttribute('without_free_without_login', 'true');

        $form->addScript()->setScript(<<<CUT
$(function(){
    $("select[name='plugin']").change(function(){
        var selected = $(this).val();
        $("[id^='headrow-']").hide();
        if (selected) {
            $("[id^=headrow-"+selected+"]").show();
        }
    }).change();
});
CUT
        );
        return $form;
    }

    public function _valuesFromForm(array & $vars)
    {
        if ($vars['plugin'] && !empty($vars['_plugins'][$vars['plugin']]))
            $vars['vars'] = serialize($vars['_plugins'][$vars['plugin']]);
    }

    public function _valuesToForm(array & $vars)
    {
        if (!empty($vars['vars']))
        {
            foreach (unserialize($vars['vars']) as $k => $v)
                $vars['_plugins'][$vars['plugin']][$k] = $v;
        }
        parent::_valuesToForm($vars);
    }

}

class Am_Grid_Editable_Folders extends Am_Grid_Editable_Content
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Content_Folder());
    }

    public function init()
    {
        parent::init();
        $this->addCallback(self::CB_AFTER_UPDATE, array($this, 'afterUpdate'));
        $this->addCallback(self::CB_AFTER_DELETE, array($this, 'afterDelete'));
    }

    public function validatePath($path)
    {
        if (!is_dir($path))
            return "Wrong path: not a folder: " . htmlentities($path);
        if (!is_writeable($path))
            return "Specified folder is not writable - please chmod the folder to 777, so aMember can write .htaccess file for folder protection";
    }

    function createForm()
    {
        $form = new Am_Form_Admin;

        $title = $form->addText('title')->setLabel(___("Title\ndisplayed to customers"))->setAttribute('size', 50);
        $title->addRule('required');
        $form->addAdvCheckbox('hide')->setLabel(___("Hide\n" . "do not display this item link in members area"));

        $path = $form->addText('path')->setLabel(___('Path to Folder'))->setAttribute('size', 50)->addClass('dir-browser');
        $path->addRule('required');
        $path->addRule('callback2', '-- Wrong path --', array($this, 'validatePath'));

        $url = $form->addGroup()->setLabel(___('Folder URL'));
        $url->addRule('required');
        $url->addText('url')->setAttribute('size', 50)->setId('url');
        $url->addHtml()->setHtml('&nbsp;<a href="#" id="test-url-link">' . ___('open in new window') . '</a>');

        $methods = array(
            'new-rewrite' => ___('New Rewrite'),
            'htpasswd' => ___('Traditional .htpasswd'),
        );
        foreach ($methods as $k => $v)
            if (!Am_Di::getInstance()->plugins_protect->isEnabled($k))
                unset($methods[$k]);


        $method = $form->addAdvRadio('method')->setLabel(___('Protection Method'));
        $method->loadOptions($methods);
        if (count($methods) == 0)
        {
            throw new Am_Exception_InputError("No protection plugins enabled, please enable new-rewrite or htpasswd at aMember CP -> Setup -> Plugins");
        }
        elseif (count($methods) == 1)
        {
            $method->setValue(key($methods))->toggleFrozen(true);
        }

        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')->setLabel(___('Access Permissions'));
        $form->addScript('script')->setScript('
        $(function(){
            $(".dir-browser").dirBrowser({
                urlField : "#url",
                rootUrl  : ' . Am_Controller::getJson(REL_ROOT_URL) . ',
            });
            $("#test-url-link").click(function() {
                var href = $("input", $(this).parent()).val();
                if (href)
                    window.open(href , "test-url", "");
            });
        });
        ');
        return $form;
    }

    protected function initGridFields()
    {
        $this->addGridField('title', ___('Title'))->setRenderFunction(array($this, 'renderAccessTitle'));
        $this->addGridField('path', ___('Path/URL'))->setRenderFunction(array($this, 'renderPathUrl'));
        $this->addGridField('method', ___('Protection Method'));
        parent::initGridFields();
    }

    public function renderPathUrl(Folder $f)
    {
        $url = Am_Controller::escape($f->url);
        return $this->renderTd(
            Am_Controller::escape($f->path) .
            "<br />" .
            "<a href='$url' target='_blank'>$url</a>", false);
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->folderTable);
    }

    public function getGridPageTitle()
    {
        return ___("Folders");
    }

    public function getHtaccessRewriteFile(Folder $folder)
    {
        if (AM_WIN)
            $rd = str_replace("\\", '/', DATA_DIR);
        else
            $rd = DATA_DIR;

        $root_url = ROOT_SURL;
        return <<<CUT
########### AMEMBER START #####################
Options +FollowSymLinks
RewriteEngine On

# if cookie is set and file exists, stop rewriting and show page
RewriteCond %{HTTP_COOKIE} amember_nr=([a-zA-Z0-9]+)
RewriteCond $rd/new-rewrite/%1-{$folder->folder_id} -f
RewriteRule ^(.*)\$ - [S=3]

# if cookie is set but folder file does not exists, user has no access to given folder
RewriteCond %{HTTP_COOKIE} amember_nr=([a-zA-Z0-9]+)
RewriteCond $rd/new-rewrite/%1-{$folder->folder_id} !-f
RewriteRule ^(.*)$ $root_url/no-access/folder/id/$folder->folder_id?url=%{REQUEST_URI}?%{QUERY_STRING}&host=%{HTTP_HOST}&ssl=%{HTTPS} [L,R]
    
## if user is not authorized, redirect to login page
# BrowserMatch "MSIE" force-no-vary
RewriteCond %{QUERY_STRING} (.+)
RewriteRule ^(.*)$ $root_url/protect/new-rewrite?f=$folder->folder_id&url=%{REQUEST_URI}?%1&host=%{HTTP_HOST}&ssl=%{HTTPS} [L,R,B]
RewriteRule ^(.*)$ $root_url/protect/new-rewrite?f=$folder->folder_id&url=%{REQUEST_URI}&host=%{HTTP_HOST}&ssl=%{HTTPS} [L,R]
########### AMEMBER FINISH ####################
CUT;
    }

    public function getHtaccessHtpasswdFile(Folder $folder)
    {
        $rd = DATA_DIR;

        $require = '';
        if (!$folder->hasAnyProducts())
            $require = 'valid-user';
        else
            $require = 'group FOLDER_' . $folder->folder_id;

//        $redirect = ROOT_SURL . "/no-access?folder_id={$folder->folder_id}";
//        ErrorDocument 401 $redirect

        return <<<CUT
########### AMEMBER START #####################
AuthType Basic
AuthName "Members Only"
AuthUserFile $rd/.htpasswd
AuthGroupFile $rd/.htgroup
Require $require
########### AMEMBER FINISH ####################

CUT;
    }

    public function protectFolder(Folder $folder)
    {
        switch ($folder->method)
        {
            case 'new-rewrite':
                $ht = $this->getHtaccessRewriteFile($folder);
                break;
            case 'htpasswd':
                $ht = $this->getHtaccessHtpasswdFile($folder);
                break;
            default: throw new Am_Exception_InternalError('Unknown protection method');
        }
        $htaccess_path = $folder->path . '/' . '.htaccess';
        if (file_exists($htaccess_path))
        {
            $content = file_get_contents($htaccess_path);
            $new_content = preg_replace('/#+\sAMEMBER START.+AMEMBER FINISH\s#+/ms', $ht, $content, 1, $found);
            if (!$found)
                $new_content = $ht . "\n\n" . $content;
        } else
        {
            $new_content = $ht . "\n\n";
        }
        if (!file_put_contents($htaccess_path, $new_content))
            throw new Am_Exception_InputError("Could not write file [$htaccess_path] - check file permissions and make sure it is writeable");
    }

    public function unprotectFolder(Folder $folder)
    {
        $htaccess_path = $folder->path . '/.htaccess';
        if (!is_dir($folder->path))
        {
            trigger_error("Could not open folder [$folder->path] to remove .htaccess from it. Do it manually", E_USER_WARNING);
            return;
        }
        $content = file_get_contents($htaccess_path);
        if (strlen($content) && !preg_match('/^\s*\#+\sAMEMBER START.+AMEMBER FINISH\s#+\s*/s', $content))
        {
            trigger_error("File [$htaccess_path] contains not only aMember code - remove it manually to unprotect folder", E_USER_WARNING);
            return;
        }
        if(!unlink($folder->path . '/.htaccess'))
            trigger_error("File [$htaccess_path] cannot be deleted - remove it manually to unprotect folder", E_USER_WARNING);
    }

    public function afterInsert(array &$values, ResourceAbstract $record)
    {
        parent::afterInsert($values, $record);
        $this->protectFolder($record);
    }
    public function afterUpdate(array &$values, ResourceAbstract $record)
    {
        $this->protectFolder($record);
    }

    public function afterDelete($record)
    {
        $this->unprotectFolder($record);
    }

    public function renderContent()
    {
        return parent::renderContent() . '<p><b>' . ___("After making any changes to htpasswd protected areas, please run [Utiltites->Rebuild Db] to refresh htpasswd file") . '</b></p>';
    }

}

class Am_Grid_Editable_Emails extends Am_Grid_Editable_Content
{
    protected $comment = array();

    public function __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by Subject'), array('subject'=>'LIKE')));
    }

    public function init()
    {
        $this->comment = array(
            EmailTemplate::AUTORESPONDER =>
            "Autoresponder message will be automatically sent by cron job
         when configured conditions met. If you set message to be sent
         after payment, it will be sent immediately after payment received.
         Auto-responder message will not be sent if:
         <ul> 
            <li>User has unsubscribed from e-mail messages</li>
         </ul>
        ",
            EmailTemplate::EXPIRE =>
            "Expiration message will be sent when configured conditions met.
         Additional restrictions applies to do not sent unnecessary e-mails.
         Expiration message will not be sent if:
         <ul> 
            <li>User has other active products with the same renewal group</li>
            <li>User has unsubscribed from e-mail messages</li>
         </ul>
        "
        );
        parent::init();
        $this->addCallback(self::CB_VALUES_FROM_FORM, array($this, '_valuesFromForm'));
    }

    public function initActions()
    {
        parent::initActions();
        $this->actionDelete('insert');
        $this->actionAdd($a0 = new Am_Grid_Action_Insert('insert-' . EmailTemplate::AUTORESPONDER, ___('New Autoresponder')));
        $a0->addUrlParam('name', EmailTemplate::AUTORESPONDER);
        $this->actionAdd($a1 = new Am_Grid_Action_Insert('insert-' . EmailTemplate::EXPIRE, ___('New Expiration E-Mail')));
        $a1->addUrlParam('name', EmailTemplate::EXPIRE);
    }

    protected function createAdapter()
    {
        $ds = new Am_Query(Am_Di::getInstance()->emailTemplateTable);
        $ds->addWhere('name IN (?a)', array(EmailTemplate::AUTORESPONDER, EmailTemplate::EXPIRE));
        return $ds;
    }

    protected function initGridFields()
    {
        $this->addField('name', ___('Name'));
        $this->addField('day', ___('Send'))->setGetFunction(array($this, 'getDay'));
        $this->addField('subject', ___('Subject'))->addDecorator(new Am_Grid_Field_Decorator_Shorten(30));
        parent::initGridFields();
        $this->removeField('_link');
    }

    public function getDay(EmailTemplate $record)
    {
        switch ($record->name)
        {
            case EmailTemplate::AUTORESPONDER:
                return ($record->day > 1) ? ___("%d-th subscription day", $record->day) : ___("immediately after subscription is started");
                break;
            case EmailTemplate::EXPIRE:
                switch (true)
                {
                    case $record->day > 0:
                        return ___("%d days after expiration", $record->day);
                    case $record->day < 0:
                        return ___("%d days before expiration", -$record->day);
                    case $record->day == 0:
                        return ___("on expiration day");
                }
                break;
        }
    }

    public function createForm()
    {
        $form = new Am_Form_Admin;

        $record = $this->getRecord();

        $name = empty($record->name) ?
            $this->getCompleteRequest()->getFiltered('name') :
            $record->name;

        $form->addHidden('name');

        $form->addStatic()->setContent(nl2br($this->comment[$name]))->setLabel(___('Description'));

        $form->addStatic()->setLabel(___('E-Mail Type'))->setContent($name);
        $form->addElement(new Am_Form_Element_MailEditor($name, array('upload-prefix'=>'email-messages')));
        $form->addElement(new Am_Form_Element_ResourceAccess('_access'))
            ->setAttribute('without_period', true)
            ->setLabel($name == EmailTemplate::AUTORESPONDER ? ___('Send E-Mail if customer has subscription (required)') : ___('Send E-Mail when subscription expires (required)'));

        $group = $form->addGroup()
                ->setLabel(___('Send E-Mail only if customer has no subscription (optional)'));

        $select = $group->addMagicSelect('_not_conditions')
                ->setAttribute('without_period', true)
                ->setAttribute('without_free', true);
        $this->addCategoriesProductsList($select);
        $group->addAdvCheckbox('not_conditions_expired')->setContent(___('check expired subscriptions too'));

        $group = $form->addGroup('day')->setLabel(___('Send E-Mail Message'));
        $options = ($name == EmailTemplate::AUTORESPONDER) ?
            array('' => ___('..th subscription day (starts from 2)'), '1' => ___('immediately after subscription is started')) :
            array('-' => ___('days before expiration'), '0' => ___('on expiration day'), '+' => ___('days after expiration'));
        ;
        $group->addInteger('count', array('size' => 3, 'id' => 'days-count'));
        $group->addSelect('type', array('id' => 'days-type'))->loadOptions($options);
        $group->addScript()->setScript(<<<CUT
$("#days-type").change(function(){
    var sel = $(this);
    if ($("input[name='name']").val() == 'autoresponder')
        $("#days-count").toggle( sel.val() != '1' );  
    else
        $("#days-count").toggle( sel.val() != '0' );  
}).change();
CUT
        );
        return $form;
    }

    function addCategoriesProductsList(HTML_QuickForm2_Element_Select $select)
    {
        $g = $select->addOptgroup(___('Product Categories'), array('class' => 'product_category_id', 'data-text' => ___("Category")));
        $g->addOption(___('Any Product'), 'c-1', array('style' => 'font-weight: bold'));
        foreach ($this->getDi()->productCategoryTable->getAdminSelectOptions() as $k => $v)
        {
            $g->addOption($v, 'c' . $k);
        }
        $g = $select->addOptgroup(___('Products'), array('class' => 'product_id', 'data-text' => ___("Product")));
        foreach ($this->getDi()->productTable->getOptions() as $k => $v)
        {
            $g->addOption($v, 'p' . $k);
        }
    }

    public function _valuesToForm(array &$values)
    {
        parent::_valuesToForm($values);
        switch (get_first(@$values['name'], @$_GET['name']))
        {
            case EmailTemplate::AUTORESPONDER :
                $values['day'] = (empty($values['day']) || ($values['day'] == 1)) ?
                    array('count' => 1, 'type' => '1') :
                    array('count' => $values['day'], 'type' => '');
                break;
            case EmailTemplate::EXPIRE :
                $day = @$values['day'];
                $values['day'] = array('count' => $day, 'type' => '');
                if ($day > 0)
                    $values['day']['type'] = '+';
                elseif ($day < 0)
                {
                    $values['day']['type'] = '-';
                    $values['day']['count'] = -$day;
                } else
                    $values['day']['type'] = '0';
                break;
        }
        $values['attachments'] = explode(',', @$values['attachments']);
        $values['_not_conditions'] = explode(',', @$values['not_conditions']);
    }

    public function _valuesFromForm(array &$values)
    {
        switch ($values['day']['type'])
        {
            case '0': $values['day'] = 0;
                break;
            case '1': $values['day'] = 1;
                break;
            case '': case '+':
                $values['day'] = (int) $values['day']['count'];
                break;
            case '-':
                $values['day'] = - $values['day']['count'];
                break;
        }
        $values['attachments'] = implode(',', @$values['attachments']);
        ///////
        foreach(array('free', 'free_without_login', 'product_category_id', 'product_id') as $key)
        {
            if (!empty($values['_access'][$key]))
                foreach ($values['_access'][$key] as & $item)
                {
                    if (is_string($item)) $item = json_decode($item, true);
                    $item['start'] = $item['stop'] = $values['day'] . 'd';
                }
        }
        $values['_not_conditions'] = array_filter(array_map('filterId', $values['_not_conditions']));
        $values['not_conditions'] = implode(',', $values['_not_conditions']);
    }

    public function renderProducts(ResourceAbstract $resource)
    {
        $access_list = $resource->getAccessList();
        if (count($access_list) > 6)
            $s = count($access_list) . ' access records...';
        else
        {
            $s = "";
            foreach ($access_list as $access)
                $s .= sprintf("%s <b>%s</b> %s<br />\n", $access->getClass(), $access->getTitle(), "");
        }
        return $this->renderTd($s, false);
    }

}

class Am_Grid_Editable_Video extends Am_Grid_Editable_Content
{
    function  __construct(Am_Request $request, Am_View $view)
    {
        parent::__construct($request, $view);
        $this->addCallback(self::CB_VALUES_FROM_FORM, array($this, '_valuesFromForm'));
        $this->setFilter(new Am_Grid_Filter_Text(___('Filter by Title'), array('title'=>'LIKE')));
    }

    protected function initGridFields()
    {
        $this->addGridField('title', ___('Title'))->setRenderFunction(array($this, 'renderAccessTitle'));
        $this->addGridField('path', ___('Filename'))->setRenderFunction(array($this, 'renderPath'));
        $this->addGridField(new Am_Grid_Field_Expandable('_code', ___('JavaScript Code')))
            ->setGetFunction(array($this, 'renderJsCode'));
        parent::initGridFields();
    }

    protected function _valuesFromForm(& $values)
    {
        $path = $values['path'];
        $values['mime'] = is_numeric($path) ?
            $this->getDi()->uploadTable->load($path)->getType() :
            Upload::getMimeType($path);
    }

    public function renderJsCode(Video $video)
    {
        $type = $video->mime == 'audio/mpeg' ? 'audio' : 'video';

        $width = 550;
        $height = $type == 'video' ? 330 : 30;

        $root = Am_Controller::escape(ROOT_URL);
        $cnt = <<<CUT
<!-- the following code you may insert into any HTML, PHP page of your website or into WP post -->
<!-- you may skip including Jquery library if that is already included on your page -->
<script type="text/javascript" 
        src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
<!-- end of JQuery include -->
<!-- there is aMember video JS code starts -->
<!-- you can use GET variable width and height in src URL below
     to customize these params for specific entity
     eg. $root/$type/js/id/{$video->video_id}?width=$width&height=$height -->
<script type="text/javascript" id="am-$type-{$video->video_id}"
    src="$root/$type/js/id/{$video->video_id}">
</script>        
<!-- end of aMember video JS code -->
CUT;
        return "<pre>" . Am_Controller::escape($cnt) . "</pre>";
    }

    protected function createAdapter()
    {
        return new Am_Query(Am_Di::getInstance()->videoTable);
    }

    function createForm()
    {
        $form = new Am_Form_Admin;
        $form->setAttribute('enctype', 'multipart/form-data');
        $form->setAttribute('target', '_top');

        $maxFileSize = min(ini_get('post_max_size'), ini_get('upload_max_filesize'));
        $el = $form->addElement(new Am_Form_Element_Upload('path', array(), array('prefix' => 'video')))
                ->setLabel(___("Video/Audio File\n(max upload size %s)\nYou can use this feature only for video and\naudio formats that <a href=\"%s\" target=\"_blank\">supported by flowplayer</a>",
                    $maxFileSize, 'http://flowplayer.org/documentation/installation/formats.html'))
                ->setId('form-path');

        $jsOptions = <<<CUT
{
    onFileAdd : function (info) {
        var txt = $(this).closest("form").find("input[name='title']");
        if (txt.data('changed-value')) return;
        txt.val(info.name);
    }
}
CUT;
        $el->setJsOptions($jsOptions);
        $form->addScript()->setScript(<<<CUT
$(function(){
    $("input[name='title']").change(function(){
        $(this).data('changed-value', true);
    });
});
CUT
        );
        $el->addRule('required');

        $form->addText('title', array('size' => 50))->setLabel(___('Title'))->addRule('required', 'This field is required');
        $form->addText('desc', array('size' => 50))->setLabel(___('Description'))->addRule('required', 'This field is required');
        $form->addAdvCheckbox('hide')->setLabel(___("Hide\n" . "do not display this item link in members area"));

        $form->addElement(new Am_Form_Element_PlayerConfig('config'))->setLabel(array(___('Player Configuration'),
            ___('this option is applied only for video files')));

        $form->addElement(new Am_Form_Element_ResourceAccess)->setName('_access')->setLabel(___('Access Permissions'));
        return $form;
    }

    public function renderContent()
    {
        return $this->getPlayerInfo() . parent::renderContent();
    }

    function getPlayerInfo()
    {
        $out = "";
        if (!file_exists($fn = APPLICATION_PATH . '/default/views/public/js/flowplayer/flowplayer.js'))
            $out .= "Please upload file [<i>$fn</i>]<br />";
        if (!file_exists($fn = APPLICATION_PATH . '/default/views/public/js/flowplayer/flowplayer.swf'))
            $out .= "Please upload file [<i>$fn</i>]<br />";
        if (!file_exists($fn = APPLICATION_PATH . '/default/views/public/js/flowplayer/flowplayer.controls.swf'))
            $out .= "Please upload file [<i>$fn</i>]<br />";
        if (!file_exists($fn = APPLICATION_PATH . '/default/views/public/js/flowplayer/flowplayer.audio.swf'))
            $out .= "Please upload file [<i>$fn</i>]<br />";
        if ($out)
        {
            $out = "To starting sharing media files, you have to download either free or commercial version of <a href='http://flowplayer.org/'>FlowPlayer</a><br />"
                . $out;
        }
        return $out;
    }

}

class Am_Grid_Editable_ContentAll extends Am_Grid_Editable
{
    public function __construct(Am_Request $request, Am_View $view)
    {
        $di = Am_Di::getInstance();
        
        $ds = null;$i = 0; $key = null;
        foreach ($di->resourceAccessTable->getAccessTables() as $k => $t)
        {
            $q = new Am_Query($t);
            $q->clearFields();
            if (empty($key))
                $key = $t->getKeyField();
            $q->addField($t->getKeyField(), $key);
            $type = $t->getAccessType();
            $q->addField("'$type'", 'resource_type');
            $q->addField($t->getTitleField(), 'title');
            $q->addField($q->escape($t->getAccessTitle()), 'type_title');
            $q->addField($q->escape($t->getPageId()), 'page_id');
            
            if ($t instanceof EmailTemplateTable)
                $q->addWhere('name IN (?a)', array(EmailTemplate::AUTORESPONDER, EmailTemplate::EXPIRE));
            if (empty($ds))
                $ds = $q;
            else
                $ds->addUnion($q);
        }
        // yes we need that subquery in subquery to mask field names
        // to get access of fields of main query (!)
        $ds->addOrderRaw("(SELECT _sort_order
             FROM ( SELECT sort_order as _sort_order, 
                    resource_type as _resource_type, 
                    resource_id as _resource_id
                  FROM ?_resource_access_sort ras) AS _ras
             WHERE _resource_id=$key AND _resource_type=resource_type LIMIT 1),
             $key, resource_type");
        
        parent::__construct('_all', ___('All Content'), $ds, $request, $view, $di);
        $this->addField('type_title', ___('Type'));
        $this->addField('title', ___('Title'));
        
        $this->actionDelete('insert');
        $this->actionDelete('edit');
        $this->actionDelete('delete');
        
        $this->actionAdd(new Am_Grid_Action_ContentAllEdit('edit', ___('Edit'), ''));
        $this->actionAdd(new Am_Grid_Action_SortContent());
    }
}

/**
 * This field type once added allows to sort records by dragging or by changing 
 * sort number
 */
class Am_Grid_Action_SortContent extends Am_Grid_Action_Abstract
{
    protected $privilege = 'edit';
    protected $type = self::HIDDEN;
    protected $fieldName;
    protected $callback;
    /** @var Am_Grid_Decorator_LiveEdit */
    protected $decorator;
    protected static $jsIsAlreadyAdded = false;
    
    public function setGrid(Am_Grid_Editable $grid)
    {
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, array($this, 'getTrAttribs'));
        $grid->addCallback(Am_Grid_Editable::CB_RENDER_STATIC, array($this, 'renderStatic'));
        return parent::setGrid($grid);
    }
    function getTrAttribs(array & $attribs, $obj)
    {
        $attribs['data-id'] = $id = (int)$obj->pk();
        $type = $obj->get('resource_type');
        if (!$type) 
            $type = $this->grid->getDataSource()->createRecord()->getAccessType();
        $attribs['data-type'] = $type;
        $grid_id = $this->grid->getId();
        $params = array(
            $grid_id . '_' . Am_Grid_ReadOnly::ACTION_KEY => $this->getId(),
            $grid_id . '_type' => $type,
            $grid_id . '_' . Am_Grid_ReadOnly::ID_KEY => $id,
        );
        $attribs['data-params'] = json_encode($params);
    }
    public function renderStatic(& $out, Am_Grid_Editable $grid)
    {
        $url = json_encode($grid->makeUrl());
        $grid_id = $this->grid->getId();
        $msg = ___("Drag&Drop rows to change display order");
        $out .= <<<CUT
<i><div class="am-grid-drag-sort-message">$msg</div></i>
<script type="text/javascript">
jQuery(function($){
    $(".grid-wrap").ngrid("onLoad", function(){
        if ($(this).find("th .sorted-asc, th .sorted-desc").length)
        {
            $(this).sortable( "destroy" );
            return;
        }

        $(this).sortable({
            items: "tbody > tr.grid-row",
            update: function(event, ui) {
                var item = $(ui.item);
                var url = $url;
                var prevId = item.prev().data('id');
                var nextId = item.next().data('id');
                var prevT = item.prev().data('type');
                var nextT = item.next().data('type');
                var params = item.data('params');
                params.{$grid_id}_move_before =  nextId ? nextId : '';
                params.{$grid_id}_move_after =  prevId ? prevId : '';
                params.{$grid_id}_move_before_type =  nextT ? nextT : '';
                params.{$grid_id}_move_after_type =  prevT ? prevT : '';
                $.post(url, params, function(response){
                });
            },
        });
    });
});
</script>
CUT;
    }
    
    public function run()
    {
        $request = $this->grid->getRequest();
        $id = $request->getFiltered('id');
        $type = $request->getFiltered('type', $this->grid->getDataSource()->createRecord()->getAccessType());
        $move_before = $request->getFiltered('move_before');
        if ($move_before <= 0) $move_before = null;
        $move_after = $request->getFiltered('move_after');
        if ($move_after <= 0) $move_after = null;
        $move_before_type = $request->getFiltered('move_before_type', null);
        $move_after_type = $request->getFiltered('move_after_type', null);
        
        $accessTables = Am_Di::getInstance()->resourceAccessTable->getAccessTables();
        if (empty($accessTables[$type]))
            throw new Am_Exception_InputError("Wrong type: [$type]");
        
        $record = $accessTables[$type]->load($id, false);
        if (!$record) 
            throw new Am_Exception_InputError("Record [$id] not found");

        $resp = array(
            'ok' => true,
        );
        if ($this->callback)
            $resp['callback'] = $this->callback;
        try {
            $record->setSortBetween($move_after, $move_before, $move_after_type,
                $move_before_type);
        } catch (Exception $e) {
            throw $e;
            $resp = array('ok' => false, );
        }
        Am_Controller::ajaxResponse($resp);
        exit();
    }    
}

class Am_Grid_Action_ContentAllEdit extends Am_Grid_Action_Abstract
{
    protected $privilege = 'edit';
    protected $url;
    public function __construct($id, $title, $url)
    {
        $this->id = $id;
        $this->title = $title;
        $this->url = $url;
        parent::__construct();
        $this->setTarget('_top');
    } 
    public function getUrl($record = null, $id = null)
    {
        $id = $record->pk();
        $page_id = $record->page_id;
        $back_url = Am_Controller::escape($this->grid->getBackUrl());
        return REL_ROOT_URL . "/default/admin-content/p/$page_id/index?_{$page_id}_a=edit&_{$page_id}_b=$back_url&_{$page_id}_id=$id";
    }
    public function run() { }
}

class AdminContentController extends Am_Controller_Pages
{
    public function checkAdminPermissions(Admin $admin)
    {
        return $admin->hasPermission('grid_content');
    }

    public function preDispatch()
    {
        parent::preDispatch();
        $this->view->headScript()->appendFile(REL_ROOT_URL . "/application/default/views/public/js/resourceaccess.js");
        $this->view->headScript()->appendFile(REL_ROOT_URL . "/application/default/views/public/js/player-config.js");
    }
    
    public function initPages()
    {
        if (empty($this->getSession()->admin_content_sort_checked))
        {
            // dirty hack - we are checking that all content records have sort order
            $count = 0;
            foreach ($this->getDi()->resourceAccessTable->getAccessTables() as $k => $table)
                $count += $table->countBy();
            $countSort = $this->getDi()->db->selectCell("SELECT COUNT(*) FROM
                ?_resource_access_sort");
            if ($countSort != $count)
                $this->getDi()->resourceAccessTable->syncSortOrder();
            $this->getSession()->admin_content_sort_checked = 1;
        }
        //
        foreach ($this->getDi()->resourceAccessTable->getAccessTables() as $k => $table)
        {
            /* @var $table ResourceAbstractTable */
            $page_id = $table->getPageId();
            $this->addPage('Am_Grid_Editable_' . ucfirst($page_id), $page_id, $table->getAccessTitle());
        }
        $this->addPage('Am_Grid_Editable_ContentAll', 'all', ___('All'));
    }


    public function renderPage(Am_Controller_Pages_Page $page)
    {
        $this->setActiveMenu($page->getId() == 'all' ? 'content' : 'content-' . $page->getId());
        return parent::renderPage($page);
    }
}
