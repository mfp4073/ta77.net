<?php

/** 
 * @package Am_View
 */

/**
 * Class represents templates - derived from Zend_View_Abstract 
 * @package Am_View
 */
class Am_View extends Zend_View_Abstract
{
    /** @var Am_Di */
    public $di; 
    protected static $_sprite_offsets = array (
        'icon' => array(
            'add' => 266,
            'affiliates-banners' => 532,
            'affiliates-commission' => 798,
            'affiliates-payout' => 1064,
            'affiliates' => 1330,
            'api' => 1596,
            'awaiting-me' => 1862,
            'awaiting' => 2128,
            'cart' => 2394,
            'ccrebills' => 2660,
            'closed' => 2926,
            'configuration' => 3192,
            'content-directory' => 3458,
            'content-emails' => 3724,
            'content-files' => 3990,
            'content-folders' => 4256,
            'content-integrations' => 4522,
            'content-links' => 4788,
            'content-newsletter' => 5054,
            'content-pages' => 5320,
            'content-video' => 5586,
            'content' => 5852,
            'copy' => 6118,
            'dashboard' => 6384,
            'date' => 6650,
            'delete' => 6916,
            'download' => 7182,
            'edit' => 7448,
            'export' => 7714,
            'help' => 7980,
            'helpdesk' => 8246,
            'key' => 8512,
            'login' => 8778,
            'magnify' => 9044,
            'merge' => 9310,
            'new' => 9576,
            'newsletter-subscribe-all' => 9842,
            'newsletters' => 10108,
            'oto' => 10374,
            'preview' => 10640,
            'products-categories' => 10906,
            'products-coupons' => 11172,
            'products-manage' => 11438,
            'products' => 11704,
            'report-bugs' => 11970,
            'reports-payments' => 12236,
            'reports-reports' => 12502,
            'reports' => 12768,
            'retry' => 13034,
            'revert' => 13300,
            'run-report' => 13566,
            'states' => 13832,
            'status_busy' => 14098,
            'user-locked' => 14364,
            'user-not-approved' => 14630,
            'users-browse' => 14896,
            'users-email' => 15162,
            'users-import' => 15428,
            'users-insert' => 15694,
            'users' => 15960,
            'utilites' => 16226,
            'view' => 16492,
        ),
        'flag' => array(
            'ad' => 26,
            'ae' => 52,
            'af' => 78,
            'ag' => 104,
            'ai' => 130,
            'al' => 156,
            'am' => 182,
            'an' => 208,
            'ao' => 234,
            'ar' => 260,
            'as' => 286,
            'at' => 312,
            'au' => 338,
            'aw' => 364,
            'ax' => 390,
            'az' => 416,
            'ba' => 442,
            'bb' => 468,
            'bd' => 494,
            'be' => 520,
            'bf' => 546,
            'bg' => 572,
            'bh' => 598,
            'bi' => 624,
            'bj' => 650,
            'bm' => 676,
            'bn' => 702,
            'bo' => 728,
            'br' => 754,
            'bs' => 780,
            'bt' => 806,
            'bv' => 832,
            'bw' => 858,
            'by' => 884,
            'bz' => 910,
            'ca' => 936,
            'catalonia' => 962,
            'cc' => 988,
            'cd' => 1014,
            'cf' => 1040,
            'cg' => 1066,
            'ch' => 1092,
            'ci' => 1118,
            'ck' => 1144,
            'cl' => 1170,
            'cm' => 1196,
            'cn' => 1222,
            'co' => 1248,
            'cr' => 1274,
            'cs' => 1300,
            'cu' => 1326,
            'cv' => 1352,
            'cx' => 1378,
            'cy' => 1404,
            'cz' => 1430,
            'de' => 1456,
            'dj' => 1482,
            'dk' => 1508,
            'dm' => 1534,
            'do' => 1560,
            'dz' => 1586,
            'ec' => 1612,
            'ee' => 1638,
            'eg' => 1664,
            'eh' => 1690,
            'en' => 1716,
            'england' => 1742,
            'er' => 1768,
            'es' => 1794,
            'et' => 1820,
            'europeanunion' => 1846,
            'fam' => 1872,
            'fi' => 1898,
            'fj' => 1924,
            'fk' => 1950,
            'fm' => 1976,
            'fo' => 2002,
            'fr' => 2028,
            'ga' => 2054,
            'gb' => 2080,
            'gd' => 2106,
            'ge' => 2132,
            'gf' => 2158,
            'gh' => 2184,
            'gi' => 2210,
            'gl' => 2236,
            'gm' => 2262,
            'gn' => 2288,
            'gp' => 2314,
            'gq' => 2340,
            'gr' => 2366,
            'gs' => 2392,
            'gt' => 2418,
            'gu' => 2444,
            'gw' => 2470,
            'gy' => 2496,
            'hk' => 2522,
            'hm' => 2548,
            'hn' => 2574,
            'hr' => 2600,
            'ht' => 2626,
            'hu' => 2652,
            'id' => 2678,
            'ie' => 2704,
            'il' => 2730,
            'in' => 2756,
            'io' => 2782,
            'iq' => 2808,
            'ir' => 2834,
            'is' => 2860,
            'it' => 2886,
            'jm' => 2912,
            'jo' => 2938,
            'jp' => 2964,
            'ke' => 2990,
            'kg' => 3016,
            'kh' => 3042,
            'ki' => 3068,
            'km' => 3094,
            'kn' => 3120,
            'kp' => 3146,
            'kr' => 3172,
            'kw' => 3198,
            'ky' => 3224,
            'kz' => 3250,
            'la' => 3276,
            'lb' => 3302,
            'lc' => 3328,
            'li' => 3354,
            'lk' => 3380,
            'lr' => 3406,
            'ls' => 3432,
            'lt' => 3458,
            'lu' => 3484,
            'lv' => 3510,
            'ly' => 3536,
            'ma' => 3562,
            'mc' => 3588,
            'md' => 3614,
            'me' => 3640,
            'mg' => 3666,
            'mh' => 3692,
            'mk' => 3718,
            'ml' => 3744,
            'mm' => 3770,
            'mn' => 3796,
            'mo' => 3822,
            'mp' => 3848,
            'mq' => 3874,
            'mr' => 3900,
            'ms' => 3926,
            'mt' => 3952,
            'mu' => 3978,
            'mv' => 4004,
            'mw' => 4030,
            'mx' => 4056,
            'my' => 4082,
            'mz' => 4108,
            'na' => 4134,
            'nc' => 4160,
            'ne' => 4186,
            'nf' => 4212,
            'ng' => 4238,
            'ni' => 4264,
            'nl' => 4290,
            'no' => 4316,
            'np' => 4342,
            'nr' => 4368,
            'nu' => 4394,
            'nz' => 4420,
            'om' => 4446,
            'pa' => 4472,
            'pe' => 4498,
            'pf' => 4524,
            'pg' => 4550,
            'ph' => 4576,
            'pk' => 4602,
            'pl' => 4628,
            'pm' => 4654,
            'pn' => 4680,
            'pr' => 4706,
            'ps' => 4732,
            'pt' => 4758,
            'pw' => 4784,
            'py' => 4810,
            'qa' => 4836,
            're' => 4862,
            'ro' => 4888,
            'rs' => 4914,
            'ru' => 4940,
            'rw' => 4966,
            'sa' => 4992,
            'sb' => 5018,
            'sc' => 5044,
            'scotland' => 5070,
            'sd' => 5096,
            'se' => 5122,
            'sg' => 5148,
            'sh' => 5174,
            'si' => 5200,
            'sj' => 5226,
            'sk' => 5252,
            'sl' => 5278,
            'sm' => 5304,
            'sn' => 5330,
            'so' => 5356,
            'sr' => 5382,
            'st' => 5408,
            'sv' => 5434,
            'sy' => 5460,
            'sz' => 5486,
            'tc' => 5512,
            'td' => 5538,
            'tf' => 5564,
            'tg' => 5590,
            'th' => 5616,
            'tj' => 5642,
            'tk' => 5668,
            'tl' => 5694,
            'tm' => 5720,
            'tn' => 5746,
            'to' => 5772,
            'tr' => 5798,
            'tt' => 5824,
            'tv' => 5850,
            'tw' => 5876,
            'tz' => 5902,
            'ua' => 5928,
            'ug' => 5954,
            'um' => 5980,
            'us' => 6006,
            'uy' => 6032,
            'uz' => 6058,
            'va' => 6084,
            'vc' => 6110,
            've' => 6136,
            'vg' => 6162,
            'vi' => 6188,
            'vn' => 6214,
            'vu' => 6240,
            'wales' => 6266,
            'wf' => 6292,
            'ws' => 6318,
            'ye' => 6344,
            'yt' => 6370,
            'za' => 6396,
            'zh' => 6422,
            'zm' => 6448,
            'zw' => 6474,
        ),
    );

