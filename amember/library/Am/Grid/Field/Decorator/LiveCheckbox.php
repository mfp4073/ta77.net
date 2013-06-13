<?php

/**
 * This decorator will be automatically added by live-checkbox action
 */
class Am_Grid_Field_Decorator_LiveCheckbox extends Am_Grid_Field_Decorator_Abstract
{

    /** @var Am_Grid_Action_LiveCheckbox */
    protected $action;

    public function __construct(Am_Grid_Action_LiveCheckbox $action)
    {
        $this->action = $action;
        parent::__construct();
    }

    public function render(&$out, $obj, $grid)
    {
        $content = $this->getContent($obj, $grid);
        preg_match('{(<td.*>)(.*)(</td>)}i', $out, $match);
        $out = $match[1] . $content . $match[3];
    }

    protected function divideUrlAndParams($url)
    {
        $ret = explode('?', $url, 2);
        if (count($ret) <= 1)
            return array($ret[0], null);
        parse_str($ret[1], $params);
        return array($ret[0], $params);
    }

    protected function getContent($obj, Am_Grid_Editable $grid)
    {
        $id = $this->action->getIdForRecord($obj);
        $val = $obj->{$this->field->getFieldName()};
        list($url, $params) = $this->divideUrlAndParams($this->action->getUrl($obj, $id));
        $content = sprintf('<input name="%s" class="live-checkbox" data-url="%s" data-id="%d" data-params="%s" type="checkbox" %s/>',
                Am_Controller::escape($grid->getId() . '_' . $this->field->getFieldName() . '-' . $grid->escape($id)),
                Am_Controller::escape($url),
                $id, Am_Controller::escape(Am_Controller::getJson($params)), ($val ? 'checked ' : ''));

        return $content;
    }

}