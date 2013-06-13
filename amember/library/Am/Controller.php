<?php

/**
 * Page controller implements mvC logic
 * @package Am_Controller
 */
class Am_Controller extends Zend_Controller_Action {
    const ACTION_KEY = 'action';

    protected $processed = false;
    /**
     * Is this an Ajax request or not (to override auto-detection)
     * @var bool
     */
    protected $isAjax = null;

    /** @var bool ignore @see runPage calls */
    static protected $_unitTestEnabled = false;
    /** @var array for testing only
     * @internal 
     */
    static private $_cookies = array();

    /** @var Am_Request */
    protected $_request;
    
    /** @var Am_View */
    public $view;

    public function __construct(Zend_Controller_Request_Abstract $request,
                                Zend_Controller_Response_Abstract $response,
                                array $invokeArgs = array())
    {
        if ($request === null)
            throw new Am_Exception_InternalError("Class ".get_class($this)." constructed without \$request and \$response");
        $invokeArgs['noViewRenderer'] = true;
        $this->view = $invokeArgs['di']->view;
        parent::__construct($request, $response, $invokeArgs);
    }
    
    /** @return Am_Di */
    function getDi()
    {
        return $this->_invokeArgs['di'];
    }
    /**
     * Return variable from aMember config
     * @param string $key
     * @return mixed
     */
    function getConfig($key, $default = null){
        return $this->_invokeArgs['di']->config->get($key, $default);
    }
    /** @return Am_View */
    function getView()
    {
        return $this->view;
    }
    
    public function _checkPermissions()
    {
        if (stripos($this->_request->getControllerName(), 'admin')===0)
        {
            if ($this instanceof AdminAuthController) return; 
            $admin = $this->getDi()->authAdmin->getUser();
            if (!$admin)
                throw new Am_Exception_InternalError("Visitor has got access to admin controller without admin authentication!");
            if (!$this->checkAdminPermissions($admin))
                throw new Am_Exception_AccessDenied("Admin [{$admin->login}] has no permissions to do selected operation in " . get_class($this));
        }
    }

    public function setActiveMenu($id) {
        $this->getView()->headScript()->appendScript('window.amActiveMenuID = "' . $id . '";');
    }

    /**
     *
     * @param Admin $admin 
     */
    public function checkAdminPermissions(Admin $admin)
    {
        throw new Am_Exception_NotImplemented(__FUNCTION__ . " must be implemented in " . get_class($this));
    }
    
    /**
     * Call required action
     * @param $actionName
     */
    public function dispatch($action)
    {
        // Notify helpers of action preDispatch state
        $this->_helper->notifyPreDispatch();
        
        $this->_checkPermissions();

        try {
            $this->preDispatch();
        } catch (Am_Exception_Redirect $e) {
            $this->postDispatch();
            $this->_helper->notifyPostDispatch();
            return;
        }
        if (! $this->isProcessed()) {
            if ($this->getRequest()->isDispatched()) {
                if (null === $this->_classMethods) {
                    $this->_classMethods = get_class_methods($this);
                }

                // preDispatch() didn't change the action, so we can continue
                try {
                    if ($this->getInvokeArg('useCaseSensitiveActions') || in_array($action, $this->_classMethods)) {
                        if ($this->getInvokeArg('useCaseSensitiveActions')) {
                            trigger_error('Using case sensitive actions without word separators is deprecated; please do not rely on this "feature"');
                        }
                        $this->_runAction($action);
                    } else {
                        $this->__call($action, array());
                    }
                } catch (Am_Exception_Redirect $e) {
                // all ok, we just called it for GOTO
                }
                $this->postDispatch();
            }
        }
        // whats actually important here is that this action controller is
        // shutting down, regardless of dispatching; notify the helpers of this
        // state
        $this->_helper->notifyPostDispatch();
    }

    /**
     * After running this function $this->_response must be filled-in
     * @param string $action
     */
    public function _runAction($action)
    {
        ob_start();
        $this->$action();
        $this->getResponse()->appendBody(ob_get_clean());
    }

    public function  _setInvokeArgs(array $args = array()) {
        return parent::_setInvokeArgs($args);
    }