    protected $layout = null;

    function __construct(Am_Di $di = null)
    {
        parent::__construct();
        if (null === $di)
            $this->di = Am_Di::getInstance();
        else
            $this->di = $di;
        if ($this->di->hasService('theme'))
            $this->theme = $this->di->theme;
        else
            $this->theme = new Am_Theme ($this->di, 'default', array());
        $this->setHelperPath('Am/View/Helper', 'Am_View_Helper_');
        $this->setEncoding('UTF-8');
        foreach ($this->di->viewPath as $dir)
            $this->addScriptPath($dir);
        if (!$this->getScriptPaths())
            $this->addScriptPath(dirname(__FILE__) . '/../../application/default/views');
        $this->headScript()->prependScript("window.rootUrl = " . Am_Controller::getJson(REL_ROOT_URL) . ";\n");
        $this->headScript()->prependScript("window.CKEDITOR_BASEPATH = " . Am_Controller::getJson(REL_ROOT_URL . '/application/default/views/public/js/ckeditor/') . ";\n");
        $this->headScript()->prependScript("window.amLangCount = " . Am_Controller::getJson(count(Am_Di::getInstance()->config->get('lang.enabled'))) . ";\n");
    }

    function display($name)
    {
        echo $this->render($name);
    }

    static public function getSpriteOffset($id, $source = 'icon')
    {
        return isset(self::$_sprite_offsets[$source ][$id]) ? self::$_sprite_offsets[$source][$id] : false;
    }

