<?php
class am4Widgets extends am4Plugin{
    function action_WidgetsInit(){
        foreach(get_declared_classes() as $c){
            if(preg_match("/am4Widget_\S+/", $c)){
                register_widget($c);
            }
        }
    }
    
    function showWidgetProtectionBlock($widget, $instance){
        $view = new am4View("widget_protection");
        $view->assign("instance", $instance);
        $view->assign("widget", $widget);
        $view->render();
    }
    
    function isWidgetAvailable($widget, $instance){
        $api = am4PluginsManager::getAPI();
        
        // Allow to see all widgets for admin users; 
        if(am4PluginsManager::skipProtection())
            return true;
        
        if(in_array('guest', (array)$instance['access'])){
                return $api->isLoggedIn() ? false : true; 
        }

            
        
        // Check access options; 
        $access = new am4UserAccess();
        $not_have = true;
        if(in_array('not_have', (array)$instance['access']))
        {
            if($access->allFalse(am4AccessRequirement::createRequirements($instance['not_have']))){
                $not_have = true;
            }else{
                $not_have = false;
            }
        } 
        $have = true;
        if(in_array('have', (array)$instance['access']))
        {
            if($access->anyTrue(am4AccessRequirement::createRequirements($instance['have']))){
                $have = true;
            }else{
                $have = false;
            }
        }
        
        
        return $have && $not_have;
    }
}


class am4Widget_login extends WP_Widget{
   
    protected $elements;

    function __construct(){
        parent::__construct(false, __("aMember Widget", 'am4-plugin'),array('classname' => 'widget_text', 'description' => __('Login form and aMember subscriptions links', 'am4-plugin')));    
        $this->addFormElement("text", 'before_login_title',         __('Before Login Title:', 'am4-plugin'),      __('Login', 'am4-plugin'));
        $this->addFormElement("text", 'after_login_title',          __('After Login Title:', 'am4-plugin'),       __('Your Subscriptions', 'am4-plugin'));
        $this->addFormElement("text", 'password_title',             __('Password Title', 'am4-plugin'),           __('Password', 'am4-plugin'));
        $this->addFormElement("text", 'username_title',             __('Username Title:', 'am4-plugin'),          __('Login', 'am4-plugin'));
        $this->addFormElement("text", 'login_button_title',         __('Login Button  Title:', 'am4-plugin'),     __('Login', 'am4-plugin'));
        $this->addFormElement("text", 'register_link_title',        __('Register Link Title:', 'am4-plugin') ,    __('Signup Here', 'am4-plugin'));
        $this->addFormElement("text", 'lost_password_title',        __('Lost Password Title:', 'am4-plugin'),     __('Lost Password', 'am4-plugin'));
        $this->addFormElement("text", 'renew_subscription_title',   __('Renew Subscription Title:', 'am4-plugin'),__('Renew Subscription', 'am4-plugin'));
        $this->addFormElement("text", 'payment_history_title',      __('Payment History Title:', 'am4-plugin'),   __('Payment History', 'am4-plugin'));
        $this->addFormElement("text", 'logout_title',               __('Logout Title:', 'am4-plugin'),            __('Logout', 'am4-plugin'));
        $this->addFormElement("text", 'change_profile_title',       __('Change Profile Title:', 'am4-plugin'),    __('Edit Profile', 'am4-plugin'));
        $this->addFormElement("text", 'signup_page_url',            __('Signup Page URL', 'am4-plugin') ,         am4PluginsManager::getAPI()->getSignupURL());
        $this->addFormElement("text", 'renewal_page_url',           __('Renewal  Page URL', 'am4-plugin') ,       am4PluginsManager::getAPI()->getSignupURL());
        $this->addFormElement("text", 'lost_password_page_url',     __('Lost Password Page URL', 'am4-plugin'),   am4PluginsManager::getAPI()->getLoginURL());
        $this->addFormElement("text", 'profile_page_url',           __('Profile Page URL', 'am4-plugin') ,        am4PluginsManager::getAPI()->getProfileURL());
        $this->addFormElement("text", 'history_page_url',           __('Payment History Page URL', 'am4-plugin'), am4PluginsManager::getAmemberURL()."/member/payment-history");
        $this->addFormElement("text", 'logout_page_url',            __('Logout page URL', 'am4-plugin') ,         am4PluginsManager::getAPI()->getLogoutURL());
        $this->addFormElement("checkbox",'amember_links',           __('Active Subscriptions Links', 'am4-plugin'),   1);
        $this->addFormElement("checkbox",'renew_subscription_link', __('Renew subscription Link', 'am4-plugin'),      1);
        $this->addFormElement("checkbox",'payment_history_link',    __('Payment History Link', 'am4-plugin'),         1);
        $this->addFormElement("checkbox",'logout_link',             __('Logout Link', 'am4-plugin'),                  1);
        $this->addFormElement("checkbox",'register_link',           __('Register Link', 'am4-plugin'),                1);
        $this->addFormElement("checkbox",'forgot_password_link',    __('Forgot Password Link', 'am4-plugin'),         1);
        $this->addFormElement("checkbox",'change_profile_link',     __('Change Profile Link', 'am4-plugin'),          1);
        $this->addFormElement("checkbox",'follow_amember_redirect_rules',     __('Follow aMember redirect Rules', 'am4-plugin'),0);
        
    }
    function addFormElement($type, $name, $title, $default){
        $this->elements[$type][$name] = array('type'=>$type, 'name'=>$name,'title'=>$title, 'default'=>$default);
    }
    function showElements($instance, $type){
        foreach($this->elements[$type] as $k=>$v){
            switch($type){
                case 'text'     :   $this->form_text_element($instance, $v['name'], $v['title']); break;
                case 'checkbox' :   $this->form_checkbox_element($instance, $v['name'], $v['title']); break;
                default :   throw new Exception('Unknown element type: '.$type);
            }
        }
    }
    // Get string if defained;
    function load_defaults($instance){
        $_d = array();
        foreach($this->elements as $v){
            foreach($v as $elem){
                $_d[$elem['name']] = $elem['default'];
            }
        }
        $instance = array_merge($_d, $instance);
        foreach($instance as $k=>$v){
            $instance[$k] = esc_attr($v);
        }
        return $instance;
    }
    function form_text_element($instance, $name, $text){
        ?>
        <div><label for="<?php echo $this->get_field_id($name); ?>"><?php print $text; ?> 
                <input class="widefat" id="<?php echo $this->get_field_id($name); ?>" name="<?php echo $this->get_field_name($name); ?>" type="text" value="<?php print $instance[$name]; ?>" />
            </label></div><br/>
        <?php
    }


