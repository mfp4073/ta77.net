<?php

/**
 * Represents action that is necessary to finish payment
 * @package Am_Paysystem
 */
interface Am_Paysystem_Action
{
    public function process(Am_Controller $action = null);
    public function toXml(XMLWriter $x);
} 