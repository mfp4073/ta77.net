/*
 * Folder Access editor JS code
 * @author Alex Scott alex@cgi-central.net
 */
// todo:
//  - init start/stop values
(function( $ ){

var methods = {
    init : function( options ) {
    return this.each(function(){
        var $this = $(this),
            data = $this.data('resourceaccess');
        if (data && data.initialized) return;
        $this.data('resourceaccess', {
            options: $.extend({
                without_period: false
            }, options)
            ,initialized : true
        });
        // free access handling
        $(".free-switch a").click(function(){
            var sw = $(this).data("access");
            
            $(".free-switch").hide();
            $(".free-switch."+sw+"-access").show();
            
            switch (sw)
            {
                case 'protected':
                    $this.resourceaccess("removeItem", 'free_without_login', 0);
                    $this.resourceaccess("removeItem", 'free', 0);
                    break;
                case 'free':
                    $this.resourceaccess("addItem", 'free', 'Free', 
                        0, "", "", "");
                    $this.resourceaccess("removeItem", 'free_without_login', 0);
                    break;
                case 'free_without_login':
                    $this.resourceaccess("addItem", 'free_without_login', 'Free, login not required', 
                            0, "", "", "");
                    $this.resourceaccess("removeItem", 'free', 0);
                    break;
            }
        });

        // init items
        var items = $.parseJSON($(".resourceaccess-init", $this).val());
        var select = $(".access-items", $this);
        if (items)
            for (cl in items)
            {
                var clText = $("optgroup."+cl, select).data('text');
                for (id in items[cl])
                {
                    var item = items[cl][id];
                    $this.resourceaccess("addItem", cl, clText, 
                        id, item.text, item.start, item.stop);
                }
                if (cl == 'free')
                {
                    $(".protected-access, .free-access", $this).toggle();
                    break;
                }
                if (cl == 'free_without_login')
                {
                    $(".protected-access, .free_without_login-access", $this).toggle();
                    break;
                }
            }
    
        // If the plugin hasn't been initialized yet
        $(".access-items", $this).change(function()
        {
            if (this.selectedIndex <= 0) return;
            var option = this.options[this.selectedIndex];
            var gr = $(option).closest("optgroup");
            $this.resourceaccess("addItem", gr.attr("class"), gr.data("text"), 
                option.value, option.text, null, null);
        });
    });
    }
    ,removeItem: function(cl, id) {
        var hiddenName = this.attr("id") + "["+cl+"]["+id+"]";
        $("input[type='hidden'][name='"+hiddenName+"']").closest(".item").remove();
    }
    ,addItem: function(cl, clText, id, text, start, stop)
    {
        var el = $("<div class='item'>");
        el.html(" <i>" + clText + "</i> <b>" + text + "</b>");
        var x = $("<a href='javascript:'>[X]</a>");
        x.click(function(){
            el.remove();
            $(".access-items optgroup."+cl+" option[value='"+id+"']").prop("disabled", false);
            // todo - enable option
        });
        el.prepend(x);
        $(".access-items optgroup."+cl+" option[value='"+id+"']").prop("disabled", true);
        var elid = this.attr("id");
        var hidden = $("<input type='hidden' name='"+elid+"["+cl+"]["+id+"]' />")
        hidden.val($.toJSON({
             start : start
            ,stop  : stop
            ,text  : text
        }));
        el.append(hidden);

        if (!cl.match(/^free/) && !this.data('resourceaccess').options.without_period)
        {
            var astart = $("<a href='javascript:'></a>");
            astart.text($(this).resourceaccess("getLinkText",start, true))
            .click(function(event){
                event.stopPropagation();
                methods.openEditor($(this), true, el);
            });
            var astop = $("<a href='javascript:'></a>");
            astop.text($(this).resourceaccess("getLinkText",stop, false))
            .click(function(event){
                event.stopPropagation();
                methods.openEditor($(this), false, el);
            });
            el.append("&nbsp;from ").append(astart).append(" to ").append(astop);
        }
        $("."+cl+"-list", this).append(el);
        return el;
    }
    ,getLinkText: function(v, isStart)
    {
            switch (true)
            {
                case v == '-1d' : return 'forever';
                case v == 0 :
                case v == '' :
                case v == null:
                    return isStart ? 'start' : 'expiration';
                default:
                    return v;
            }
    }
    // open period editor
    ,openEditor : function(a, isStart, div)
    {
        var text = $("<input type='text' id='resourceaccess-count' size=3 maxlength=5>");
        var select = $("<select id='resourceaccess-unit' size=1></select>");
        select.append(new Option(isStart ? 'start' : 'expiration', ''));
        select.append(new Option('-th day', 'd'));
        if (!isStart)
            select.append(new Option('forever', '-1d'));
        var span = $("<span class='resourceaccess-edit' style='font-size: 8pt;' />");
        span.append(text);
        span.append(select);
        var hidden = div.find("input[type='hidden']");
        span.bind("outerClick", function(){
            var val = $.evalJSON(hidden.val());
            var t = null, v = select.val();
            if (v == 'd') v = parseInt(text.val()) + v;
            if (select.val() != 'd')
                t = select[0].options[select[0].selectedIndex].text;
            else
                t = v;
            if (isStart)
                val.start = v;
            else
                val.stop = v;
            hidden.val($.toJSON(val));
            a.text(t).show();
            span.remove();
        });
        var val = $.evalJSON(hidden.val());
        var v = isStart ? val.start : val.stop;
        if (parseInt(v) > 0)
        {
            text.val(parseInt(v));
            select.val('d');
        } else {
            select.val(v);
            text.hide();
        }
        select.change(function(){
            text.toggle($(this).val() == 'd');
        });
        a.hide().after(span);
    }
};
$.fn.resourceaccess = function( method ) {
    if ( methods[method] ) {
      return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
      return methods.init.apply( this, arguments );
    } else {
      $.error( 'Method ' +  method + ' does not exist on jQuery.resourceaccess' );
    }    
};

})( jQuery );
