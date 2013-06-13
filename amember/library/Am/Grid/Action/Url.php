<?php

/**
 * Action that just displays a given link 
 * if URL contains __ID__ it will be replaced with actual ID of the record
 */
class Am_Grid_Action_Url extends Am_Grid_Action_Abstract
{
    protected $privilege = 'browse';
    protected $url;
    public function __construct($id, $title, $url)
    {
        $this->id = $id;
        $this->title = $title;
        $this->url = $url;
        parent::__construct();
    } 
    public function getUrl($record = null, $id = null)
    {
        return str_replace(array('__ID__', '__ROOT__'), array($id, REL_ROOT_URL), $this->url);
    }
    public function run()
    {
    }
}