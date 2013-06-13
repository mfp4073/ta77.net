<?php

include_once("api.php");

class Am_Protect_Wordpress extends Am_Protect_Databased
{

    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    protected $_timezone_backup = null;
    protected $_autoload_backup = array();
    protected $_headers_backup = array();
    protected $_include_path = null;
    protected $_remove_headers = array('Last-Modified', 'Expires');
    protected $_current_view;
    protected $_page_title;
    protected $safe_jquery_load = false;
    protected $_wp;
    private $_toSave = array('_POST', '_GET', '_REQUEST');
    private $_savedVars = array();
    protected $guessTablePattern = 'users';
    protected $guessFieldsPattern = array(
        'ID', 'user_login', 'user_pass', 'user_nicename', 'display_name'
    );
    protected $groupMode = self::GROUP_MULTI;
    protected $wpmu = null;

    const WPMU_BLOG_ID = 'wpmu_blog_id';

    public function init()
    {
        parent::init();
        if ($this->isConfigured() && $this->getConfig('network') && $this->getConfig('network_add_to_blogs'))
        {
            include_once "network.php";
            $plugin = new Am_Protect_WPNetwork($this->getDi(), $this->getConfig(), $this);
            $this->getDi()->plugins_protect->register($plugin->getId(), $plugin);
            $this->getDi()->plugins_protect->addEnabled($plugin->getId());
            $this->wpmu = $plugin;
        }
    }

    /**
     *
     * @return Am_Protect_WPNetwork $plugin
     * 
     */
    function getNetworkPlugin()
    {
        return $this->wpmu;
    }

    public function canAutoCreate()
    {
        return true;
    }

    public function getLevels()
    {
        $ret = array();
        for ($i = 0; $i <= 10; $i++)
        {
            $ret[$i] = 'level_' . $i;
        }
        return $ret;
    }

    public function getIntegrationFormElements(HTML_QuickForm2_Container $group)
    {
        parent::getIntegrationFormElements($group);
        
        if ($this->getConfig('buddy_press'))
        {
            $groups = $this->getBuddyPressGroups();
            $available_groups = $this->getConfig('buddy_press_groups');

            foreach ($groups as $k => $v)
                if (!in_array($k, $available_groups))
                    unset($groups[$k]);

            $group->addSelect('buddy_press_group', array(), array('options' => array('' => '-- Select Group -- ') + $groups))
                ->setLabel('BuddyPress Group');
        }

        if ($this->getConfig('network') && $this->getConfig('network_create_blog'))
        {
            $group->addAdvCheckbox('create_blog')->setLabel('Create blog for user');
        }

        /*
          $options = $this->getLevels();
          $group->addSelect('level', array(), array('options'=>$options))->setLabel('Wordpress Level');
         */
    }

