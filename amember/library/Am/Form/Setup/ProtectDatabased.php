<?php
/**
 * Configuration form for integration plugin
 * @see Am_Protect_Databased
 * @package Am_Protect
 */
class Am_Form_Setup_ProtectDatabased extends Am_Form_Setup
{
    protected $plugin;
    protected $groupsNeedRefresh = false;
    
    public function __construct(Am_Protect_Databased $plugin)
    {
        parent::__construct($plugin->getId());
        $this->setTitle($plugin->getTitle());
        $this->setComment($plugin->getTitle() . ' Configuration');
        $this->plugin = $plugin;
        $url = Am_Controller::escape(REL_ROOT_URL) . '/default/admin-content/p/integrations/index';
        $text = ___("Once the plugin configuration is finished on this page, do not forget to add\n".
                    "a record on %saMember CP -> Protect Content -> Integrations%s page",
            '<a href="'.$url.'" target="_blank">', '</a>');
        $this->addProlog(<<<CUT
<div class="warning_box">
    $text
</div>   
CUT
        );
    }
    
    /** @return Am_Protect_Databased */
    public function getPlugin()
    {
        return $this->plugin;
    }
    public function initElements()
    {
        parent::initElements();
        if(method_exists($this->plugin, "parseExternalConfig") && !$this->plugin->isConfigured())
                $this->addFolderSelect();
        $this->addOtherDb();
        $this->addDbPrefix();
        $this->addGroupSettings();
        if ($this->plugin->canAutoCreate())
        {
            $gr = $this->addGroup()->setLabel(___("Create aMember Users By Demand\n".
                "silently create customer in aMember if\n".
                "user tries to login into aMember with\n".
                "the same username and password as for %s", $this->getTitle()));
            $el = $gr->addAdvCheckbox('auto_create');
            $auto_create_id = $el->getId();
            $options = array('' => ___('Please Select'));
            if ($this->plugin->canAutoCreateFromGroups())
                $options['-1'] = ___('Add Access depends on current user\'s groups in '.$this->plugin->getTitle());
            foreach (Am_Di::getInstance()->billingPlanTable->selectAllSorted() as $p)
                try {
                    $k = $p->product_id.'_'.$p->plan_id;
                    $v = $p->getProduct()->title;
                    $v .= ' ('.$p->getTerms().')';
                    $options[$k] = $v;
                } catch (Exception $e){};
            $el = $gr->addSelect("auto_create_billing_plan")
                ->setLabel(___("Default Level\n".
                "users created by demand\n".
                "will have access to the sele\n".
                "(for example all subscriptions expired)"))
                ->loadOptions($options);
            $auto_create_billing_plan_id = $el->getId();
            $el = $gr->addStatic("auto_create_billing_plan_text")->setContent('<span id=auto_create_billing_plan_text-0><br/>'.
                ___('please select billing plan to add manual access to users added by demand').
                '</span>');
            $auto_create_billing_plan_text_id = $el->getId();
            if ($this->plugin->canAutoCreateFromGroups())
            {
                //$group_plans = $gr->addGroup('auto_create_bpgroups');
                $group_plans = $gr;
                unset($options['-1']);
                $group_plans->addStatic()->setContent('<div id="auto_create_billing_plan_products">');
                foreach($this->plugin->getAvailableUserGroups() as $g)
                {
                    $group_plans->addStatic()->setContent('<div class="acbp_left">'.$g->getTitle().'</div><div class="acbp_right">');
                    $group_plans->addElement('select',"auto_create_bpgroups_".$g->getId())->loadOptions($options);
                    $group_plans->addStatic()->setContent('</div><br/>');
                }
                $group_plans->addStatic()->setContent('</div>');
            }
        $this->addScript('script_auto_create')->setScript(<<<CUT
$(function(){
    $('#$auto_create_id').change(function(){
        $('#$auto_create_billing_plan_id,#$auto_create_billing_plan_text_id').toggle(this.checked);
        $('#auto_create_billing_plan_products').toggle($('#$auto_create_billing_plan_id').val()=='-1' && this.checked);
    }).change();
    $('#$auto_create_billing_plan_id').change(function(){
        $('#auto_create_billing_plan_products').toggle($('#$auto_create_billing_plan_id').val()=='-1' && $('#$auto_create_id').is(':checked'));
    }).change();
})
CUT
            );
            
        }
        $this->addFieldsPrefix("protect.{$this->pageId}.");
        $this->addScript('script')->setScript($this->getJs());
         if (defined($const = get_class($this->plugin)."::PLUGIN_STATUS") && (constant($const) == Am_Plugin::STATUS_BETA || constant($const) == Am_Plugin::STATUS_DEV)) 
        {
            $beta = (constant($const) == Am_Plugin::STATUS_DEV) ? 'ALPHA' : 'BETA';
            $this->addProlog("<div class='warning_box'>This plugin is currently in $beta testing stage, some functions may work unstable.".
                "Please test it carefully before use.</div>");
        }       
        $this->plugin->afterAddConfigItems($this);
    }
    public function ajaxAction()
    {
        $arr = $this->getConfigValuesFromForm();
        if(method_exists($this->plugin, "parseExternalConfig")
                && array_key_exists("path", $arr) && strlen($arr['path'])
                ){
            // Try to get config values from third party script;
            $ret = array();
            try{
                $ret['data'] = call_user_func(array($this->plugin, "parseExternalConfig"), $arr['path']);
            }catch(Exception $e){
                $ret['data'] = false;
                $ret['error'] = $e->getMessage();
            }
            return print Am_Controller::getJson($ret);
        }
        $class = get_class($this->plugin);
        $obj = new $class(Am_Di::getInstance(), $arr);
        try {
            $db = $obj->getDb();
        } catch (Am_Exception $e) {
            return print "Error - ". preg_replace('/ at .+$/', '', $e->getMessage());
        }
        return print "OK. Press 'Continue...' to refresh Database name autocompletion database";
    }

