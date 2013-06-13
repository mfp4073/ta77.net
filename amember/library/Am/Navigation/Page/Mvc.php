<?php

class Am_Navigation_Page_Mvc extends Zend_Navigation_Page_Mvc {
    function  setResource($resource = null)
    {
        $this->_resource = $resource;
    }
}
