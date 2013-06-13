// 
// This plugin implements the handling of AJAX table
//
(function( $ ){

var methods = {
    init : function( options ) {
    return this.each(function(){
        var $this = $(this),
            data = $this.data('ngrid');
        // If the plugin hasn't been initialized yet
        if (!data) {
            var id = $(this).attr("id").replace(/^grid-/, '');
            if (!id)
                throw "ngrid: no id specified for grid";
            // do initialization
            $(this).data('ngrid', {
                init   : true,
                id     : id,
                target : $this
            });
            $("a[href]:not([target])", $this).live("click.ngrid", function(event){
                if ((this.href == '#') || (this.href.match(/^javascript:/)))
                    return;
                $this.ngrid('reload', this.href);
                return false;
            });
            $("form:not([target])", $this).live("submit.ngrid", function(event){
                $(this).ajaxSubmit({
                    'context' : $this,
                    'cache' : false,
                    success: methods.onAjaxSuccess
                });
                return false;
            });
            
            $(":button[data-url]", $this).live("click.ngrid", function(event){
                if (!$(this).attr("data-url") || $(this).attr("data-target")) return;
                $this.ngrid('reload', $(this).attr("data-url"));
                return false;
            })
            
            $("input.group-action-checkbox", $this).live("change.ngrid", function(){
                $(this).closest("tr").toggleClass("selected", this.checked);
            });
            $("input.group-action-checkbox-all", $this).live("change.ngrid", function(){
                var list = $("input.group-action-checkbox", $this);
                this.checked ? 
                    list.attr("checked", "checked") : 
                    list.removeAttr("checked");
                list.trigger("change.ngrid");
                
                var info = $this.ngrid("info");
                if (info.totalRecords > list.length)
                {
                    if (this.checked)
                    {
                        $this.find("div.check-all-offer").show();
                    } else {
                        $this.ngrid('toggleCheckAll', false);
                    }
                }
            });
            $("a.check-all-offer-offer", $this).live("click.ngrid", function(){
                $this.ngrid('toggleCheckAll', true);
            });
            $("a.check-all-offer-cancel", $this).live("click.ngrid", function(){
                $this.ngrid('toggleCheckAll', false);
                $("input.group-action-checkbox-all").removeAttr("checked").trigger("change.ngrid");
            });
            $("td.expandable", $this).live("click.ngrid", methods.onExpandableClick);
            $("div.group-wrap select", $this).live('change.ngrid', function(){
                if (!this.selectedIndex) return; // empty item selected
                var val, ids="",url;
                if (val = $("input.group-action-checkbox-entire", $this).val())
                {
                    ids = val;
                } else 
                    $("input.group-action-checkbox", $this).each(function(i,el){
                        if (!el.checked) return;
                        if (ids) ids += ",";
                        ids += el.value;
                    });
                if (!ids)
                {
                    flashError("No rows selected for operation, please click on checkboxes, then repeat");
                    this.selectedIndex = null;
                    return false;
                }
                url = $(this.options[this.selectedIndex]).attr("data-url");
                target = $(this.options[this.selectedIndex]).attr("data-target");
                if (!url)
                    throw "ngrid: no url specified for action";
                if (ids)
                    url += '&' + escape('_' + $this.data('ngrid').id + '_group_id') + '=' + escape(ids);
                if (target)
                    window.location = url;
                else
                    $this.ngrid("reload", url);
            });
        }
        $this.trigger('load');
    });
    }
    ,toggleCheckAll : function(flag) {
        var $this = $(this);
        var container = $("input.group-action-checkbox-all", $this).parent();
        var input = $("input.group-action-checkbox-entire", container);
        if (flag)
        {
            input.val('[ALL]');
            $("div.check-all-offer-offer").hide();
            $("div.check-all-offer-selected").show();
        } else {
            input.val('');
            $("div.check-all-offer-offer").show();
            $("div.check-all-offer-selected").hide();
            $("div.check-all-offer").hide();
        }
    }
    ,reload : function(url, params) {
        var $this = $(this);
        var options = {
             cache: false
            ,context: $this
            ,target: $this
            ,url : url
            ,success: methods.onAjaxSuccess
        };
        if (params) options.data = params;
        $.ajax(options);
    }
    ,onAjaxSuccess: function(response, status, xhr, target) {
        var $this = $(this);
        if ((typeof(response) == 'object') && response['ngrid-redirect']) 
        {
            return $this.ngrid("reload", response['ngrid-redirect']);
        }
        $this.html(response);
    }
    // register a function to be executed on grid load (either normal or ajax)
    // it will be executed initially during onLoad call, and after each ajax reload
    ,onLoad: function(callback) {
        $(this).bind("load", callback);
        callback.apply(this);
    }
    ,info: function() {
        return $.parseJSON($(this).find("table.grid").attr("data-info"));
    },
    onExpandableClick: function(td) 
    {
        this.getText = function (dataDiv) 
        {
            //if (dataDiv.hasClass('isHtml')) return $(dataDiv).val();
            return filterHtml($(dataDiv).val());
        }
        
        this.close = function () {
            this.row.data('state', 'closed')
            this.row.next().remove();
            this.row.find('td').removeClass('expanded')
        }

        this.open = function () {
            this.row.data('state', 'opened')
            this.cell.data('openedByMe', 1)
            numOfCols = this.row.children().size();
            this.row.after('<tr><td colspan="' +
                numOfCols +
                '" class="expandable-data">' +
                this.getText(this.cell.find('.data')) +
                '</td></tr>');
            this.cell.addClass('expanded')
        }

        this.row  = $(this).parent()
        this.cell = $(this)
        this.isHtml = (this.cell.find('.data').hasClass('isHtml'));

        this.state      = this.row.data('state')
        this.openedByMe = this.cell.data('openedByMe')

        this.row.children().data('openedByMe', 0)

        if (this.state == 'opened'){
            this.close();
            if (!this.openedByMe)
                this.open()
        } else {
            this.open()
        }
        return false;
    }
};

$.fn.ngrid = function( method ) {
    if ( methods[method] ) {
      return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
      return methods.init.apply( this, arguments );
    } else {
      $.error( 'Method ' +  method + ' does not exist on jQuery.ngrid' );
    }    
};

})( jQuery );
