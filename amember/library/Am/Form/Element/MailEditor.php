<?php

/**
 * Provides an admin e-mail body editor UI element
 * @package Am_Form 
 */
class Am_Form_Element_MailEditor extends HTML_QuickForm2_Container_Group
{
    protected $editor;
    
    public function __construct($name, $options = array())
    {
        parent::__construct('', array('id' => 'mail-editor'));
        
        $subject = $this->addElement('text', 'subject', array('size'=>80))
                ->setLabel(___('Subject'));
        $subject->addRule('required');
        $this->addStatic()->setContent('<br /><br />');

        $format = $this->addGroup(null)->setLabel(___('E-Mail Format'));
        $format->addRadio('format', array('value'=>'html'))->setContent(___('HTML Message'));
        $format->addRadio('format', array('value'=>'text'))->setContent(___('Plain-Text Message'));
        $this->addStatic()->setContent('<br /><br />');

        $this->editor = $this->addElement(new Am_Form_Element_HtmlEditor('txt', null, true));
        $this->editor->addRule('required');
        $this->addStatic()->setContent('<br /><br />');
        
        $this->tagsOptions = Am_Mail_TemplateTypes::getInstance()->getTagsOptions($name);
        $tagsOptions = array();
        foreach ($this->tagsOptions as $k => $v)
            $tagsOptions[$k] = "$k - $v";
        $sel = $this->addSelect('', array('id'=>'insert-tags', ));
        $sel->loadOptions(array_merge(array(''=>''), $tagsOptions));
        $this->addStatic()->setContent('<br /><br />');
        $this->addClass('no-label');

        $prefix = isset($options['upload-prefix']) ? $options['upload-prefix'] : EmailTemplate::ATTACHMENT_FILE_PREFIX;

        $fileChooser = new Am_Form_Element_Upload('attachments',
                array('multiple'=>1), array('prefix'=>$prefix));
        $this->addElement($fileChooser)->setLabel(___('Attachments'));
    }
    
    protected function renderClientRules(HTML_QuickForm2_JavascriptBuilder $builder)
    {
        $id = Am_Controller::escape($this->editor->getId());
        $vars = "";
        foreach ($this->tagsOptions as $k => $v)
            $vars .= "['$k'],\n";
        $vars = trim($vars, "\n\r,");
        $builder->addElementJavascript(<<<CUT
$(function(){
    // modified version of http://alexking.org/blog/2003/06/02/inserting-at-the-cursor-using-javascript
    $.fn.insertAtCaret = function (myValue) {
            return this.each(function(){
                    //IE support
                    if (document.selection) {
                            this.focus();
                            sel = document.selection.createRange();
                            sel.text = myValue;
                            this.focus();
                    }
                    //MOZILLA/NETSCAPE support
                    else if (this.selectionStart || this.selectionStart == '0') {
                            var startPos = this.selectionStart;
                            var endPos = this.selectionEnd;
                            var scrollTop = this.scrollTop;
                            this.value = this.value.substring(0, startPos)
                                          + myValue
                                  + this.value.substring(endPos,
    this.value.length);
                            this.focus();
                            this.selectionStart = startPos + myValue.length;
                            this.selectionEnd = startPos + myValue.length;
                            this.scrollTop = scrollTop;
                    } else {
                            this.value += myValue;
                            this.focus();
                    }
            });

    };
            
    $('select#insert-tags').change(function(){
        var val = $(this).val();
        if (!val) return;
        $("#txt-0").insertAtCaret(val);
        $(this).prop("selectedIndex", -1);
    });
            
    if (CKEDITOR.instances["$id"]) {
        delete CKEDITOR.instances["$id"];
    }
    var editor = null;
    $("input[name='format']").change(function()
    {
        if (!this.checked) return;
        if (this.value == 'html')
        {
            if (!editor) {
                editor = initCkeditor("$id", { placeholder_items: [
                    $vars
                ]});
            }
            $('select#insert-tags').hide();
        } else {
            if (editor) {
                editor.destroy();
                editor = null;
            }
            $('select#insert-tags').show();
        }
    }).change();
});            
CUT
            );
    }
}