<?php
/*
*     Author: Alex Scott
*      Email: alex@cgi-central.net
*        Web: http://www.amember.com/
*    Release: 4.2.17
*    License: LGPL http://www.gnu.org/copyleft/lesser.html
*/

/**
 * Basic text filter for grid - limit records display by searching
 * of entered filter text in configured datasource fields
 */
class Am_Grid_Filter_Text extends Am_Grid_Filter_Abstract
{
    protected $fields = array();
    protected $attributes = array(
        'size' => 30,
    );
    public function __construct($title, $fields, $attributes = array())
    {
        $this->title = $title;
        if (!is_array($fields))
            $this->fields = array($fields => '=');
        else
            $this->fields = $fields;
        if ($attributes)
            $this->attributes = $attributes;
    }
    protected function applyFilter()
    {
        $filter = $this->vars['filter'];
        if (empty($filter) || !$this->fields) return ;
        $condition = null;
        foreach ($this->fields as $field => $op)
        {
            $c = new Am_Query_Condition_Field($field, $op, $op == 'LIKE' ? "%$filter%" : $filter);
            if (!$condition) 
                $condition = $c;
            else
                $condition->_or($c);
        }
        $this->grid->getDataSource()->getDataSourceQuery()->add($condition);
    }
    public function renderInputs()
    {
        return $this->renderInputText();
    }
}