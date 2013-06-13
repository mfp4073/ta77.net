<?php

class Am_Plugin_GoogleAnalytics extends Am_Plugin
{
    const PLUGIN_STATUS = self::STATUS_PRODUCTION;
    const PLUGIN_REVISION = '4.2.17';

    protected $id;
    protected $done = false;
    public function __construct(Am_Di $di, array $config)
    {
        $this->id = $di->config->get('google_analytics');
        parent::__construct($di, $config);
    }
    public function isConfigured()
    {
        return !empty($this->id);
    }
    function onSetupForms(Am_Event_SetupForms $forms)
    {
        $form = new Am_Form_Setup('google_analytics');
        $form->setTitle("Google Analytics");
        $forms->addForm($form);
        $form->addElement('text', 'google_analytics')
             ->setLabel(array('Google Analytics Account ID', 'To enable automatic sales and hits tracking with GA,
             enter Google Analytics cAccount ID into this field.
             <a href=\'http://www.google.com/support/googleanalytics/bin/answer.py?answer=55603\' target=_blank>Where can I find my tracking ID?</a>
             The tracking ID will look like <i>UA-1231231-1</i>.
             Please note - this tracking is only for pages displayed by aMember,
             pages that are just protected by aMember, cannot be tracked. 
             Use '.
             '<a href="http://www.google.com/support/googleanalytics/bin/search.py?query=how+to+add+tracking&ctx=en%3Asearchbox" target=_blank>GA instructions</a>
             how to add tracking code to your own pages.
             '));
        $form->addAdvCheckbox("google_analytics_only_sales_code")
            ->setLabel(array("Include only sales code", "Enable this if you already have tracking code in template"));
        $form->addAdvCheckbox("google_analytics_track_free_signups")
            ->setLabel(array("Track free signups"));
    }
    function onAfterRender(Am_Event_AfterRender $event)
    {
        if ($this->done) return;
        if (preg_match('/thanks\.phtml$/', $event->getTemplateName()) && $event->getView()->invoice && $event->getView()->payment)
        {
            $this->done += $event->replace("|</body>|i", $this->getHeader() . 
                    $this->getSaleCode($event->getView()->invoice, $event->getView()->payment) . "</body>", 1);
        } 
        elseif (preg_match('/signup\.phtml$/', $event->getTemplateName()))
        {
            $this->done += $event->replace("|</body>|i", $this->getHeader() . 
                    $this->getTrackingCode(). $this->getSignupCode(). "</body>", 1);
        } 
        elseif (!preg_match('/\badmin\b/', $t = $event->getTemplateName()) && !$this->getDi()->config->get("google_analytics_only_sales_code"))
        {
            $this->done += $event->replace("|</body>|i", $this->getHeader() . $this->getTrackingCode() . "</body>", 1);
        }
    }
    function getTrackingCode()
    {
        return <<<CUT

<script type="text/javascript">
if (typeof(_gaq)=='object') { // sometimes google-analytics can be blocked and we will avoid error
    _gaq.push(['_setAccount', '{$this->id}']);
    _gaq.push(['_trackPageview']);
}
</script>
<!-- end of GA code -->

CUT;
    }
    function getSignupCode()
    {
    }
    function getSaleCode(Invoice $invoice, InvoicePayment $payment)
    {
        $out = <<<CUT

<script type="text/javascript">
if (typeof(_gaq)=='object') { // sometimes google-analytics can be blocked and we will avoid error
    _gaq.push(['_setAccount', '{$this->id}']);
    _gaq.push(['_trackPageview']);
}
</script>
CUT;
        if (empty($payment->amount) && !$this->getDi()->config->get('google_analytics_track_free_signups')) {
            return $out;
        } elseif (empty($payment->amount)) {
            $a = array(
                $invoice->public_id,
                "",
                0,
                0,
                0,
                $invoice->getCity(),
                $invoice->getState(),
                $invoice->getCountry(),
            );
        } else {
            $a = array(
                $payment->transaction_id,
                "",
                $payment->amount,
                $payment->tax,
                $payment->shipping,
                $invoice->getCity(),
                $invoice->getState(),
                $invoice->getCountry(),
            );
        }
        $a = implode(",\n", array_map('json_encode', $a));
        $items = "";
// uncomment to enable items tracking
//        foreach ($invoice->getItems() as $item)
//        {
//            $items .= "['_addItem', '$payment->transaction_id', '$item->item_id', '$item->item_title','', $item->first_total, $item->qty],";
//        }
        return $out . <<<CUT
<script type="text/javascript">
if (typeof(_gaq)=='object') { // sometimes google-analytics can be blocked and we will avoid error
    _gaq.push(
        ['_addTrans', $a],
        $items
        ['_trackTrans']
    );
}
</script>
<!-- end of GA code -->
CUT;
    }
    function getHeader()
    {
        return <<<CUT

<!-- start of GA code -->
<script type="text/javascript">
    var gaJsHost = (("https:" == document.location.protocol) ? "https://ssl." : "http://www.");
    document.write(unescape("%3Cscript src='" + gaJsHost + "google-analytics.com/ga.js' type='text/javascript'%3E%3C/script%3E"));
</script>
CUT;
    }
}
