


$.fn.billingPlan = function() {
return this.each(function(){
    var plan = this; // fieldset
    // get id
    var id = $(this).attr("id").replace(/^plan-/, '');
    if (id == 'TPL') return;
    $(this).data("id", id);
    // add [-] or [+] sign
    $("legend span", this)
        .before("&nbsp;<a class='collapse' href='javascript:'>[-]</a>&nbsp;");
    // add delete/create links
    $('legend', this)
        .append("&nbsp;<a class='del' href='javascript:'>Delete</a>")
        .append("&nbsp;<a class='add' href='javascript:'>Create</a>")
        .append('&nbsp;<b>Terms:</b> <i><span class="terms-text"></span></i>')
        .click(function(event){
            if (event.target.tagName != 'LEGEND') return;
            $("a.collapse", plan).click();
        });

    var first_price     = $("input[name$='[first_price]']", this);
    var first_period_c  = $("input[name$='[first_period][c]']", this);
    var first_period_u  = $("select[name$='[first_period][u]']", this);
    var t_rebill_times  = $("input[name$='[rebill_times]']", this);
    var s_rebill_times  = $("select[name$='[_rebill_times]']", this);
    var second_price     = $("input[name$='[second_price]']", this);
    var second_period_c = $("input[name$='[second_period][c]']", this);
    var second_period_u = $("select[name$='[second_period][u]']", this);

    t_rebill_times.change(function(){
        if ($(this).val() >= 1) 
            $(second_period_c).add(second_price).parents(".row").show();
        else 
            $(second_period_c).add(second_price).parents(".row").hide();
    });
    s_rebill_times.change(function(){
        var sel = $(this);
        var val = sel.val();
        var txt = t_rebill_times;
        if (val == "x") {
            txt.show();
            if (txt.data("saved_value") != null)
                txt.val(txt.data("saved_value"));
        } else {
            if (sel.data("saved_value") == "x")
                txt.data("saved_value", txt.val());
            txt.val(val);
            txt.hide();
        }
        sel.data("saved_value", sel.val());
        txt.change();
    }).change();
    first_period_u.change(function(){
        var val = $(this).val();
        val == "lifetime" ? first_period_c.hide() : first_period_c.show();
        if (val != 'lifetime') // if reverting from lifetime, reset "count"
        {
            if (first_period_c.val() == '2037-12-31')
                first_period_c.val('');
        }
        var showSecond = (val == "lifetime" || val == "fixed");
        if (showSecond) {
            s_rebill_times.val("0").change().parents(".row").hide();
        } else {
            s_rebill_times.parents(".row").show();
            if (second_price.val() == "" && second_period_c.val()=="")
            {
                second_price.val(first_price.val());
                second_period_c.val(first_period_c.val());
                second_period_u.val(first_period_u.val());
            }
        }
    }).change();
    second_period_u.change(function(){
        var val = $(this).val();
        val == "lifetime" ? second_period_c.hide() : second_period_c.show();
        if (val != 'lifetime') // if reverting from lifetime, reset "count"
        {
            s_rebill_times.find('option[value="x"],option[value="99999"]').prop("disabled", false);
            if (second_period_c.val() == '2037-12-31')
                second_period_c.val('');
        } else {
            // only allow to second price be charged once if lifetime
            s_rebill_times.val('1').change();
            s_rebill_times.find('option[value="x"],option[value="99999"]').prop("disabled", true);
        }
    }).change();
    
    plan.getPeriodText = function(c, u, skip_one_c)
    {
        var uu;
        switch (u){
            case 'd':uu = c==1 ? 'day': 'days';break;
            case 'm':uu = c==1 ? 'month' : 'months';break;
            case 'y':uu = c==1 ? 'year' : 'years';break;
            case 'fixed':return " up to " + c;
        }
        var cc = c;
        if (c == 1) cc = skip_one_c ? '' : 'one';
        return cc + ' ' + uu;
    }

    plan.calculateTerms = function()
    {
        var undef = "--";
        var vals = this.getValues();

        if (!vals.first_price.length && !vals.first_period_c.length)
            return undef;

        var first_price    = parseFloat(vals.first_price);
        var first_period_c = parseInt(vals.first_period_c);
        var first_period_u = vals.first_period_u;
        var rebill_times = parseInt(vals._rebill_times == 'x' ? vals.rebill_times : vals._rebill_times);
        var second_price = parseFloat(vals.second_price);
        var second_period_c = parseInt(vals.second_period_c);
        var second_period_u = vals.second_period_u;

        var currency = $(this).closest("form").find(":input[name='currency']").val();
        var c1 = first_price + ' ' + currency;
        if (first_price <= 0) c1 = 'Free';
        var c2 = second_price + ' ' + currency;
        if (second_price <= 0) c2 = 'free';

        var ret = c1;
        if (first_period_u != 'lifetime')
            if (rebill_times)
                ret += " for first " + this.getPeriodText(first_period_c, first_period_u, true)
            else
                ret += " for " + this.getPeriodText(first_period_c, first_period_u)
        if (rebill_times)
        {
            if (second_period_u == 'lifetime')
            {
                ret += ", then " + c2 + " for lifetime";
            } else {
                ret += ", then " + c2 + " for every " + this.getPeriodText(second_period_c, second_period_u);
                if (rebill_times < 9999)
                    ret += ", for " + (rebill_times) + " installments";
            }
        }
        return ret.replace(/[ ]+/g, ' ');
    }

    plan.getValues = function()
    {
        var vals = {};
        $(":input", this).each(function(){
            var el = $(this);
            vals[ 
                el.attr("name")
                .replace(/_plan\[.+?\]\[/, '')
                .replace(/\]$/, '')
                .replace(/\]\[/, '_')
            ] = typeof (el.val()) == 'string' ? el.val().replace(/^[ ]+/, '').replace(/[ ]+$/, '') : el.val();
        })
        return vals;
    }
    
    $([first_price[0], first_period_c[0], first_period_u[0], t_rebill_times[0], s_rebill_times[0],
        second_price[0], second_period_c[0], second_period_u[0]]).change(function(){
        $(".terms-text", plan).text(plan.calculateTerms());
    }).change();


});};

