<?php

class Am_Form_Element_AffCommissionSize extends HTML_QuickForm2_Container_Group
{
    public function __construct($name = null, $attributes = null, $data = null)
    {
        parent::__construct($name, $attributes, null);
        
        $this->addElement('text', $data.'_c', array('size' => 5, ));
        
        $sel = $this->addElement('select', $data.'_t', array('size' => 1,), array('options' => 
            array(
                '%' => '%',
                '$' => Am_Currency::getDefault(),
            )
        ));
    } 
}