    public function __call($methodName, $args) {
        require_once 'Zend/Controller/Action/Exception.php';
        if ('Action' == substr($methodName, -6)) {
            $action = substr($methodName, 0, strlen($methodName) - 6);
            throw new Zend_Controller_Action_Exception(sprintf('Action "%s" does not exist in %s and was not trapped in __call()', $action, get_class($this)), 404);
        }
        throw new Zend_Controller_Action_Exception(sprintf('Method "%s" does not exist and was not trapped in __call()', $methodName), 500);
    }
    /**
     * Run htmlentities() for the string
     * @param string string to escape
     * @return string escaped string
     */
    static function escape($string)
    {
        return htmlentities($string, ENT_QUOTES, 'UTF-8', false);
    }
    public function isAjax()
    {
        if ($this->isAjax === null)
            $this->isAjax = $this->_request->isXmlHttpRequest();
        return $this->isAjax;
    }
    static public function static_isAjax()
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    public function setAjax($value)
    {
        $this->isAjax = (bool)$value;
    }
    static function getJson($vars)
    {
        return json_encode($vars);//,JSON_FORCE_OBJECT);
    }
    static function ajaxResponse($vars)
    {
        header("Content-type: application/json; charset=UTF-8");
        if (!empty($_GET['callback']))
        {
            if (preg_match('/\W/', $_GET['callback'])) {
                // if $_GET['callback'] contains a non-word character,
                // this could be an XSS attack.
                header('HTTP/1.1 400 Bad Request');
                exit();
            }
            printf('%s(%s)', $_GET['callback'], self::getJson($vars));
        } else
            echo self::getJson($vars);
    }
    static function decodeJson($str)
    {
        return json_decode($str, true);
    }
    static function noCache()
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
    }
    static function isSsl()
    {
        return ((@$_SERVER['HTTPS']==1) || (@$_SERVER['HTTPS']=='on') || $_SERVER['SERVER_PORT'] == 443);
    }
    public function isProcessed()
    {
        return $this->processed;
    }
    /** call this to stop request processing */
    public function setProcessed($flag = true)
    {
        $this->processed = (bool)$flag;
    }
    public function isPost()
    {
        return $this->_request->isPost();
    }
    public function isGet()
    {
        return $this->_request->isGet();
    }
    /** @return mixed request parameter of if not exists in request, then $default value */
    function getParam($key, $default=null)
    {
        return $this->_request->getParam($key, $default);
    }
    /** @return int the same as get param but with intval(...) applied */
    function getInt($key, $default=0)
    {
        return $this->_request->getInt($key, $default);
    }
    /** @return string request parameter with removed chars except the a-zA-Z0-9-_ */
    function getFiltered($key, $default=null){
        return $this->_request->getFiltered($key, $default);
    }
    /** @return string request parameter with htmlentities(..) applied */
    function getEscaped($key, $default=null){
        return $this->_request->getEscaped($key, $default);
    }

    function redirectAjax($url, $text)
    {
        return printf(
            "<div class='center' style='margin-top: 4em; margin-bottom: 4em;'>".
            "<b>%s</b>".
            "<div class='small'><a href='%s' class='redirect'>%s</a></div>" .
            "</div>"
            ,$this->escape($text)
            ,$this->escape($url), $this->escape(___('Click here if you do not want to wait any longer (or if your browser does not automatically forward you).'))
            );
    }
    /**
     * Redirect customer to new url
     * @param $targetTop useful when doing a redirect in AJAX generated html
     */
    function redirectHtml($url, $text='', $title='Redirecting...', $targetTop=false, $proccessed = null, $total = null)
    {
        $this->view->assign('title', $title);
        $this->view->assign('text', $text);
        $this->view->assign('url', $url);
        if (!is_null($total)) {
            $width = (100 * $proccessed) / $total;
            $this->view->width = min(100, round($width));
            $this->view->showProgressBar = true;
            $this->view->total = $total;
            $this->view->proccessed = $proccessed;
        }
        if ($targetTop)
            $this->view->assign('target', '_top');
        if (ob_get_level())
            ob_end_clean();
        $this->getResponse()->setBody($this->view->render(defined('AM_ADMIN') ? 'admin/redirect.phtml' : 'redirect.phtml'));
        throw new Am_Exception_Redirect($url); // exit gracefully
    }
    
    static function redirectLocation($url)
    {
        // some hosts disallow redirect without full URL
        if (!preg_match('#^http#',$url))
        {
            $u = parse_url(ROOT_URL);
            $url = $u['scheme'] . '://' . $u['host'] . $url;
        }
        if (APPLICATION_ENV != 'testing') 
        {
            header("Location: " . preg_replace('/[\r\n]+/', '', $url));
        }
        throw new Am_Exception_Redirect($url);
    }
    /**
     * Render html for <option>..</option> tags of <select>
     * @param array of options key => value
     * @param mixed selected option key
     */
    static function renderOptions(array $options, $selected = '')
    {
        $out = "";
        foreach ($options as $k => $v) {
            if (is_array($v)) 
            { 
                //// render optgroup instead
                $out .=
                     "<optgroup label='" . self::escape($k) . "'>"
                    . self::renderOptions($v, $selected)
                    ."</optgroup>\n";
                continue;
            }
            if (is_array($selected))
                $sel = in_array($k, $selected) ? ' selected="selected"' : '';
            else
                $sel = (string)$k == (string)$selected ? ' selected="selected"' : null;
            $out .= sprintf('<option value="%s"%s>%s</option>'."\n",
                    self::escape($k),
                    $sel,
                    self::escape($v));
        }
        return $out;
    }
    /**
     * Convert array of variables to string of input:hidden values
     * @param array variables
     * @return string <input type="hidden" name=".." value="..."/><input .....
     */
    static function renderArrayAsInputHiddens($vars, $parentK=null)
    {
        $ret = "";
        foreach ($vars as $k=>$v)
            if (is_array($v))
                $ret .= self::renderArrayAsInputHiddens($v, $parentK ? $parentK.'['.$k.']': $k);
            else
                $ret .= sprintf('<input type="hidden" name="%s" value="%s" />'."\n",
                    self::escape($parentK ? ($parentK."[".$k."]") : $k), self::escape($v));
        return $ret;
    }
    /**
     * Convert array of variables to array of input:hidden values
     * @param array variables
     * @return array key => value for including into form
     */
    static function getArrayOfInputHiddens($vars, $parentK=null)
    {
        $ret = array();
        foreach ($vars as $k=>$v)
            if (is_array($v))
                $ret = array_merge(
                        $ret,
                        self::getArrayOfInputHiddens(
                                $v,
                                $parentK ? $parentK . '[' . self::escape($k) . ']' : self::escape($k)
                        )
                );
            else
                $ret[$parentK ? ($parentK."[".self::escape($k)."]") : self::escape($k)] = self::escape($v);
        return $ret;
    }

    static function getFullUrl(){
        $url  = self::isSsl() ? 'https://' : 'http://';
        $url .= $_SERVER['HTTP_HOST'] ;
        $url .= $_SERVER['REQUEST_URI'];
        return $url;
    }

    /** @internal */
    static function _setUnitTestEnabled($flag=true){
        self::$_unitTestEnabled = (bool)$flag;
    }
    static private function _getCookieDomain($d)
    {
        if ($d === null || $d == 'localhost') return null;
        if (preg_match('/([^\.]+)\.(int|org|com|net|biz|info|ru|co.uk|co.za|eu)$/', $d, $regs))
            return ".{$regs[1]}.{$regs[2]}";
        else
            return $d;
    }
    function delCookie($name)
    {
        $this->setCookie($name, $value, time()-24*3600);
    }
    /**
     * @todo check domain parsing and make delCookie global
     */
    static function setCookie($name, $value, $expires=0, $path = '/', $domain=null, $secure=false, $strictDomainName=false, $httponly=false){
        if (self::$_unitTestEnabled)
            self::$_cookies[$name] = $value;
        else
            setcookie($name, $value, $expires, $path, ($strictDomainName ? $domain : self::_getCookieDomain($domain)), $secure, $httponly);
    }
    static function _getCookie($name){
        return @self::$_cookies[$name];
    }
    static function _clearCookie()
    {
        self::$_cookies = array();
    }
    /**
     * Construct an application URL
     * @param <type> $controller
     * @param <type> $action
     * @param <type> $module
     * @return string
     */
    function getUrl($controller = null, $action = null, $module = null, $params = null)
    {
        $args = func_get_args();
        return self::_makeUrl($this->_request, $args);
    }

    /**
     * Construct an application URL
     * @param <type> $controller
     * @param <type> $action
     * @param <type> $module
     * @return string
     */
    static function makeUrl($controller=null, $action=null, $module=null, $params = null)
    {
        $args = func_get_args();
        return self::_makeUrl(Zend_Controller_Front::getInstance()->getRequest(), $args);
    }
    static protected function _makeUrl(Zend_Controller_Request_Abstract $request, $args)
    {
        for ($i=0;$i<=2;$i++) if (!isset($args[$i])) $args[$i] = null;
        if ($args[0] === null) $args[0] = $request->getControllerName();
        if ($args[1] === null) $args[1] = $request->getActionName();
        if ($args[2] === null && $request->getModuleName() != 'default')
            $args[2] = $request->getModuleName();
        $res = $request->getBaseUrl()
                . ($args[2] ? '/'.$args[2] : "")
                . '/' . self::escape($args[0])
                . '/' . self::escape($args[1]);
        if (count($args) > 3)
        {
            $get = array();
            for ($i=3;$i<count($args);$i++)
                if (is_array($args[$i]))
                    $get = array_merge_recursive($get, $args[$i]);
                else
                    $res .= '/' . self::escape($args[$i]);
            if ($get)
                $res .= '?' . http_build_query($get);
        }
        return $res;
    }
    /**
     * @return Zend_Session_Namespace */
    public function getSession()
    {
        return $this->getDi()->session;
    }
    
    /** @return Am_Module|null */
    public function getModule()
    {
        $module = $this->_request->getModuleName();
        if ($module == 'default') return null;
        return $this->getDi()->modules->get($module);
    }
    
    /**
     * Transform PHP class name to CSS class
     */
    static function classToCss($className)
    {
        return strtolower(preg_replace('/\-+/', '-', str_replace('_', '-', fromCamelCase($className))));
    }

    protected function _redirect($url, array $options = array())
    {
        $this->_helper->redirector->setExit(false);
        parent::_redirect($url, $options);
        throw new Am_Exception_Redirect($url);
    }
}

