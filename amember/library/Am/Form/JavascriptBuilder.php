<?php

/**
 * Javascript validation builder based on HTML_QF2 and Jquery.Validate
 * @package Am_Form 
 */
class Am_Form_JavascriptBuilder extends HTML_QuickForm2_JavascriptBuilder
{
    protected $rules = array();
    protected $messages = array();
    protected $scripts = array();
    
    protected $addValidateJs = array();
    
    public function __construct($defaultWebPath = 'js/', $defaultAbsPath = null) 
    {
        $this->scripts = array();
        $this->libraries = array();
    }
    
    function _getCompare(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el){
        $config = $rule->getConfig();
        if ($config['operator'] == '===' && $config['operand'] instanceof HTML_QuickForm2_Node_InputPassword)
            return array('equalTo', '#' . $config['operand']->getId());
    }
    function _getLength(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el){
        $config = $rule->getConfig();
        if ($config['min'] && $config['max']) {
            return array('rangelength', array($config['min'], $config['max']));
        } elseif ($config['min']) {
            return array('minlength', $config['min']);
        } elseif ($config['max']) {
            return array('maxlength', $config['max']);
        } else {
            return array(null,null);
        }
    }
    function _getNonempty(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el){
        return array('required', true);
    }
    function _getRequired(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el){
        return array('required', true);
    }
    function _getRegex(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el){
        if ($el instanceof Am_Form_Element_Date) return; // @todo fix it up!
        if (preg_match('{^(/|\|)(.+)(\\1)([giDm]*)$}', $rule->getConfig(), $regs))
            $params = array($regs[2], str_replace('D', '', $regs[4]));
        else
            throw new Am_Exception_InternalError("Cannot parse regexp [$params] for use in " .__METHOD__);
        return array('regex', $params);
    }
    /**
     * @return null|array(rule_id, array|string JqueryValidateRuleDef)
     */
    function translateRule(HTML_QuickForm2_Rule $rule, HTML_QuickForm2_Node $el){
        $ret = null;
        $method = '_get' . preg_replace('/^HTML_QuickForm2_Rule_/', '', get_class($rule));
        if (method_exists($this, $method))
            $ret = $this->$method($rule, $el);
        return $ret ? $ret : array(null, null);
    }

    public function addRule(HTML_QuickForm2_Rule $rule, $triggers = false) {
        $id = $rule->getOwner()->getName();
        if ($id == '') return;
        list($ruleType, $ruleDef) = $this->translateRule($rule, $rule->getOwner());
        if (!$ruleType) return;
        $this->rules[$id][$ruleType] = $ruleDef;
        $this->messages[$id][$ruleType] = $rule->getMessage();
    }
    public function addElementJavascript($script) {
        $this->scripts[] = (string)$script;
    }
    public function addValidateJs($script)
    {
        $this->addValidateJs[] = $script;
    }
    public static function encode($value) {
        return Am_Controller::getJson($value);
    }
    public function setFormId($formId) {
    }
    public function getFormJavascript($formId = null, $addScriptTags = true) {
        $rules = Am_Controller::getJson($this->rules);
        $messages = Am_Controller::getJson($this->messages);
        $formId = Am_Controller::getJson('form#'. $formId);
        $output = <<<CUT
<script type="text/javascript">
jQuery(document).ready(function($) {
    if (jQuery && jQuery.validator)
    {
        jQuery.validator.addMethod("regex", function(value, element, params) {
            return this.optional(element) || new RegExp(params[0],params[1]).test(value);
        }, "Invalid Value");

        jQuery($formId).validate({
            ignore: ':hidden'
            ,rules: $rules
            ,messages: $messages
            //,debug : true
            ,errorPlacement: function(error, element) {
                error.appendTo( element.parent());
            }
            // custom validate js code start
            //-CUSTOM VALIDATE JS CODE-//
            // custom validate js code end
        });
    }
    // custom js code start
    //-CUSTOM JS CODE-//
    // custom js code end
});
</script>
CUT;
        $output = str_replace('//-CUSTOM JS CODE-//', implode(";\n", $this->scripts), $output);
        $addValidateJs = join(",\n", $this->addValidateJs);
        if ($addValidateJs != '')
            $output = str_replace('//-CUSTOM VALIDATE JS CODE-//', "\n," . $addValidateJs, $output);
        if (!$this->rules && !$this->scripts && !$this->addValidateJs) return null;
        return $output;
    }
}