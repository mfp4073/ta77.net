var bricksEditor = {
    hidden: null,
    enabled: null,
    disabled: null,
    
    update: function()
    {
        var val = [];
        $("#bricks-enabled .brick").each(function(idx, el){
            var $el = $(el);
            ret = {'id': $el.attr("id"), "class" : $el.data("class")};
            if ($el.data("config")) ret.config = $el.data("config");
            if ($el.data("hide")) ret.hide = $el.data("hide");
            if ($el.data("labels")) ret.labels = $el.data("labels");
            val.push(ret);
        });
        this.hidden.val($.toJSON(val));
    },
    
    // methods
    init: function() {
        this.hidden = $('input[name="fields"]', $('#bricks-enabled').parents('form'));
        this.enabled = $('#bricks-enabled');
        this.disabled = $('#bricks-disabled');
        
        this.update();
    },
    getBrickConfigDiv: function(id) {
        return $('div#config_'+id);
    },
    showConfigDialog: function (brickDiv)
    {
        var configId = "#brick-config-" +  brickDiv[0].id;
        $(configId).dialog({
            modal: true,
            autoOpen : false,
            width: Math.round($(window).width() * 0.7),
            height: Math.round($(window).height() * 0.7),
            buttons: {
                "Ok": function(event) {
                    $(brickDiv).data("config", $(configId + " form :input").not("[name='_save_']").serialize());
                    bricksEditor.update();
                    flashMessage("Configuration updated successfully. Information will be saved to database once you press 'Enter' in main form.");
                    $(this).dialog("close").dialog("destroy");
                 },
                "Cancel": function(event) {
                    $(this).dialog("close").dialog("destroy");
                }
            }
        }).dialog("open");
    },
    showLabelDialog: function(brickDiv)
    {
        var frm = $("#brick-labels").clone().attr('id', 'brick-labels-live');
        frm.appendTo('body');
        // get current labels
        var stdlabels = brickDiv.data('stdlabels');
        var labels = brickDiv.data('labels');
        var txt = frm.find("textarea");
        var row = txt.closest(".row");
        for (i in stdlabels)
        {
            var newRow = txt.closest(".row").clone();
            var input = newRow.find("textarea");
            input.attr({
                id: 'txt-' + i,
                name: i,
                size: 60
            }).data("stdlabel", stdlabels[i]).text(labels[i] ? labels[i] : stdlabels[i]);
            if (labels[i] && (labels[i] != stdlabels[i]))
                input.addClass('changed');
            input.change(function(event){
                $(this).addClass('changed');
            });
            if (labels[i]) input.addClass("custom-label");
            newRow.find(".element-title").text(i);
            row.after(newRow);
        }
        row.remove();
        frm.dialog({
            modal: true,
            autoOpen : false,
            width: Math.round($(window).width() * 0.7),
            height: Math.round($(window).height() * 0.7),
            buttons: {
                "Ok": function(event) {
                    var labels = {}; 
                    $.each( 
                        $("textarea.changed", frm).serializeArray(), 
                        function(id, el){ labels[el.name] = el.value; }
                    );
                    brickDiv.data('labels', labels);
                    bricksEditor.update();
                    flashMessage("Configuration updated successfully. Information will be saved to database once you press 'Enter' in main form.");
                    $(this).dialog("close").dialog("destroy");
                    frm.remove();
                 },
                "Cancel": function(event) {
                    $(this).dialog("close").dialog("destroy");
                    frm.remove();
                }
            }
        }).dialog("open");
    }
    
};


jQuery(document).ready(function($) {
    bricksEditor.init();
    $("#bricks-enabled, #bricks-disabled").sortable({
        connectWith: '.connectedSortable'
    }).disableSelection();
    $( "#bricks-enabled" ).bind( "sortreceive", function(event, ui) 
    {
        var el = $(ui.item[0]);
        var oldId = ui.item[0].id;
        var match;
        if (match = el.attr('id').match(/^(.+)-(\d+)$/))
        {
            var cl = el.data('class') // say PageSeparator
            var origI = +match[2]; // say 0
            var i = origI;
            do {
                i++;
            } while ($("#"+cl+"-"+i).length);
            // rename moved el to new Id
            var newId = cl + "-" + i;
            el.attr("id", newId);
            // insert cloned element to original position
            var newEl = el.clone().attr("id", oldId);
            $("#bricks-disabled").append( newEl );
            // now clear config form if any
            var frm = $("#brick-config-"+ oldId);
            var newFrm = frm.clone().attr("id", "brick-config-"+newId) 
            frm.after(newFrm);
            newFrm.find('.magicselect').restoreMagicSelect();
        }
    });
    $( "#bricks-enabled" ).bind( "sortremove", function(event, ui) {
        var el = $(ui.item[0]);
        if (el.data('multiple'))
        {
            $(ui.sender).sortable("cancel");
            $("#brick-config-" + el.attr("id")).remove();
            ui.item.remove();
        }
    });
    $( "#bricks-enabled" ).bind( "sortupdate", function(event, ui) {
        bricksEditor.update();
    });
    $("#bricks-enabled").live('dblclick', function(event){
        bricksEditor.showConfigDialog($(event.target).closest(".brick"));
    });
    $("#bricks-enabled a.configure").live('click', function(event){
        bricksEditor.showConfigDialog($(event.target).closest('.brick'));
    });
    $("#bricks-enabled a.labels").live('click', function(event){
        bricksEditor.showLabelDialog($(event.target).closest('.brick'));
    });
    $(".hide-if-logged-in input[type='checkbox']").click(function()
    {
        $(this).closest(".brick").data("hide", this.checked ? "1" : "0");
        bricksEditor.update();
    });
});