$(".billing-plan a.del").live("click", function(event){
    var id = $(this).parents(".billing-plan").data("id");
    if ($(".billing-plan").length <= 2)
    {
        alert("You cannot delete last billing plan. Please add another billing plan first");
        return;
    }
    if (!confirm("Are you sure you want to remove this billing plan?")) return;
    $("#plan-"+id).remove();
    event.stopPropagation();
});
$(".billing-plan a.add").live("click", function(event){
    var d = new Date();
    var newId = d.getTime();
    var html = $("#plan-TPL").html()
        .replace(/TPL/g, newId)
        .replace(/TEMPLATE/g, 'New Billing Plan');
    $("#plan-TPL").after('<fieldset class="billing-plan" id="plan-'+newId+'">' +  html + '</fieldset>');
    $("#plan-"+newId).billingPlan();
    $("#plan-"+newId+" .plan-title-text").click();
    event.stopPropagation();
});
$(".billing-plan a.collapse").live("click", function(event){
    var set = $(this).parents(".billing-plan");
    set.toggleClass("collapsed");
    if (set.hasClass("collapsed"))
    {
        $(".row", set).hide();
        $(".row.terms-text-row", set).show();
        $("a.collapse", set).text('[+]');
    } else {
        $(".row", set).show();
        $("a.collapse", set).text('[-]');
    }
    event.stopPropagation();
});

$(function(){
    $(".billing-plan .variable_qty").live("change", function(){
        var txt = $(this).parents(".element").find("input[type='text']");
        if (this.checked) 
            txt.val(1).prop("readonly", "readonly").css("color", "gray");
        else 
            txt.prop("readonly", null).css("color", "black");
    });
    $(".billing-plan .variable_qty").change();
});


$(".plan-title-text").live('click', function(event){
    var txt = $(this);
    var edit = txt.parents("legend").find(".plan-title-edit");
    txt.hide();
    edit.show();
    event.stopPropagation();
    // bind outerclick event
    $("body").bind("click.inplace-edit", function(event){
        if (!$(event.target).is(".plan-title-edit"))
        {
            txt.text(edit.val());
            edit.hide();
            txt.show();
            $("body").unbind("click.inplace-edit");
        }
    });
});

function getRenewalOptions()
{
    return "<option>xx</option><option>yy</option>";
}

jQuery(document).ready(function($) {
    $(".billing-plan").billingPlan();
    
    $("input[name='start_date_fixed']").prop("disabled", "disabled");
    $("select[name='renewal_group']").prop("id", "renewal_group").after($("<span><a href='javascript:' id='add-renewal-group'>add group</a></span>"));

    $("select[name='renewal_group']").change(function(){
        $(this).toggle($(this).find('option').length > 1);
    }).change();
    
    $("#start-date-edit").magicSelect({
        callbackTitle : function(option) {
            ret = option.text;
            if (option.value == 'fixed') 
            {
                var el = $("input[name='start_date_fixed']");
                var html = $("<p></p>").append(el.clone()
                    .prop("disabled", "").show()
                    .prop("id", "start_date_fixed")
                    .removeClass('hasDatepicker')
                ).html();
                ret += "&nbsp;" + html;
            } 
            return ret;
        }
    });
    
    $("a#add-renewal-group").live('click', function(){
        var ret = prompt("Enter title for your new renewal group, for example: group#1", "");
        if (!ret) return;
        var $sel = $("select#renewal_group").append(
            $("<option></option>").val(ret).html(ret));
        $sel.val(ret).change();
    });
    
    $("input[name='start_date_fixed']").live('focus', function(){
        if ($(this).hasClass('hasDatepicker')) return;
        $(this).datepicker({
                defaultDate: window.uiDefaultDate,
                dateFormat: window.uiDateFormat,
                changeMonth: true,
                changeYear: true
        });
    });

    function serializeForm()
    {
        var arr = $("form#Am_Form_Admin_Product").serializeArray();
        var settings = {};
        for (k in arr) settings[ arr[k].name ] = arr[k].value;
        return settings;
    }
});
