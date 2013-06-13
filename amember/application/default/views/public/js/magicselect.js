
/*
 * MagicSelect
 *
 * just another viewpoint on view of multiselect
 * this plugin should be used with select-multiple elements only
 *
 * @param String selectOffer
 * @param String deleteTitle
 * @param Function callbackTitle
 * 
 *
 */

;(function($) {
$.valHooks['__magic_select_saved'] = $.valHooks['select']; // save original handler
$.valHooks['select'] = {
    get : function(el, val) {
        if (!$(el).hasClass("magicselect"))
            return $.valHooks['__magic_select_saved'].get(el, val);
        return el._getMagicValue();
    },
    set : function(el, val)
    {
        if (!$(el).hasClass("magicselect"))
            return $.valHooks['__magic_select_saved'].set(el, val);
        throw "$magicSelect.val(set) is not yet implemented"
    }
};

$.fn.magicSelect = function(inParam) {
    return this.each(function(){
        var magicSelect = this;
        if ($(magicSelect).data('initialized')) {
            return;
        } else {
            if (this.type != 'select-multiple') {
                throw new Error('Element should be multiselect to use magicselect for it');
            }
            $(magicSelect).data('initialized', 1); 
        }
        $(magicSelect).data('params', inParam); //store it to use in restore function

        magicSelect._getMagicValue = function()
        {
            var $p = $(this.parentNode);
            var val = [];
            $(".magicselect-item input[type=hidden]", $p).each(function(){
                val.push(this.value);
            });
            return val;
        }
        magicSelect._setMagicValue = function(val)
        {
            
        }

        var selectOffer;
        if (!(selectOffer = $(magicSelect).data('offer')))
              selectOffer = '-- Please Select --';
          
        var param = $.extend({
            selectOffer : selectOffer,
            getOptionName : function(name, /* Option */ option) {return name},
            getOptionValue : function(/* Option */ option) {return $(option).val()},
            onOptionAdded : function(context, /* Option */ option) {},
            deleteTitle : 'x',
            onChange : function(val){},
            callbackTitle : function(/* Option */ option) {
                return $(option).data('label') ? $(option).data('label') : option.text;
            }
        }, inParam)

        var selectedOptions = new Object();

        $(magicSelect).wrap('<div></div>');

        if (param.sortable) {
            $(magicSelect).parent().sortable({ items: 'div' });
        }
        //this function shoud be used for update current select from thrty-part code
        //@param options object value => title
        magicSelect.update = function (options) {
            $(magicSelect).empty();
            var option = $('<option></option>');
            $(magicSelect).append( option.clone().append(param.selectOffer).val('__special__offer') );
            $.each(options, function(index, value){
                $(magicSelect).append(
                    option.clone().attr('value', index).append(value)
                )
            })
            $(magicSelect).nextAll().remove();
            $.each(selectedOptions, function(){
                var $option = $("option[value=" + this + "]", $(magicSelect));
                if ($option.get(0)) {
                    addSelected($option.get(0), true);
                }
            })
        }

        $(magicSelect).data('name', $(magicSelect).attr('name'));
        $(magicSelect).attr('data-name', $(magicSelect).attr('name'));
        $(magicSelect).prepend( $('<option></option>').attr('value', '__special__offer').append(param.selectOffer) )

        var options = [];
        $.each(this.options, function(){
            options.push(this);
        })

        if (param.sortable) {
            options = options.sort(function(a,b){
                if (parseInt($(a).data('sort_order')) < parseInt($(b).data('sort_order'))) {
                    return 1;
                } else if (parseInt($(a).data('sort_order')) == parseInt($(b).data('sort_order'))) {
                    return 0;
                } else {
                    return -1;
                }
            })
        }

        $.each(options, function(){
            addSelected(this);
        });

        $(magicSelect).removeAttr('multiple');
        magicSelect.selectedIndex = null;

        $(magicSelect).attr('name', '');

        $(magicSelect).change(function(){
            selectedOption = this.options[this.selectedIndex];
            //we use prefix __special__ for options that can not be selected
            //but used in some another way eg. 'Please Select' and 'Upload File'
            if (selectedOption.value.substring(0, 11) == '__special__') {
                return;
            }
            addSelected(selectedOption);
            magicSelect.selectedIndex = null;
        })

        function addSelected(option, fromStored) {
            if (option.selected || fromStored) {
               selectedOptions[option.value] = option.value;

               $(option).attr('disabled', 'disabled');

               var $optionCurrent = $(option);

               var a = $('<a></a>');
               a.attr('href', 'javascript:;');
               a.append(param.deleteTitle);
               a.css({'textDecoration' : 'none'});
               a.click(function(){
                   $optionCurrent.prop('disabled', '');
                   delete selectedOptions[$optionCurrent.val()];
                   param.onChange.call($(magicSelect), selectedOptions);
                   $(this).parent().remove();
                   $(magicSelect).trigger("change");
               })

               var input = $('<input></input>');
               input.attr('type', 'hidden');

               input.attr('name', param.getOptionName($(magicSelect).data('name'), option));
               input.attr('value', param.getOptionValue(option));

               var div = $('<div></div>');
               div.addClass(param.sortable ? 'magicselect-item-sortable' : 'magicselect-item')
               div.append('[');
               div.append(a);
               div.append('] ')
               div.append(param.callbackTitle(option));
               div.append(input);

               param.onOptionAdded(div, option);

               $(magicSelect).parent().append(div);
               param.onChange.call($(magicSelect), selectedOptions);
            }
        }
    })
}
//$("select.magicselect").magicSelect();
})(jQuery);

;(function($) {
$.fn.restoreMagicSelect = function() {
    return this.each(function(){
        var magicSelect = this;
        var params = $(magicSelect).data('params') || {};
        var name = $(magicSelect).attr('data-name');
        var $wrapper = $(magicSelect).closest('div');
  
        var $select = $wrapper.find('select'); 
        var $newselect = $('<select></select>');
        
        var attributes = ['id', 'data-offer', 'data-type', 'class'];
        $.each(attributes, function(k, v) {
            $newselect.attr(v, $select.attr(v));
        });
        
        $newselect.attr({
            'name' : name,
            'multiple' : 'multiple'
        });
        $select.find('option').each(function(k, el){
            if (el.value.substring(0, 11) != '__special__') {
                $newselect.append(el);
            }
        })
        $wrapper.find('input[type=hidden]').each(function(k, el){
           $newselect.find('[value=' + el.value + ']').attr('selected', 'selected');
        })
        $wrapper.after($newselect);
        $wrapper.remove();
        $newselect.magicSelect(params);
    })
}
})(jQuery);