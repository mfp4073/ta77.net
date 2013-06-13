<?php

/**
 * External HTTP Request class, based on PEAR's HTTP_Request2
 * @see http://pear.php.net/package/HTTP_Request2/
 * @package Am_Utils
 */
class Am_HttpRequest extends HTTP_Request2 
{
    function  __construct($url = null, $method = self::METHOD_GET, array $config = array()) {
        if (extension_loaded('curl') && (strpos(@ini_get('disable_functions'), 'curl_exec')===false))
            $this->setConfig('adapter', 'HTTP_Request2_Adapter_Curl');
        $this->setConfig('proxy_host', Am_Di::getInstance()->config->get('http.proxy_host'));
        $this->setConfig('proxy_port', Am_Di::getInstance()->config->get('http.proxy_port'));
        $this->setConfig('proxy_user', Am_Di::getInstance()->config->get('http.proxy_user'));
        $this->setConfig('proxy_password', Am_Di::getInstance()->config->get('http.proxy_password'));
        $this->setConfig('ssl_verify_peer', Am_Di::getInstance()->config->get('http.verify_peer', false));
        $this->setConfig('ssl_verify_host', Am_Di::getInstance()->config->get('http.verify_host', false));
        $this->setConfig('ssl_cafile', Am_Di::getInstance()->config->get('http.ssl_cafile', null));
        $this->setConfig('ssl_cafile', Am_Di::getInstance()->config->get('http.ssl_cafile', null));
        parent::__construct($url, $method, $config);
    }
    
    function getPostParams()
    {
        return $this->postParams;
    }
    function toXml(XmlWriter $x, $writeEnvelope = true)
    {
        if ($writeEnvelope)
            $x->startElement('http-request');
        
        $x->startElement('method'); $x->text($this->getMethod()); $x->endElement();
        $x->startElement('url'); $x->text($this->getUrl()); $x->endElement();
        $x->startElement('headers');
        foreach ($this->getHeaders() as $k => $v)
        {
            $x->startElement('header');
            $x->writeAttribute('name', $k);
            $x->text($v);
            $x->endElement();
        }
        $x->endElement();
        $x->startElement('params');
        foreach ($this->getPostParams() as $k => $v)
        {
            $x->startElement('param');
            $x->writeAttribute('name', $k);
            $x->text($v);
            $x->endElement();
        }
        $x->endElement();
        if (!$this->getPostParams() && $this->getBody()) // plain xml request?
        {
            $x->startElement('body');
            $x->writeCdata($this->getBody());
            $x->endElement();
        }
        if ($writeEnvelope)
            $x->endElement();
    }
    /**
     * For unit-testing only!
     * @access private
     * @return Am_HttpRequest_Adapter_Mock
     */
    function _getAdapter()
    {
        return $this->adapter;
    }
}