    protected function _run()
    {
        $arg = func_get_arg(0);
        
        Am_Di::getInstance()->hook->call(Am_Event::BEFORE_RENDER, 
            array('view' => $this, 'templateName' => $arg));

        extract($this->getVars());
        $savedLayout = $this->layout;
        ob_start();
        include func_get_arg(0);
        $content = ob_get_contents();
        ob_end_clean();
        if ($this->layout && $savedLayout != $this->layout) // was switched in template
        {
            while ($layout = array_shift($this->layout))
            {
                ob_start();
                include $this->_script($layout);
                $content = ob_get_contents();
                ob_end_clean();
            }
        }
        
        $event = Am_Di::getInstance()->hook->call(new Am_Event_AfterRender(null, 
            array(
                'view' => $this, 
                'templateName' => $arg,
                'output' => $content,
                )));
        echo $event->getOutput();
    }

    public function setLayout($layout)
    {
        $this->layout[] = $layout;
    }

    public function formOptions($options, $selected)
    {
        return Am_Controller::renderOptions($options, $selected);
    }

    public function formCheckboxes($name, $options, $selected)
    {
        $out = "";
        $name = Am_Controller::escape($name);
        foreach ($options as $k => $v)
        {
            $k = Am_Controller::escape($k);
            $sel = is_array($selected) ? in_array($k, $selected) : $k == $selected;
            $sel = $sel ? " checked='checked'" : "";
            $out .= "<input type='checkbox' name='{$name}[]' value='$k'$sel>\n$v\n<br />\n";
        }
        return $out;
    }

