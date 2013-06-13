<?php

class Am_Form_Setup_Helpdesk extends Am_Form_Setup
{
    function __construct()
    {
        parent::__construct('helpdesk');
        $this->setTitle(___('Helpdesk'));
        $this->data['help-id'] = 'Setup/Helpdesk';
    }
    function initElements()
    {
         $this->addElement('textarea', 'helpdesk.intro', array('style'=>"width:90%"), array('help-id' => '#Editing_Text_on_Helpdesk_Page'))
                 ->setLabel(___("Intro Text on Helpdesk Page\n") . ___("It can contain html markup"));
         $this->setDefault('helpdesk.intro', 'We answer customer tickets Mon-Fri, 10am - 5pm EST. You can also call us by phone if you have an urgent question.');
         
          $this->addElement('email_checkbox', 'helpdesk.notify_new_message', null, array('help-id' => '#Enabling.2FDisabling_Customer_Notifications'))
                 ->setLabel(___("Send Notification about New Messages to Customer\n". 
                     "aMember will email a notification to user\n".
                     "each time admin responds to a user ticket"));
          $this->setDefault('helpdesk.notify_new_message', 1);

          $this->addElement('email_checkbox', 'helpdesk.notify_new_message_admin')
                 ->setLabel(___("Send Notification about New Messages to Admin\n". 
                     "aMember will email a notification to admin\n".
                     "each time user responds to a ticket"));
          $this->setDefault('helpdesk.notify_new_message_admin', 1);

          $this->addElement('email_checkbox', 'helpdesk.new_ticket')
                 ->setLabel(___("New Ticket Autoresponder to Customer\n".
                     "aMember will email an autoresponder to user\n".
                     "each time user create new ticket"));

          $this->addAdvCheckbox('helpdesk.add_signature')
              ->setLabel(___("Add Signature to Response"));

          $this->addTextarea('helpdesk.signature', array('rows'=>5, 'cols'=>50))
              ->setLabel(___("Signature Text\n".
                  "You can use the following placeholders %name_f%, %name_l%\n" .
                  "it will be expanded to first and last name of admin in operation"));

          $this->addScript('script')
              ->setScript(<<<CUT
(function($){
    $(function(){
        $("[id='helpdesk.add_signature-0']").change(function(){
            $("[id='helpdesk.signature-0']").closest('div.row').toggle(this.checked);
        }).change()
    })
})(jQuery)
CUT
              );

          $this->addAdvCheckbox('helpdesk.does_not_show_faq_tab')
              ->setLabel(___("Does Not Show FAQ Tab in Member Area"));
    }
}
