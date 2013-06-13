<?php

class Am_Form_Admin_FixLicense extends Am_Form_Admin {

    function init() 
    {
         $this->addElement('text', 'root_url', array (
           'size' => 40,
         ))
             ->setLabel(___("Root URL\nroot script URL, usually %s", '<i>http://www.yoursite.com/amember</i>'))
             ->addRule('callback2', '-error-must-be-returned-', array($this, 'validateRootUrl'));

         $this->addElement('text', 'root_surl', array (
           'size' => 40,
         ))
             ->setLabel(___("Secure Root URL\nsecure URL, usually %s", '<i>http<b>s</b>://www.yoursite.com/amember</i>'))
             ->addRule('callback2', '-error-must-be-returned-', array($this, 'validateRootUrl'));

         if ('==TRIAL==' == '=='.'TRIAL==')
         {
             $license = Am_Di::getInstance()->config->get('license');
             $this->addElement('textarea', 'license', array(
                        'style' => 'width:95%',
                        'rows' => count(explode("\n", $license))+1,
                    ))
                    ->setLabel(___("License Key"))
                    ->addRule('required')
                    ->addRule('notregex', ___('You have license keys from past versions of aMember, please replace it with latest, one-line keys'), 
                        '/====\s+LICENSE/')
                    ->addRule('callback', ___('Valid license key are one-line string,starts with L and ends with X'),
                        array($this, 'validateKeys'));
             
             if ($domains = Am_License::getInstance()->getDomains())
             {
                 $cnt = '<i>'.implode(",", Am_License::getInstance()->getDomains()).'</i> ';
                 $cnt .= ___("expires") . ' ';
                 $cnt .= amDate(Am_License::getInstance()->getExpires());
             } else {
                 $cnt = "No License Configured";
             }
         } else {
             $cnt = "Using TRIAL Version - expires ==TRIAL_EXPIRES==";
         }
         $this->addElement('static')->setLabel(___('Configured License Keys'))->setContent(sprintf('<div>%s</div>', $cnt));
         parent::init();
         
         $this->addSaveButton(___('Update License Information'));
    }
    
    function validateKeys($keys)
    {
        $keys = explode("\n", $keys);
        $ok = 0;
        foreach ($keys as $k)
        {
            $k = trim($k, "\t\n\r ");
            if (empty($k)) continue;
            if (!preg_match('/^L[A-Za-z0-9\/=+]+X$/', $k)) continue;
            $ok++;
        }
        return $ok > 0;
    }

    function getDsDefaults() 
    {
        return new HTML_QuickForm2_DataSource_Array(array(
            'license' => Am_Di::getInstance()->config->get('license'),
            'root_url' => Am_Di::getInstance()->config->get('root_url'),
            'root_surl' => Am_Di::getInstance()->config->get('root_surl')
        ));
    }

    function validateRootUrl($url)
    {
        if (defined('APPLICATION_HOSTED')) return;

        if (!preg_match('/^http(s|):\/\/.+$/', $url))
            return ___("URL must start from %s or %s", '<i>http://</i>', '<i>https://</i>');
        if (preg_match('/\/+$/', $url))
            return ___("URL must be specified without trailing slash");
    }
}

class AdminLicenseController extends Am_Controller {

    public function checkAdminPermissions(Admin $admin) {
        return $admin->isSuper();
    }

    function indexAction() {
        $this->view->title = ___('Fix aMember Pro License Key');

        $this->view->msg = Am_License::getInstance()->check();

        $form = new Am_Form_Admin_FixLicense();

        $form->setDataSources(array(
            $this->getRequest(),
            $form->getDsDefaults()
        ));

        if ($form->isSubmitted() && $form->validate()) {
            $vars = $form->getValue();
            Am_Config::saveValue('license', $vars['license']);
            Am_Config::saveValue('root_url', $vars['root_url']);
            Am_Config::saveValue('root_surl', $vars['root_surl']);
            return $this->redirectLocation($this->getFullUrl());
        }

        $this->view->form = $form;
        $this->view->display('admin/fixlicense.phtml');
    }

}