    public function formRadio($name, $options, $selected)
    {
        $out = "";
        $name = Am_Controller::escape($name);
        foreach ($options as $k => $v)
        {
            $k = Am_Controller::escape($k);
            $sel = $k == $selected;
            $sel = $sel ? " checked='checked'" : "";
            $out .= "<input type='radio' name='{$name}' value='$k'$sel>\n$v\n<br />\n";
        }
        return $out;
    }


    /**
     * Output all code necessary for aMember, this must be included before
     * closing </head> into layout.phtml
     * @param $safe_jquery_load  - Load jQuery only if it was not leaded before(true|false). Default is false. 
     * 
     */
    function printLayoutHead($need_reset=true, $safe_jquery_load = false)
    {
        if($need_reset)
            $this->headLink()
                 ->appendStylesheet($this->_scriptCss('reset.css'));
        $this->headLink()
            ->appendStylesheet($this->_scriptCss('amember.css'));
        $this->headLink()
            ->appendStylesheet($this->_scriptCss('ie-7.css'), 'screen', 'IE 7');

        $this->theme->printLayoutHead($this);
        
        if ($siteCss = $this->_scriptCss('site.css'))
            $this->headLink()->appendStylesheet($siteCss);
        
        $this->headLink()->appendStylesheet($this->_scriptJs('jquery/jquery.ui.css'));
        
        $hs = $this->headScript();
        try {
            $hs->prependScript(
                "window.uiDateFormat = " . Am_Controller::getJson($this->convertDateFormat(Zend_Registry::get('Am_Locale')->getDateFormat())) . ";\n")
                    ->prependScript(sprintf("window.uiDefaultDate = new Date(%d,%d,%d);\n", date('Y'), date('n')-1, date('d')));
        } catch (Exception $e) {
            // we can live without it if Am_Locale is not yet registered, we will just skip this line
        }
        
        if($safe_jquery_load){
            $hs->prependScript('if (typeof jQuery == \'undefined\') {document.write(\'<script type="text/javascript" src="'.$this->_scriptJs('jquery/jquery.js').'"></script>\');} else {$=jQuery;}');
        }else{
            $hs->prependFile($this->_scriptJs('jquery/jquery.js'));
        }
        $hs->appendFile($this->_scriptJs('jquery/jquery.ui.js'));
        $hs->appendFile($this->_scriptJs('user.js'));
        $hs->appendFile($this->_scriptJs('upload.js'));

        echo "<!-- userLayoutHead() start -->\n";
        echo $this->placeholder("head-start") . "\n";
        echo $this->headMeta() . "\n";
        echo $this->headLink() . "\n";
        echo $this->headStyle() . "\n";
        echo $this->headScript() . "\n";
        echo $this->placeholder('head-finish') . "\n";
        echo "<!-- userLayoutHead() finish -->\n";
    }

    function adminHeadInit()
    {
        $this->headLink()->appendStylesheet($this->_scriptCss('reset.css'));
        $this->headLink()->appendStylesheet(REL_ROOT_URL . "/application/default/views/public/js/jquery/jquery.ui.css");
        $this->headLink()->appendStylesheet($this->_scriptCss('admin.css'));
        if ($theme = $this->_scriptCss('admin-theme.css'))
            $this->headLink()->appendStylesheet($theme);
        $this->headScript()
            ->prependScript(
                "window.uiDateFormat = " . Am_Controller::getJson($this->convertDateFormat(Zend_Registry::get('Am_Locale')->getDateFormat())) . ";\n")
            ->prependScript(sprintf("window.uiDefaultDate = new Date(%d,%d,%d);\n", date('Y'), date('n')-1, date('d')))
            ->prependFile(REL_ROOT_URL . "/js.php?js=admin");
        $this->placeholder('body-start')->append(
            '<div id="flash-message"></div>'
        );
    }