    function form_checkbox_element($instance, $name, $text){
        ?>
        <label for="<?php echo $this->get_field_id($name); ?>">
            <input name="<?php echo $this->get_field_name($name); ?>" type="hidden" value="0"/>
            <input  id="<?php echo $this->get_field_id($name); ?>" name="<?php echo $this->get_field_name($name); ?>" type="checkbox" value="1" <?php checked(1,$instance[$name]); ?>/><?php print $text; ?></label><br/>
        <?php
    }
    function form($instance){
        $instance = $this->load_defaults($instance);
        $this->showElements($instance, "text");
        ?>
        <div>
            <b><?php _e('Show Links', 'am4-plugin');?></b><br/><br/>
        <?php
        // Form checkbox elements
        $this->showElements($instance, "checkbox");
        ?>
        </div>
        <?php
    }
    function update($new, $old){
        return $new;
    }
    function widget($args, $instance){
        global $current_user;
        $instance = $this->load_defaults($instance);
        $api = am4PluginsManager::getAPI();
               
        extract($args);
        $before_login_title = apply_filters('widget_title', $instance['before_login_title']);
        $after_login_title = apply_filters('widget_title', $instance['after_login_title']);
        if($api->isLoggedIn()){
            $title = $after_login_title;
        }else{
            $title = $before_login_title;
        }



        print $before_widget;
        print $before_title.$title.$after_title;
        // usefull links at the bottom;
            if($api->isLoggedIn()){
                $this->after_login_widget($instance);
            }else{
                $this->before_login_widget($instance);
            }

        print $after_widget;
    }

    function before_login_widget($instance){
    // Login form here
        $view = new am4View("widget_login_form");
        $view->assign("instance",$instance);
        $view->render();
    }
    
    function after_login_widget($instance){
        global $current_user;
// Get current user's subscription and show usefull links;
        $amember_api = am4PluginsManager::getAPI();
        $view = new am4View("widget_after_login");
        $view->assign("instance",$instance);
        if($amember_api->isLoggedIn()){
            $view->assign("user", $amember_api->getUser());
            $view->assign("links", array_filter((array)$amember_api->getUserLinks()));
        }
        $view->assign('isLoggedIn', $amember_api->isLoggedIn());
        $view->render();
    }

}

include_once(ABSPATH . WPINC . "/default-widgets.php");
class am4Widget_text extends WP_Widget_Text {
    function am4Widget_text(){
		$widget_ops = array('classname' => 'widget_text', 'description' => __('Arbitrary text or HTML', 'am4-plugin'));
		$control_ops = array('width' => 400, 'height' => 350);
                $this->WP_Widget('amember_text',  __('aMember Text Widget', 'am4-plugin'),$widget_ops, $control_ops);
    }
    
    function form($instance){
        parent::form($instance);
        am4PluginsManager::get('widgets')->showWidgetProtectionBlock($this, $instance);
    }

    function update($new_instance, $old_instance) {
        $instance = parent::update($new_instance, $old_instance);
        $instance['access'] = $new_instance['access'];
        $instance['have'] = $new_instance['have'];
        $instance['not_have'] = $new_instance['not_have'];
        return $instance;
    }
    function get_field_name($field_name,$is_array=false) {
	if($is_array){
            return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . '][]';
        }else{
            return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . ']';
        }
    }
    function widget($args, $instance){
        global $current_user;
        
        if(am4PluginsManager::get("widgets")->isWidgetAvailable($this, $instance)){
            $instance['text'] = do_shortcode($instance['text']);
            return parent::widget($args, $instance);
        }

    }

}

class am4Widget_menu extends WP_Nav_Menu_Widget{
    function am4Widget_menu(){
		$widget_ops = array('description' => __('Custom Menu with aMember protection settings', 'am4-plugin'));
		$this->WP_Widget('amember_menu', __('aMember menu Widget', 'am4-plugin'), $widget_ops, @$control_ops);

    }
    function form($instance){
        parent::form($instance);
        am4PluginsManager::get('widgets')->showWidgetProtectionBlock($this, $instance);

    }

    function update($new_instance, $old_instance) {
        $instance = parent::update($new_instance, $old_instance);
        $instance['access'] = $new_instance['access'];
        $instance['have'] = $new_instance['have'];
        $instance['not_have'] = $new_instance['not_have'];
        return $instance;
    }
    
    function get_field_name($field_name,$is_array=false) {
	if($is_array){
            return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . '][]';
        }else{
            return 'widget-' . $this->id_base . '[' . $this->number . '][' . $field_name . ']';
        }
    }
    
    function widget($args, $instance){
        global $current_user;
        
        if(am4PluginsManager::get("widgets")->isWidgetAvailable($this, $instance)){
            return parent::widget($args, $instance);
        }

    }


}