    public function addFolderSelect(){
        $title = $this->getTitle();
        $fs = $this->addFieldset('script-path')->setLabel(___('Path to %s', $title));
        $group = $fs->addGroup()->setLabel(___('Path to %s Folder', $title));
        $path = $group->addText('path')->setAttribute('size', 50)->addClass('dir-browser');
        $group->addStatic()->setContent('<div id="check-path-container"></div>');
        $this->addScript('script')->setScript('
        $(function(){
            $(".dir-browser").dirBrowser();
            $("#check-path-container").hide();
            $("input[name$=\'_path\']").change(function(){
                var parentForm = this.form;
                $.ajax({
                    "url"       :   window.rootUrl + \'/admin-setup/ajax\',
                    "type"      :   "POST",
                    "dataType"  :   "text",
                    "data"      :   $(this).parents("form").serialize(),
                    "success"   :
                    function(data){
                        data = eval( "(" + data + ")" );
                        if(!data.data){
                            $("#check-path-container").html(data.error).show().css({color: "red"});
                            return flashError(data.error);
                        }
                        $("#check-path-container").hide();
                        $("input[name$=\'_other_db\']").attr("checked", true).change();

                        for(i in data.data){
                            var e = $("input[name$=\'__"+i+"\']");
                            if(e.is(":checkbox"))
                                e.attr("checked", (data.data[i] ? true :  false)).change();
                            else
                                e.val(data.data[i]);
                        }
                        $("input[name$=\'__path\']").val("");

                    }
                    });
            });
        });
        ');

        return $fs; 

    }
    public function addOtherDb()
    {
        $title = $this->getTitle();
        $fs = $this->addFieldset('other-db')->setLabel(___('Use Database Connection other than configured for aMember'));

        $fs->addCheckbox("other_db")
            ->setLabel(___("Use another MySQL Db\n".
            "use custom host, user, password for %s database connection".
            "Usually you can leave this unchecked", $title));
        $fs->addText("user", array('class'=>'other-db'))->setLabel(___('%s MySQL Username', $title))
            ->setId('other-db-user');
        $fs->addPassword("pass", array('class'=>'other-db'))->setLabel(___('%s MySQL Password', $title))
            ->setId('other-db-pass');
        $group = $fs->addGroup("")->setLabel(___('%s MySQL Hostname', $title));
        $group->addText('host', array('class'=>'other-db'))
            ->setId('other-db-host');
        $group->addInputButton('test-other-db', array('value' => ___('Test Settings')));
        $group->addStatic()->setContent('<div id="other-db-test-result"></div>');

        $this->addScript()->setScript(<<<CUT
$(function()
{
    $("input[name$='_other_db']").change(function(){
        $("input.other-db").parents(".row").toggle(this.checked);
    }).change();

    $("#test-other-db-0").click(function(){
        var btn = $(this);
        var val = btn.val();
        if (!$("#other-db-user").val()) return flashError("Please enter MySQL username first");
        if (!$("#other-db-pass").val()) return flashError("Please enter MySQL password first");
        if (!$("#other-db-host").val()) return flashError("Please enter MySQL hostname or IP first");
        if (!$("#db-0").val()) return flashError("Please enter MySQL database name");
        btn.val("Testing...");
        $("#other-db-test-result").load(window.rootUrl + '/admin-setup/ajax', $(this).parents("form").serialize()+'&test_db=1', function(data){
            btn.val(val); 
            if (!data.match(/^OK/)) 
                $("#other-db-test-result").css("color", "red");
            else
                $("#other-db-test-result").css("color", "blue");
        });
    });
});
CUT
);
        return $fs;
    }

    public function addDbPrefix()
    {
        $title = $this->getTitle();
        $fs = $this->addFieldset('db-prefix')->setLabel(___("%s database and tables prefix", $title));

        $group = $fs->addGroup()->setLabel("$title Database name and Tables Prefix");
        $group->addText("db", array('class'=>'db-prefiix'))->addRule('required', 'this field is required');
        $group->addText("prefix", array('class'=>'db-prefiix'));
        $group->addRule('callback2', '-error-', array($this, 'configCheckDbSettings'));
        try {
            $a = array();
            foreach ($this->plugin->guessDbPrefix(Am_Di::getInstance()->db) as $v)
            {
                list($d,$p) = explode('.', $v, 2);
                $a[] = array('label'=>$v, 'value'=>$d);
            }
            if ($a)
            {
            $guessDb = Am_Controller::getJson((array)$a);
            $this->addScript('guess_db_script')->setScript(<<<CUT
$(function(){
    $("input[name$='___db']").autocomplete({
        source : $guessDb,
        minLength: 0
    }).focus(function(){
        $(this).autocomplete("search", "");
    }).bind( "autocompleteselect", function(event, ui) {
        var a = ui.item.label.split(".", 2);
        $(event.target).autocomplete("close");
        $("input[name$='___prefix']").val(a[1]);
    });
});
CUT
            );
            }
        } catch (Am_Exception $e) {
        }
    }
    public function getConfigValuesFromForm()
    {
        $arr = array();
        foreach ($this->getValue() as $k => $v)
            if (($kk = str_replace($this->fieldsPrefix, '', $k, $count)) && $count)
                $arr[ $kk ] = $v;
        return $arr;
    }
    
    public function configCheckDbSettings()
    {
        $arr = $this->getConfigValuesFromForm();
        $ret = $this->plugin->configCheckDbSettings($arr);
        if (!$ret)
        {
            $class = get_class($this->plugin);
            $this->plugin = new $class(Am_Di::getInstance(), $arr);
            if ($this->groupsNeedRefresh)
                $this->refreshGroupSettings();
        }
        return $ret;
    }
    public function saveConfig()
    {
        if ($this->getElementById('group_settings_hidden-0')->getValue() == '1')
            return false;
        return parent::saveConfig();
    }
    public function refreshGroupSettings()
    {
        if ($this->plugin->getGroupMode() != Am_Protect_Databased::GROUP_NONE)
        {
            try {
                $groups = $this->plugin->getAvailableUserGroups();
            } catch (Am_Exception_Db $e){ // to avoid errors while db is not yet configured
                $groups = array();
                $this->groupsNeedRefresh = true;
            }
            $adminGroups = array();
            $bannedGroups = array();
            $options = array();
            foreach ($groups as $g)
            {
                $options[ $g->getId() ] = $g->getTitle();
                if ($g->isAdmin()) $adminGroups[] = $g->getId();
                if ($g->isBanned()) $bannedGroups[] = $g->getId();
            }
            $this->getElementById('default_group-0')->loadOptions(array('' => '-- Please select --') + $options);

            $this->getElementById('admin_groups-0')->loadOptions($options);
            
            $this->getElementById('banned_groups-0')->loadOptions($options);
            
            $dataSources = $this->getDataSources();
            // must we check if such variables have been passed? 
            array_unshift($dataSources, new HTML_QuickForm2_DataSource_Array($arr = array(
//                self::name2underscore($this->getElementById('default_group-0')->getName()) => $default,
                self::name2underscore($this->getElementById('admin_groups-0')->getName()) => $adminGroups,
                self::name2underscore($this->getElementById('banned_groups-0')->getName()) => $bannedGroups,
            )));
            $this->setDataSources($dataSources);
            if ($groups) $this->groupsNeedRefresh = false;
        }
    }

    public function addGroupSettings()
    {
        $title = $this->getTitle();

        $fs = $this->addFieldset('settings')->setLabel("$title Integration Settings");
        $fs->addHidden('group_settings_hidden')->setValue('0');
        
        if ($this->plugin->getGroupMode() != Am_Protect_Databased::GROUP_NONE)
        {
            try {
                $groups = $this->plugin->getAvailableUserGroups();
            } catch (Am_Exception_Db $e){ // to avoid errors while db is not yet configured
                $groups = array();
                $this->groupsNeedRefresh = true;
            }
            $adminGroups = array();
            $bannedGroups = array();
            $options = array();
            foreach ($groups as $g)
            {
                $options[ $g->getId() ] = $g->getTitle();
                if ($g->isAdmin()) $adminGroups[] = $g->getId();
                if ($g->isBanned()) $bannedGroups[] = $g->getId();
            }
            $fs->addSelect("default_group")
                ->setLabel(___("Default Level\n".
                "default level - user reset to this access level\n".
                "if no active subscriptions exists\n".
                "(for example all subscriptions expired)"))
                ->loadOptions(array('' => '-- Please select --') + $options);
//                ->addRule('required', 'This field is required');
            $fs->addMagicSelect("admin_groups")
                ->setLabel(___("Admin Groups\n".
                "aMember never touches %s accounts\n".
                "assigned to the following groups. This protects\n".
                "%s accounts against any aMember activity"
                , $title, $title . ' ' . ___('admin')))
                ->loadOptions($options);
            $fs->addMagicSelect("banned_groups")
                ->setLabel(___("Banned Groups\n".
                "aMember never touches %s accounts\n".
                "assigned to the following groups. This protects\n".
                "%s accounts against any aMember activity"
                , $title, $title . ' ' . ___('banned')))
                ->loadOptions($options);
            
            $fs->addElement(new Am_Form_Element_SortableList("priority"))
               ->loadOptions($options)
               ->setLabel(___("Groups Priority\n".
                   "you may drag and drop groups to sort it.\n".
                   "if there are several groups available for user\n".
                   "aMember will choose groups that are higher\n".
                   "in this list as \"Primary\""));
            
            $fs->addScript()->setScript(<<<CUT
jQuery(function($){ // hide and disable elements in Priority list once it is added to banned or admin list
    $("#banned_groups-0.magicselect,#admin_groups-0.magicselect").change(function(){
        var val = $("#banned_groups-0.magicselect").val();
        $.merge(val, $("#admin_groups-0.magicselect").val());
        $("#priority-0 input[type=hidden]").each(function(){
            var disabled = val.indexOf(this.value) > -1;
            $(this).prop("disabled", disabled ? true : null)
                .closest("li").toggle(!disabled);
        });
    }).change();
});   
CUT
            );
            
        }
        $fs->addAdvCheckbox("remove_users")
            ->setLabel(___("Remove Users\n".
            "when user record removed from aMember\n".
            "must the related record be removed from %s", $title));
    }
    public function getJs()
    {
        $continue = Am_Controller::escape(___("Continue"));
        return <<<CUT
$(function(){
    var db = $("input[name$='___db']");
    var prefix = $("input[name$='___prefix']");
    var isDbWrong = (!db.val() && !prefix.val()) || db.parents(".element.error").length>0;
    if (isDbWrong)
    {
        db.parents("fieldset").nextUntil("#row-save-0").hide();
        $("#save-0").val("$continue...");
        $("input[name$='group_settings_hidden']").val("1");
    } else {
        $("input[name$='group_settings_hidden']").val("0");
    }
});
CUT;
    }
    
}