    /**
     * Convert date format from PHP date() to Jquery UI
     * @param string $dateFormat
     * @return string
     */
    public function convertDateFormat($dateFormat)
    {
        $convertionMap = array(
            'j' => 'd',  //day of month (no leading zero)
            'd' => 'dd', //day of month (two digit)
            'z' => 'oo', //day of the year (three digit)
            'D' => 'D',  //day name short
            'l' => 'DD', //day name long
            'm' => 'mm', //month of year (two digit)
            'M' => 'M',  //month name short
            'F' => 'MM', //month name long
            'y' => 'y',  //year (two digit)
            'Y' => 'yy'  //year (four digit)
        );
        return strtr($dateFormat, $convertionMap);
    }

    static function getThemes($themeType = 'user')
    {
        $entries = scandir($td = APPLICATION_PATH . ($themeType == 'user' ? '/default/themes' : '/default/themes-admin/'));
        $ret = array('default' => "Default Theme");
        foreach ($entries as $d)
        {
            if ($d[0] == '.')
                continue;
            $p = "$td/$d";
            if (is_dir($p) && is_readable($p))
                $ret[$d] = ucfirst($d);
        }
        return $ret;
    }
    
    /** Find location of the CSS (respecting the current theme)
     * @return string|null path including REL_ROOT_URL, or null
     */
    function _scriptCss($name, $escape = true)
    {
        try
        {
            $ret = $this->di->app->pathToUrl($this->_script('public/css/' . $name));
        } catch (Zend_View_Exception $e)
        {
            return;
        }
        return $escape ? $this->escape($ret) : $ret;
    }

    /** Find location of the CSS (respecting the current theme)
     * @return string|null path including REL_ROOT_URL, or null
     */
    function _scriptJs($name, $escape = true)
    {
        try
        {
            $ret = $this->di->app->pathToUrl($this->_script('public/js/' . $name));
        } catch (Zend_View_Exception $e)
        {
            return;
        }
        return $escape ? $this->escape($ret) : $ret;
    }

    /** Find location of the Image (respecting the current theme)
     * @return string|null path including REL_ROOT_URL, or null
     */
    function _scriptImg($name, $escape = true)
    {
        try
        {
            $ret = $this->di->app->pathToUrl($this->_script('public/img/' . $name));
        } catch (Zend_View_Exception $e)
        {
            return;
        }
        return $escape ? $this->escape($ret) : $ret;
    }

    /**
     * Returns url of current page with given _REQUEST parameters overriden
     * @param array $parametersOverride
     */
    function url(array $parametersOverride = array(), $skipRequestParams = false)
    {
        $vars = $skipRequestParams ? $parametersOverride : array_merge($_REQUEST, $parametersOverride);
        return Am_Controller::makeUrl() . '?' . http_build_query($vars);
    }

    /**
     * print escaped current url without parameters
     */
    function pUrl($controller = null, $action = null, $module = null, $params = null)
    {
        $args = func_get_args();
        echo call_user_func_array(array('Am_Controller', 'makeUrl'), $args);
    }

    /**
     * Add necessary html code to page to enable graphical reports
     */
    function enableReports()
    {
        static $reportsEnabled = false;
        if ($reportsEnabled)
            return;
        $root = REL_ROOT_URL;
        $this->placeholder('head-finish')->append(<<<CUT
<script language="javascript" type="text/javascript" src="$root/application/default/views/public/js/raphael.js"></script>
<script language="javascript" type="text/javascript" src="$root/application/default/views/public/js/morris.js"></script>
CUT
        );
        $reportsEnabled = true;
    }
}

/**
 * @package Am_View
 * helper to display theme variables in human-readable format 
 */
class Am_View_Helper_ThemeVar 
{
    function themeVar($k, $default = null)
    {
        $k = sprintf('themes.%s.%s', Am_Di::getInstance()->config->get('theme', 'default'), $k);
        return Am_Di::getInstance()->config->get($k, $default);
    }
}

/**
 * @package Am_View
 * helpder to display time interval in human readable format 
 */