    public function afterAddConfigItems(Am_Form_Setup_ProtectDatabased $form)
    {
        parent::afterAddConfigItems($form);
        /*
          $options = $this->getLevels();
          $form->addSelect('protect.wordpress.default_wplevel', array(), array("options" =>$options))
          ->setLabel(array("Default user level", "default level - user will be reset to this access level
          when no active subscriptions exists (for example all subscriptions expired)
          "));
         */
        $configPrefix = sprintf('protect.%s.', $this->getId());
        $form->addText($configPrefix . 'folder', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Folder', "
                 Folder where you have " . $this->getTitle() . " installed"));
        $form->addAdvCheckbox($configPrefix . 'use_wordpress_theme')
            ->setLabel(array('Use Wordpress theme', 'aMember will use theme that you set in wordpress for header and footer'));
        /* $form->addAdvCheckbox('protect.wordpress.network')
          ->setLabel(array('Network Enabled', 'Check this if you have Wordpress Network Enabled')); */

        $form->addText($configPrefix . 'auth_key', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Auth Key', "
          AUTH_KEY setting from " . $this->getTitle() . " config"));
        $form->addText($configPrefix . 'secure_auth_key', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Secure Auth Key', "
          SECURE_AUTH_KEY setting from " . $this->getTitle() . " config"));
        $form->addText($configPrefix . 'logged_in_key', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Logged In Key', "
          LOGGED_IN_KEY setting from " . $this->getTitle() . " config"));
        $form->addText($configPrefix . 'nonce_key', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Nonce Key', "
          NONCE_KEY setting from " . $this->getTitle() . " config"));

        $form->addText($configPrefix . 'auth_salt', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Auth Salt', "
          AUTH_SALT setting from " . $this->getTitle() . " config"));
        $form->addText($configPrefix . 'secure_auth_salt', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Secure Auth Salt', "
          SECURE_AUTH_SALT setting from " . $this->getTitle() . " config"));
        $form->addText($configPrefix . 'logged_in_salt', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Logged In Salt', "
          LOGGED_IN_SALT setting from " . $this->getTitle() . " config"));
        $form->addText($configPrefix . 'nonce_salt', array('size' => 70))
            ->setLabel(array($this->getTitle() . ' Nonce Salt', "
          NONCE_SALT setting from " . $this->getTitle() . " config"));

        $form->addSelect($configPrefix . 'display_name', '', array('options' => array(
                    'username' => 'Username',
                    'name_f_name_l' => 'First & Last Name'
                )))
            ->setLabel('Set display name for new users to: ');

        if ($this->haveSimplePress() || $this->getConfig('simple_press'))
        {
            $form->addAdvCheckbox($configPrefix . 'simple_press')->setLabel("Update SimplePress database");
        }

        if ($this->haveBuddyPress() || $this->getConfig('buddy_press'))
        {
            $form->addAdvCheckbox($configPrefix . 'buddy_press')
                ->setLabel("Enable BuddyPress Groups Support")
                ->setId('buddy-press');

            $form->addMagicSelect($configPrefix . 'buddy_press_groups', array(), array('options' => $this->getBuddyPressGroups()))
                ->setLabel(array(
                    "Manage only these BP groups",
                    'aMember will have full controll on these groups'
                ))
                ->setId('buddy-press-groups');

            $form->addScript()->setScript(<<<CUT
$(function(){
    $('#buddy-press').change(function(){
        $("#buddy-press-groups").closest('.row').toggle(this.checked);
    }).change();
});
CUT
            );
        }

        if ($this->haveNetworkSupport() || $this->getConfig('network'))
        {
            $form->addAdvCheckbox($configPrefix . 'network')
                ->setLabel('Enable Wordpress Network Support')
                ->setId('network');

            $form->addAdvCheckbox($configPrefix . 'network_create_blog')
                ->setLabel(array(
                    "Enable 'Create Blog' functionality",
                ))
                ->setId('network-create-blog');
            $form->addAdvCheckbox($configPrefix . 'network_add_to_blogs')
                ->setLabel(array(
                    "Enable 'Add user to different blogs'",
                ))
                ->setId('network-add-to-blogs');

            $form->addScript()->setScript(<<<CUT
$(function(){
    $('#network').change(function(){
        $("#network-create-blog").closest('.row').toggle(this.checked);
        $("#network-add-to-blogs").closest('.row').toggle(this.checked);
    }).change();
});
CUT
            );
        }
    }

    function getBuddyPressGroups()
    {
        $ret = array();
        try
        {
            foreach ($this->getDb()->select("SELECT * FROM ?_bp_groups") as $g)
            {
                $ret[$g['id']] = $g['status'] . ' : ' . $g['name'];
            }
        }
        catch (Exception $e)
        {
            $ret = array();
        }
        return $ret;
    }

    public function getPasswordFormat()
    {
        return SavedPassTable::PASSWORD_PHPASS;
    }

    public function onAuthSessionRefresh(Am_Event_AuthSessionRefresh $event)
    {
        // Make sure that parent hook is executed because it will login user into wordpress after signup. 
        parent::onAuthSessionRefresh($event);
        $this->saveLinksToSession($event->getUser());
    }

    public function saveLinksToSession(User $user)
    {
        $resources = $this->getDi()->resourceAccessTable->getAllowedResources($user, ResourceAccess::USER_VISIBLE_TYPES);
        $links = array();
        foreach ($resources as $r)
        {
            $link = $r->renderLink();
            if ($link)
                $links[] = $link;
        }
        $this->getDi()->session->amember_links = $links;
    }

    public function loginUser(Am_Record $record, $password)
    {

        $cookie_secure = $this->getWP()->get_user_meta($record->pk(), 'use_ssl');
        $this->getWP()->wp_set_auth_cookie($record->pk(), false, $cookie_secure, $record);
        $this->saveLinksToSession($user = $this->getTable()->findAmember($record));

        if ($this->getConfig('network') && $this->getConfig('network_add_to_blogs') && $groups = $this->calculateNetworkGroups($user))
        {
            foreach ($groups as $blog_id => $gr)
            {
                switch_to_blog($blog_id);
                wp_set_auth_cookie($record->pk());
                restore_current_blog();
            }
        }
    }

    public function logoutUser(User $user)
    {
        $this->getWP()->wp_clear_auth_cookie();
        if ($this->getConfig('network') && $this->getConfig('network_add_to_blogs') && $groups = $this->calculateNetworkGroups($user))
        {
            foreach ($groups as $blog_id => $gr)
            {
                switch_to_blog($blog_id);
                wp_clear_auth_cookie();
                restore_current_blog();
            }
        }
    }

    public function getLoggedInRecord()
    {
        if (!($user_id = $this->getWP()->wp_validate_auth_cookie()))
        {
            $logged_in_cookie = $this->getWP()->getLoggedInCookie();
            if (empty($_COOKIE[$logged_in_cookie]) || !$user_id = $this->getWP()->wp_validate_auth_cookie($_COOKIE[$logged_in_cookie], 'logged_in'))
                return;
        }
        $record = $this->getTable()->load($user_id, false);
        return $record;
    }

    public function parseExternalConfig($path)
    {
        // Now set config fields as required by aMember;
        if (!is_file($config_path = $path . "/wp-config.php") || !is_readable($config_path))
            throw new Am_Exception_InputError("This is not a valid " . $this->getTitle() . " installation");
        // Read config;
        $config = file_get_contents($config_path);
        $config = preg_replace(array("/include_once/", "/require_once/", "/include/", "/require/"), "trim", $config);
        $config = preg_replace(array("/\<\?php/", "/\?\>/"), "", $config);
        eval($config);
        return array(
            'db' => DB_NAME,
            'prefix' => $table_prefix,
            'host' => DB_HOST,
            'user' => DB_USER,
            'pass' => DB_PASSWORD,
            'folder' => $path,
            'auth_key' => AUTH_KEY,
            'secure_auth_key' => SECURE_AUTH_KEY,
            'logged_in_key' => LOGGED_IN_KEY,
            'nonce_key' => NONCE_KEY,
            'auth_salt' => AUTH_SALT,
            'secure_auth_salt' => SECURE_AUTH_SALT,
            'logged_in_salt' => LOGGED_IN_SALT,
            'nonce_salt' => NONCE_SALT,
        );
    }

    public function getAvailableUserGroups()
    {
        $ret = array();
        foreach ($this->getWP()->get_roles() as $rname => $r)
        {
            $g = new Am_Protect_Databased_Usergroup(array(
                    'id' => $rname,
                    'title' => $r['name'],
                    'is_banned' => null,
                    'is_admin' => (in_array('level_10', array_keys($r['capabilities'])) ? $r['capabilities']['level_10'] : 0)
                ));
            if($g->getId() == 'subscriber') 
                array_unshift ($ret, $g);
            else
                $ret[$g->getId()] = $g;
        }
        return $ret;
    }

    function getDisplayName(User $user)
    {
        switch ($this->getConfig('display_name', 'username'))
        {
            case 'name_f_name_l' : return $user->name_f . ' ' . $user->name_l;
            case 'username' :
            default:
                return $user->login;
        }
    }

    public function createTable()
    {
        $table = new Am_Protect_Wordpress_Table($this, $this->getDb(), '?_users', 'ID');
        $table->setFieldsMapping(array(
            array(Am_Protect_Table::FIELD_LOGIN, 'user_login'),
            array(Am_Protect_Table::FIELD_LOGIN, 'user_nicename'),
            array(array($this, 'getDisplayName'), 'display_name'),
            array(Am_Protect_Table::FIELD_EMAIL, 'user_email'),
            array(Am_Protect_Table::FIELD_PASS, 'user_pass'),
            array(Am_Protect_Table::FIELD_ADDED_SQL, 'user_registered')
        ));
        return $table;
    }

    public function onInitFinished()
    {
        /* @var $b Bootstrap */
    }

    protected function saveGlobalVars()
    {
        foreach ($this->_toSave as $k)
        {
            $this->_savedVars[$k] = $GLOBALS[$k];
        }
        $this->_savedVars['_SESSION'] = array();
        foreach ($_SESSION as $k => $v)
        {
            $this->_savedVars['_SESSION'][$k] = $v;
        }
    }

    protected function restoreGlobalVars()
    {
        foreach ($this->_toSave as $k)
        {
            $GLOBALS[$k] = $this->_savedVars[$k];
        }
        foreach ($this->_savedVars['_SESSION'] as $k => $v)
        {
            $_SESSION[$k] = $v;
        }
    }

    public function onGlobalIncludes(Am_Event_GlobalIncludes $e)
    {
        if ($this->isConfigured() && ($this->getConfig('use_wordpress_theme') || $this->getConfig('network')))
        {
            // Disable autoload; 
            //Save superglobals to avoid modification in wordpress.
            $this->saveGlobalVars();
            foreach (spl_autoload_functions() as $f)
            {
                $this->_autoload_backup[] = $f;
                spl_autoload_unregister($f);
            }
            $this->_include_path = get_include_path();
            // Add theme folder to include path; 
            define("WP_CACHE", false);
            define("WP_USE_THEMES", false);
            // Save headers that was sent by aMember;
            $this->_headers_backup = headers_list();

            $this->_timezone_backup = date_default_timezone_get();

            $e->add($this->config['folder'] . "/wp-blog-header.php");
        }
    }

    public function onglobalIncludesFinished()
    {
        if ($this->isConfigured() && ($this->getConfig('use_wordpress_theme') || $this->getConfig('network')))
        {

            date_default_timezone_set($this->_timezone_backup);
            set_exception_handler(array($this->getDi()->app, '__exception'));
            foreach (headers_list() as $l)
            {
                if (strpos($l, ':') !== false)
                {
                    // header can be unset;
                    list($k, ) = explode(':', $l);
                    if (in_array($k, $this->_remove_headers))
                        if (function_exists('header_remove'))
                            header_remove($k);
                        else
                            header($k . ":");
                }
            }
            // Now set headers again.
            foreach ($this->_headers_backup as $line)
                header($line);

            set_include_path($this->_include_path);
            foreach ($this->_autoload_backup as $f)
            {
                spl_autoload_register($f);
            }
            // Restore superglobals;
            $this->restoreGlobalVars();
            // Change template path only if wordpress was included and use wordpress header is enabled. 

            if (function_exists('status_header') && $this->getConfig('use_wordpress_theme'))
            {
                $path = defined("TEMPLATEPATH") ? TEMPLATEPATH : 'default';
                $path = array_pop(preg_split('/[\/\\\]/', $path));
                if (is_file(dirname(__FILE__) . '/' . $path . '/layout.phtml'))
                {
                    $path = $path;
                }
                else if (preg_match("/^([a-zA-Z]+)/", $path, $regs) && is_file(dirname(__FILE__) . '/' . $regs[1] . '/layout.phtml'))
                {
                    $path = $regs[1];
                }
                else
                {
                    $path = 'default';
                }

                $this->getDi()->viewPath = array_merge($this->getDi()->viewPath, array(dirname(__FILE__) . '/' . $path));
                // Setup scripts and path required for wordpress;
                status_header(200); // To prevent 404 header from wordpress;
                wp_enqueue_script("jquery");
            }
        }
    }

    function addHeader()
    {
        $this->_current_view->printLayoutHead(false, $this->safe_jquery_load);
    }

    function addTitle()
    {
        return $this->_page_title . " | ";
    }

    function startLayout(Am_View $view, $title, $safe_jquery_load = false)
    {

        $this->_current_view = $view;
        $this->_page_title = $title;
        $this->safe_jquery_load = $safe_jquery_load;
        add_action("wp_head", array($this, "addHeader"));
        add_filter("wp_title", array($this, "addTitle"), 10);
        $GLOBALS[wp_query]->is_404 = false;
    }

    function getWP()
    {
        if (!$this->_wp)
        {
            $this->_wp = new WordpressAPI($this);
        }
        return $this->_wp;
    }

    function calculateLevels(User $user = null, $addDefault = false)
    {
        throw new Am_Exception('Deprecated!');

        // we have got no user so search does not make sense, return default group if configured
        $levels = array();
        if ($user && $user->pk())
        {
            foreach ($this->getIntegrationTable()->getAllowedResources($user, $this->getId()) as $integration)
            {
                $vars = unserialize($integration->vars);
                $levels[] = $vars['level'];
            }
        }
        if (!$levels)
        {
            return $this->getConfig('default_wplevel', 0);
        }
        else
        {
            return max($levels);
        }
    }

    function calculateBuddyPressGroups(User $user)
    {
        $groups = array();
        if ($user && $user->pk())
        {
            foreach ($this->getIntegrationTable()->getAllowedResources($user, $this->getId()) as $integration)
            {
                $vars = unserialize($integration->vars);
                $levels[] = $vars['buddy_press_group'];
            }
        }
        return array_filter(array_unique($levels));
    }

    /**
     * Whether blog should be created for user or not.
     * @param User $user 
     */
    function haveNetworkBlogAccess(User $user)
    {
        $groups = array();
        if ($user && $user->pk())
        {
            foreach ($this->getIntegrationTable()->getAllowedResources($user, $this->getId()) as $integration)
            {
                $vars = unserialize($integration->vars);
                if ($vars['create_blog'])
                    return true;
            }
        }
        return false;
    }

    function calculateGroups(User $user = null, $addDefault = false)
    {
        $groups = parent::calculateGroups($user, $addDefault);
        if ($this->getConfig('network') && $this->getConfig('network_add_to_blogs') && $this->calculateNetworkGroups($user))
        {
            $groups[] = 'amember_active';
        }
        if ($groups && $user)
        {
            $add_group = ($this->getIntegrationTable()->getAllowedResources($user, $this->getId()) ? 'amember_active' : 'amember_expired');
            if (!in_array($add_group, $groups))
                $groups[] = $add_group;
        }
        return $groups;
    }

    function calculateNetworkGroups(User $user = null)
    {
        $groups = array();
        if ($user && $user->pk())
        {
            foreach ($this->getIntegrationTable()->getAllowedResources($user, $this->getNetworkPlugin()->getId()) as $integration)
            {
                $vars = unserialize($integration->vars);
                if ($vars['gr'])
                    $groups[@$vars['blog_id']][] = $vars['gr'];
            }
        }

        return $groups;
    }

    function getReadme()
    {
        return <<<CUT
<b>Wordpress plugin readme</b>
1. Specify full path to folder where you have wordpress script installed. 
   (You can use "browse" to select it)
2. Check database settings and click "Continue..." button
3. Check all configuration settings, set Default level and Default user level 
   if necessary. Click "Save" button. 
   Do not change any settings if you are not sure.
4. Go to aMember CP -> Products -> Protect Content -> Integrations and setup protection. 

<b>Optionally</b> you can install aMember plugin into wordpress in order to 
protect content in wordpress itself: 
1. Upload plugin files from 
   /amember/application/default/plugins/protect/wordpress/upload_to_wordpress folder 
   into your /wordpress folder (keep folders structure)
2. Enable amember4 plugin from your Wordpress Admin -> Plugins
3. In Wordpress Admin -> aMember -> Settings select folder where you have aMember installed.

CUT;
    }

    function haveSimplePress()
    {
        try
        {
            $this->_db->query('select count(*) from ?_sfmembers');
        }
        catch (Exception $e)
        {
            return false;
        }
        return true;
    }

    function haveBuddyPress()
    {
        try
        {
            $this->_db->query('select count(*) from ?_bp_groups');
        }
        catch (Exception $e)
        {
            return false;
        }
        return true;
    }

    function haveNetworkSupport()
    {
        try
        {
            $this->_db->query('select count(*) from ?_blogs');
        }
        catch (Exception $e)
        {
            return false;
        }
        return true;
    }

}

if (!class_exists('Am_Protect_Wordpress_Table', false))
{

    class Am_Protect_Wordpress_Table extends Am_Protect_Table
    {

        function __construct(Am_Protect_Databased $plugin, $db = null, $table = null, $recordClass = null, $key = null)
        {
            parent::__construct($plugin, $db, $table, $recordClass, $key);
        }

        /**
         * @return Am_Protect_Wordpress $plugin
         */
        function getPlugin()
        {
            return parent::getPlugin();
        }

        function updateSimplePress(Am_Record $record)
        {
            $this->getPlugin()->getDb()->query('
                INSERT INTO ?_sfmembers 
                (user_id, display_name, moderator, avatar, posts, lastvisit, checktime, user_options)
                VALUES
                (?, ?, ?, ?, ?, now(), now(), ?) 
                ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)
                ', $record->pk(), $record->display_name, 0, serialize(array('uploaded' => '')), -1, serialize(array(
                    "hidestatus" => 0, "timezone" => 0, "timezone_string" => "UTC", "editor" => 1, "namesync" => 1, "unreadposts" => 50
                ))
            );
        }

        function updateSimplePressMemberships(Am_Record $record, $groups)
        {
            $this->getPlugin()->getDb()->query('DELETE FROM ?_sfmemberships WHERE user_id = ?', $record->pk());

            $sfgroups = array();
            $default = '';
            foreach ($this->getPlugin()->getDb()->selectPage($total, "
            SELECT meta_key AS wpgroup, meta_value AS sfgroup
            FROM ?_sfmeta where meta_type = 'default usergroup'
            ") as $gr)
            {
                if (in_array($gr['wpgroup'], $groups))
                    $sfgroups[] = $gr['sfgroup'];
                if ($gr['wpgroup'] == 'sfmembers')
                    $default = $gr['sfgroup'];
            }
            $sfgroups = array_filter(array_unique($sfgroups));
            if (empty($sfgroups))
                $sfgroups[] = $default;
            foreach ($sfgroups as $v)
            {
                $this->getPlugin()->getDb()->query('INSERT INTO ?_sfmemberships set user_id = ?, usergroup_id=?', $record->pk(), $v);
            }
        }

        function updateBuddyPressProfile(Am_Record $record)
        {

            if (!$this->_db->selectCell('select count(*) from ?_bp_xprofile_data where user_id=? and field_id=1', $record->pk()))
                $this->_db->query('
                    INSERT INTO ?_bp_xprofile_data
                    (user_id, value, field_id, last_updated)
                    VALUES
                    (?, ?, 1, now()) 
                    ', $record->pk(), $record->display_name
                );
        }

        function getBuddyPressGroups(Am_Record $record)
        {
            return $this->_db->selectCol('SELECT group_id FROM ?_bp_groups_members WHERE user_id=?', $record->pk());
        }

        function updateBuddyPressGroups(Am_Record $record, User $user)
        {
            $oldGroups = $this->getBuddyPressGroups($record);
            $newGroups = $this->getPlugin()->calculateBuddyPressGroups($user);
            // First filter oldGroups and remove groups which are not related to aMember.
            $oldGroups = array_intersect($oldGroups, $this->getPlugin()->getConfig('buddy_press_groups'));

            $added = array_unique(array_diff($newGroups, $oldGroups));
            $deleted = array_unique(array_diff($oldGroups, $newGroups));

            if ($deleted)
                $this->_db->query("DELETE FROM ?_bp_groups_members  WHERE user_id=? AND group_id IN (?a)", $record->pk(), $deleted);

            if ($added)
                foreach ($added as $g)
                {
                    $this->_db->query("
                        INSERT INTO ?_bp_groups_members 
                        (user_id, group_id, user_title, date_modified, is_confirmed)
                        VALUES
                        (?, ?, ?, now(), ?)", $record->pk(), $g, $record->display_name, 1);
                }
        }

        function removeFromSimplePress(Am_Record $record)
        {
            $this->getPlugin()->getDb()->query('DELETE FROM ?_sfmembers WHERE user_id = ?', $record->pk());
            $this->getPlugin()->getDb()->query('DELETE FROM ?_sfmemberships WHERE user_id = ?', $record->pk());
        }

        function updateMetaTags(Am_Record $record, User $user)
        {
            $this->_plugin->getWP()->update_user_meta($record->pk(), 'first_name', $user->name_f);
            $this->_plugin->getWP()->update_user_meta($record->pk(), 'last_name', $user->name_l);
            $this->_plugin->getWP()->update_user_meta($record->pk(), 'nickname', $user->login);
            $this->_plugin->getWP()->update_user_meta($record->pk(), 'rich_editing', 'true');
        }

        function updateLevel(Am_Record $record, $level)
        {
            $this->_plugin->getWP()->update_user_meta($record->pk(), $this->_plugin->getConfig('prefix') . "user_level", $level);
        }

        function insertFromAmember(User $user, SavedPass $pass, $groups)
        {
            $record = parent::insertFromAmember($user, $pass, $groups);
            $this->updateMetaTags($record, $user);

            if ($this->getPlugin()->getConfig('simple_press'))
                $this->updateSimplePress($record);


            if ($this->getPlugin()->getConfig('buddy_press'))
            {
                $this->updateBuddyPressProfile($record);
                $this->updateBuddyPressGroups($record, $user);
            }
            if ($this->getPlugin()->getConfig('network'))
            {

                if ($this->getPlugin()->getConfig('network_create_blog'))
                    $this->updateNetworkBlogStatus($record, $user);

                if ($this->getPlugin()->getConfig('network_add_to_blogs'))
                    $this->networkAddToBlogs($record, $user);
            }

            return $record;
        }

        function updateFromAmember(Am_Record $record, User $user, $groups)
        {
            parent::updateFromAmember($record, $user, $groups);
            $this->updateMetaTags($record, $user);
            $record->updateQuick('display_name', $this->getPlugin()->getDisplayName($user));

            if ($this->getPlugin()->getConfig('simple_press'))
                $this->updateSimplePress($record);


            if ($this->getPlugin()->getConfig('buddy_press'))
            {
                $this->updateBuddyPressProfile($record);
                $this->updateBuddyPressGroups($record, $user);
            }

            if ($this->getPlugin()->getConfig('network'))
            {

                if ($this->getPlugin()->getConfig('network_create_blog'))
                    $this->updateNetworkBlogStatus($record, $user);

                if ($this->getPlugin()->getConfig('network_add_to_blogs'))
                    $this->networkAddToBlogs($record, $user);
            }
        }

        function getGroups(Am_Record $record)
        {
            $groups = $this->_plugin->getWP()->get_user_meta($record->pk(), $this->_plugin->getConfig('prefix') . "capabilities");
            if ($groups === false)
                return array();
            return array_keys($groups);
        }

        function setGroups(Am_Record $record, $groups)
        {
            $old_groups = $this->_plugin->getWP()->get_user_meta($record->pk(), $this->_plugin->getConfig('prefix') . "capabilities");
            $ret = array();
            foreach ($groups as $k)
            {
                if ($k)
                    $ret[$k] = 1;
            }
            $this->_plugin->getWP()->update_user_meta($record->pk(), $this->_plugin->getConfig('prefix') . "capabilities", $ret);
            $this->updateLevel($record, $this->getLevelFromCaps($record));

            if ($this->getPlugin()->getConfig('simple_press'))
                $this->updateSimplePressMemberships($record, $groups);

            return $ret;
        }

        function level_reduction($max, $item)
        {
            if (preg_match('/^level_(10|[0-9])$/i', $item, $matches))
            {
                $level = intval($matches[1]);
                return max($max, $level);
            }
            else
            {
                return $max;
            }
        }

        function getLevelFromCaps(Am_Record $record)
        {
            $roles = $this->_plugin->getWP()->get_roles();
            $allcaps = array();
            foreach ($this->getGroups($record) as $g)
            {
                $allcaps = array_merge($allcaps, $roles[$g]['capabilities']);
            }
            $level = array_reduce(array_keys($allcaps), array(&$this, 'level_reduction'), 0);
            return $level;
        }

        function createAmember(User $user, Am_Record $record)
        {
            parent::createAmember($user, $record);
            $user->name_f = $this->getPlugin()->getWP()->get_user_meta($record->pk(), 'first_name');
            $user->name_l = $this->getPlugin()->getWP()->get_user_meta($record->pk(), 'last_name');
        }

        function removeRecord(Am_Record $record)
        {
            parent::removeRecord($record);
            $this->_db->query('delete from ?_usermeta where user_id = ?', $record->pk());
            if ($this->getPlugin()->getConfig('simple_press'))
                $this->removeFromSimplePress($record);
        }

        function updateNetworkBlogStatus(Am_Record $record, User $user)
        {

            $blog_id = $this->getNetworkBlogId($record);
            if ($this->getPlugin()->haveNetworkBlogAccess($user))
            {
                if (!$blog_id)
                    $this->createNetworkBlog($record, $user);
                else
                {
                    // Blog exists already. Make sure it is not deleted. 
                    $this->_db->query('update ?_blogs set deleted = 0 where blog_id = ?', $blog_id);
                }
            }
            else
            {
                if ($blog_id)
                {
                    $this->_db->query('update ?_blogs set deleted = 1 where blog_id = ?', $blog_id);
                }
            }
        }

        function createNetworkBlog(Am_Record $record, User $user)
        {

            $current_site = get_current_site();
            $blog = array(
                'domain' => $user->login,
                'email' => $user->email,
                'title' => sprintf("%s %s's Blog", $user->name_f, $user->name_l)
            );

            $domain = strtolower($blog['domain']);

            // If not a subdomain install, make sure the domain isn't a reserved word
            if (!is_subdomain_install())
            {
                $subdirectory_reserved_names = array('page', 'comments', 'blog', 'files', 'feed');

                if (in_array($domain, $subdirectory_reserved_names))
                    throw new Am_Exception_InputError(
                        sprintf(
                            ___('The following words are reserved : <code>%s</code>'), implode('</code>, <code>', $subdirectory_reserved_names)
                        )
                    );
            }


            if (is_subdomain_install())
            {
                $newdomain = $domain . '.' . preg_replace('|^www\.|', '', $current_site->domain);
                $path = $current_site->path;
            }
            else
            {
                $newdomain = $current_site->domain;
                $path = $current_site->path . $domain . '/';
            }

            $user_id = $record->pk();

            $id = wpmu_create_blog($newdomain, $path, $blog['title'], $user_id, array('public' => 1), $current_site->id);
            if (!is_wp_error($id))
            {

                if (!is_super_admin($user_id) && !get_user_option('primary_blog', $user_id))
                    update_user_option($user_id, 'primary_blog', $id, true);

                wpmu_welcome_notification($id, $user_id, $password, $title, array('public' => 1));
            } else
            {
                throw new Am_Exception_InputError($id->get_error_message());
            }
        }

        function getNetworkBlogId(Am_Record $record)
        {
            return $this->getPlugin()->getWP()->get_user_meta($record->pk(), 'primary_blog');
        }

        function networkAddToBlogs(Am_Record $record, User $user)
        {
            $groups = $this->getPlugin()->calculateNetworkGroups($user);
            if (!$groups)
                return;
            foreach ($groups as $blog_id => $gr)
            {
                add_user_to_blog($blog_id, $record->pk(), current($gr));
            }
        }

    }

}
