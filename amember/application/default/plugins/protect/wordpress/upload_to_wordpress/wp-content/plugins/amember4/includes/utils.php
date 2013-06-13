<?php
if(!function_exists('am4_get_current_screen')){
function am4_get_current_screen() {
    global $current_screen;

    if ( ! isset( $current_screen ) )
        return null;

    return $current_screen;
}
}
if(!function_exists('get_called_class')){
function get_called_class($bt = false, $l = 1) {
        if (!$bt) $bt = debug_backtrace();
        if (!isset($bt[$l])) throw new Exception("Cannot find called class -> stack level too deep.");
        if (!isset($bt[$l]['type'])) {
            throw new Exception ('type not set');
        }
        else switch ($bt[$l]['type']) {
                case '::':
                    $lines = file($bt[$l]['file']);
                    $i = 0;
                    $callerLine = '';
                    do {
                        $i++;
                        $callerLine = $lines[$bt[$l]['line']-$i] . $callerLine;
                    } while (stripos($callerLine,$bt[$l]['function']) === false);
                    preg_match('/([a-zA-Z0-9\_]+)::'.$bt[$l]['function'].'/',
                            $callerLine,
                            $matches);
                    if (!isset($matches[1])) {
                        // must be an edge case.
                        throw new Exception ("Could not find caller class: originating method call is obscured.");
                    }
                    switch ($matches[1]) {
                        case 'self':
                        case 'parent':
                            return get_called_class($bt,$l+1);
                        default:
                            return $matches[1];
                    }
                // won't get here.
                case '->': switch ($bt[$l]['function']) {
                        case '__get':
                        // edge case -> get class of calling object
                            if (!is_object($bt[$l]['object'])) throw new Exception ("Edge case fail. __get called on non object.");
                            return get_class($bt[$l]['object']);
                        default: return $bt[$l]['class'];
                    }

                default: throw new Exception ("Unknown backtrace method type");
            }
    }
}



if ( false === function_exists('lcfirst') ){
    function lcfirst( $str )
    { return (string)(strtolower(substr($str,0,1)).substr($str,1));}
}

function am4_to_camel($string) {
    return str_replace(' ', '', ucwords(preg_replace('/[_-]+/', ' ', $string)));
}


function am4_from_camel($string, $separator="_") {
    return strtolower(preg_replace('/([A-Z])/', $separator.'\1', lcfirst($string)));
}


class aMemberJson{
    protected $_data = array();

    function __construct($arr = null){
        if($arr) $this->_data = $arr;

    }

    function setError($str){
        $this->_data['error'] = $str;
    }

    function send(){
        echo $this->__toString();
    }

    function  __get($name) {
        if(array_key_exists($name, $this->_data))
            return $this->_data[$name];
        else
            return false;
    }
    function  __set($name, $value) {
        $this->_data[$name] = $value;
    }
    
    function  __toString() {
        return json_encode($this->_data);
    }
    static function init($arr){
        return new self($arr);
    }
}


class aMemberJsonError extends aMemberJson {
    function __construct($error){
        $this->setError($error);
    }
}


class am4Request{
    static $vars = array();
    static $post = array();
    static $get = array();
    static $method = "GET";
    const VARS = 'vars';
    const GET = 'get';
    const POST = 'post';
    
    
    static function get($k,$default=''){
        if(!array_key_exists($k, self::$vars)) return $default;
        return self::$vars[$k]  ? self::$vars[$k] : 'default';
    }
    
    static function getWord($k,$default=''){
        $r = self::get($k, $default);
        return preg_replace('/[^a-zA-Z0-9]/', '', $r);
    }
    
    static function getInt($k,$default=''){
        return intval(self::get($k,$default));
    }
    static function defined($k){
        return array_key_exists($k, self::$vars);
    }
    static function init(){
        foreach($_GET as $k=>$v){
            self::$vars[$k] = $v;
            self::$get[$k] = $v;
        }
        foreach($_POST as $k=>$v){
            self::$vars[$k] = $v;
            self::$post[$k] = $v;
        }
        self::$method = $_SERVER['REQUEST_METHOD'];
            
    }
}
am4Request::init();


function strleft($s1, $s2) {
	return substr($s1, 0, strpos($s1, $s2));
}