class Am_View_Helper_GetElapsedTime
{
    public $view = null;
    
    function setView(Zend_View_Interface $view) {
        $this->view = $view;
    }
    
    function getElapsedTime($date) {
        $sdate = strtotime($date);
        $edate = $this->view->di->time;

        $time = $edate - $sdate;
        if ($time>=0 && $time<=59) {
            // Seconds
            return ___('just now');
            $timeshift = $time.' ' . ___('seconds');

        } elseif($time>=60 && $time<=3599) {
            // Minutes
            $pmin = ($edate - $sdate) / 60;
            $premin = explode('.', $pmin);

            $timeshift = $premin[0].' ' . ___('min');

        } elseif($time>=3600 && $time<=86399) {
            // Hours
            $phour = ($edate - $sdate) / 3600;
            $prehour = explode('.',$phour);

            $timeshift = $prehour[0].' ' . ___('hrs');

        } elseif($time>=86400 && $time<86400*30) {
            // Days
            $pday = ($edate - $sdate) / 86400;
            $preday = explode('.',$pday);

            $timeshift = $preday[0].' ' . ___('days');

        } else {
            // Month
            $pmonth = ($edate - $sdate) / (86400 * 30);
            $premonth = explode('.',$pmonth);

            $timeshift = ___('more than') . ' ' . $premonth[0].' ' . ___('month');
        }
        return $timeshift . ' ' . ___('ago');
    }
}

/**
 * helper to display blocks 
 * @package Am_View
 * @link Am_Blocks
 * @link Am_Block
 */
class Am_View_Helper_Blocks extends Zend_View_Helper_Abstract
{

    /** @var Am_Blocks */
    protected $blocks;

    /** @return Am_Blocks */
    function getContainer()
    {
        if (!$this->blocks)
            $this->blocks = $this->view->di->blocks;
        return $this->blocks;
    }

    function setContainer(Am_Blocks $blocks)
    {
        $this->blocks = $blocks;
    }

    function render($path, $envelope = "%s")
    {
        $out = "";
        foreach ($this->getContainer()->get($this->view, $path) as $block)
            $out .= sprintf($envelope, $block['content'], $block['title'], $block['id']);
        return $out;
    }

    /** if called as blocks() returns itself, if called as block('path') calls render('path') */
    function blocks($path = null, $envelope = "%s")
    {
        return $path === null ? $this : $this->render($path, $envelope);
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->getContainer(), $name), $arguments);
    }
    
}

/**
 * View helper to return translagted text (between start() and stop() calls)
 * @package Am_View
 * @deprecated
 */
class Am_View_Helper_Tr extends Zend_View_Helper_Abstract
{

    protected $text;
    protected $args;

    /**
     * Return translated text if argument found, or itself for usage of start/stop
     * @param string|null $text
     * @return Am_View_Helper_Tr|string
     */
    function tr($text = null)
    {
        if ($text === null)
            return $this;
        $this->args = func_get_args();
        $this->text = array_shift($this->args);
    }

    function start($arg1=null, $arg2=null)
    {
        $this->args = func_get_args();
        ob_start();
    }

    function stop()
    {
        $this->text = ob_get_clean();
        $this->doPrint();
    }

    protected function doPrint()
    {
        $tr = Zend_Registry::get('Zend_Translate');
        if (!$tr)
        {
            trigger_error("No Zend_Translate instance found", E_USER_WARNING);
            echo $this->text;
        }
        /* @var $tr Zend_Translate_Adapter */
        $this->text = $tr->_(trim($this->text));
        vprintf($this->text, $this->args);
    }

}

/**
 * For usage in templates
 * echo escaped variable
 */
function p($var)
{
    echo htmlentities($var, ENT_QUOTES, 'UTF-8', false);
}

/** echo variable escaped for javascript 
 */
function j($var)
{
    echo strtr($var, array("'" => "\\'", '\\' => '\\\\', '"' => '\\"', "\r" => '\\r', '</' => '<\/', "\n" => '\\n'));
}

