<?php

/**
 * Provides an UI element to edit regional taxes
 * @package Am_Form
 */
class Am_Form_Element_RegionalTaxes extends HTML_QuickForm2_Element 
{

    protected $regional_taxes = array();

    public function getRawValue()
    {
        return $this->regional_taxes;
    }
    protected function updateValue()
    {
        $name = $this->getName();
        $name2 = Am_Form_Setup::name2underscore($name);
        foreach ($this->getDataSources() as $ds) {
            if (null !== ($value = $ds->getValue($name))) {
                $this->setValue($value);
                return;
            }
            if (null !== ($value = $ds->getValue($name2))) {
                $this->setValue($value);
                return;
            }
        }
    }

    public function setValue($value)
    {
        foreach ((array)$value as $k => $r)
        {
            if (is_array($r)) continue;
            $r = preg_replace('/[^A-Za-z0-9_.,|-]/', '', $r);
            $r = explode('|', $r, 4);
            $value[$k] = array(
                'country' => $r[0],
                'state'   => $r[1],
                'zip'     => $r[2],
                'tax_value'    => sprintf('%.2f', $r[3]),
            );
        }
        $this->regional_taxes = $value;
    }

    public function getType()
    {
        return 'custom_row';
    }

    public function __toString()
    {
        $output = sprintf('<div style="padding:0.5em"><h1>%s</h1><table id="regional-tax-table" %s><tr><th>%s</th>
	<th>%s</th>
        <th>%s</th>
	<th>%s</th>
	<th>&nbsp;</th></tr>',
            ___('Configured Tax Values'),
            'class="grid"',
            ___('Country'),
            ___('State'),
            ___('Zip'),
            ___('Tax Value') 
        );
        
        $output .= "<tr class='regional-tax-add'>" . 
            "<td width='30%'><select id='regional-tax-country' size='1'><option/>".Am_Controller::renderOptions(Am_Di::getInstance()->countryTable->getOptions())."</select>" .
            "<td width='30%'><select id='regional-tax-state' size='1'></select></td>" . 
            "<td><input type='text' id='regional-tax-zip' /></td>" . 
            "<td><input type='text' id='regional-tax-rate' size='5' maxlength='5' /></td>" . 
            "<td><input type='button' id='regional-tax-add-button' value='Add'/></td>" . 
            "</tr>\n";
            ;
            
        
        $name = $this->getName();
        foreach ($this->regional_taxes as $id => $region) {
            $hidden = Am_Controller::escape(implode('|', array(
                $region['country'],
                $region['state'],
                $region['zip'],
                $region['tax_value'],
            )));
            $output .= '<tr>'
                . sprintf('<td>%s</td>', Am_Di::getInstance()->countryTable->getTitleByCode($region['country']))
                . sprintf('<td>%s</td>', ($region['state'] ? Am_Di::getInstance()->stateTable->getTitleByCode($region['country'], $region['state']) : '*'))
                . sprintf('<td>%s</td>', ($region['zip'] ? $region['zip'] : '*'))
                . sprintf('<td>%.2f%s</td>', $region['tax_value'], '&nbsp;%')
                . sprintf('<td><a href="javascript:" class="regional-tax-remove">%s</a>%s</td>',
                        ___('Remove'),
                        "<input type='hidden' name='{$name}[]' value='$hidden'>")
                . '</tr>';
        }

        $output .= '</table></div>';
        $id = $this->getId();
        $output .= "
        <style type='text/css'>
            #row-$id .element-title { display: none;  }
            #row-$id .element { margin-left: 0 } 
        </style>
        ";
        return sprintf('<tr><td colspan="2" id="tax-regional-regions">%s</td></tr>', $output . $this->getJs());
    }
    
    function getJs()
    {
        $countries = json_encode(Am_Di::getInstance()->countryTable->getOptions());
        $states = array();
        foreach (Am_Di::getInstance()->db->select("SELECT country, state, title FROM ?_state") as $r)
            $states[$r['country']][$r['state']] = $r['title'];
        $states = json_encode($states);
        $name = $this->getName();
        $remove = ___("Remove");
        return <<<CUT
<script type='text/javascript'>
$(function(){
    var countries = $countries;
    var states = $states;
    $("#regional-tax-country").change(function(){
        var sel = $("#regional-tax-state");
        sel.find('option').remove();
        var options = states[ $(this).val() ];
        sel.append($('<option>'));
        if (!options) return;
        $.each(options, function(key, value) {   
            sel.append($('<option>', { value : key })
                .text(value)); 
        });
    });
    $("#regional-tax-add-button").click(function(){
        var c = $("#regional-tax-country").val();
        var r = $("#regional-tax-rate").val();
        if (!c || !r) {
            flashError("Country and Tax Rate fields are required");
            return;
        }
        var s = $("#regional-tax-state").val();
        var z = $("#regional-tax-zip").val();
        //
        var row = $("<tr />");
        row.append($("<td />").text(countries[c]));
        row.append($("<td />").text(states[c][s] ? states[c][s] : ''));
        row.append($("<td />").text(z));
        row.append($("<td />").text(r));
        var hidden = $("<input type='hidden' name='{$name}[]'/>").val(c+'|'+s+'|'+z+'|'+r);
        row.append($("<td><a href='javascript:' class='regional-tax-remove'>$remove</a></td>").append(hidden));
        $("#regional-tax-zip").val("");
        $("#regional-tax-state").val("");
        $("#regional-tax-table").append(row);
    });
    $("a.regional-tax-remove").live('click', function(){
        $(this).closest("tr").remove();
    });
});
</script>
CUT;
    }
}
