
/*
 * Options Editor
 *
 */

;
(function($) {
    $.fn.optionsEditor = function(inParam) {
        return this.each(function(){
            var optionsEditor = this;
            var $optionsEditor = $(optionsEditor);
            var Options;
            var $input_value, $input_label, $input_default;
            var $tr = $('<tr></tr>');
            var $td = $('<td></td>');
            var $th = $('<th></th>');

            if ($(optionsEditor).data('initialized')) {
                return;
            } else {
                if (this.type != 'hidden') {
                    throw new Error('Element should be hidden in order to use optionsEditor for it. [' + this.type + '] given.');
                }
                $(optionsEditor).data('initialized', 1);
            }

            var param = $.extend({
                }, inParam)


            init();
            

            function updateOrder(newOrder) {
                var newOptions = new Object();
                for (var key in newOrder) {
                    newOptions[newOrder[key]] = Options.options[newOrder[key]];
                }
                Options.options = newOptions;
                $optionsEditor.val($.toJSON(Options));
            }

            function getRandomInt(min, max)
            {
              return Math.floor(Math.random() * (max - min + 1)) + min;
            }

            function addNewOption(key, val, is_default) {
                Options.options[key] = val;
                if (is_default && $.inArray(key, Options['default']) == -1) {
                    Options['default'].push(key);
                }

                $optionsEditor.val($.toJSON(Options))

                var $del = $('<a href="javascript:;">x</a>').click(function(event) {
                    var $tr = $(this).parents('tr');
                    var key = $tr.data('key');
                    delete Options.options[key];
                    var index = $.inArray(key, Options['default']);
                    if (index != -1) {
                        Options['default'].splice(index, 1);
                    }
                    $optionsEditor.val($.toJSON(Options))
                    $tr.remove();
                    $optionsEditor.closest('div').find('.options-editor table tbody').sortable('refresh');
                    return false;

                });

                var $last_td =  $td.clone().append(
                        $del
                    ).attr({'align':'center'});

                var $checkbox = $('<input type="checkbox" />');
                $checkbox.get(0).checked = is_default;

                var $added_tr = $tr.clone().append(
                    $td.clone().append(
                        $checkbox
                        )
                    ).append(
                    $td.clone().append(key)
                    ).append(
                    $td.clone().
                        append(
                          '<div class="editable"></div>'
                        ).
                        append('<span></span>').click(function(event){
                        if ($(this).hasClass('opened')) return;
                        $(this).addClass('opened');
                        var val = $(this).find('span').text();
                        $input = $('<input type="text" />').val(val);
                        $(this).find('span').hide();
                        $(this).find('div').hide();
                        $(this).append(
                            $input
                            )
                        $input.get(0).focus();

                        //bind to 'outerClick' event with small delay
                        //to prevent trigger during current event
                        setTimeout(function(){
                            $input.bind("outerClick keydown", function(event){
                                //use this event only for Enter (0xD)
                                if (event.type == 'keydown' && event.keyCode != 0xD) return;
                                var _buffer = $(this).val();
                                $(this).parent().find('span').text(_buffer).show();
                                $(this).parent().find('div').show();
                                $(this).parent().removeClass('opened');
                                Options.options[$added_tr.data('key')] = _buffer;
                                $optionsEditor.val($.toJSON(Options));
                                $(this).remove();
                            });
                        }, 5);
                    }).find('span').text(val).closest('td')
                    ).append(
                    $last_td
                    ).addClass('option');

                var id = 'option-editor-item-' + (new Date).getSeconds() + '-' + getRandomInt(1,100);
                $added_tr.prop('id', id);

                $checkbox.click(function(){
                    var index = $.inArray($added_tr.data('key'), Options['default']);
                    if (this.checked && index == -1) {
                        Options['default'].push($added_tr.data('key'));
                    }

                    if (!this.checked && index != -1) {
                        Options['default'].splice(index, 1);
                    }
                    $optionsEditor.val($.toJSON(Options));

                })

                $optionsEditor.parent().find('tr.new-option').before(
                    $added_tr
                    )
                    
                $added_tr.data('key', key);

                $del.before('[');
                $del.after(']')

                resetForm();
                $optionsEditor.closest('div').find('.options-editor table tbody').sortable('refresh');

            }

            function validateForm(value, label, is_default) {
                if (!value) {
                    return 'Value is requred';
                }

                if (value in Options.options) {
                    return 'Value should be unique';
                }

                return '';
            }

            function resetForm() {
                $input_value.val('');
                $input_label.val('');
                $input_default.get(0).checked = false;

            }

            function init() {
                Options = $.parseJSON($(optionsEditor).val());
                if ($.isArray(Options.options)) {
                    temp = new Object();
                    for(var i=0; i<Options.options.length; i++)
                    	temp[i]=Options.options[i];
                    Options.options = temp;
                }

                $table = $('<table></table>');

                $new_tr = $tr.clone();

                $input_label = $('<input type="text" />');
                $input_value = $('<input type="text" />').attr('size', 5);
                $input_default = $('<input type="checkbox" />');

                $th_tr = $tr.clone();
                $th_tr.append(
                    $th.clone().append('Def').attr('title', 'Is Default?')
                    ).append(
                    $th.clone().append('Value')
                    ).append(
                    $th.clone().append('Label')
                    ).append(
                    $th.clone().append('&nbsp;')
                    )


                $table.append(
                    $th_tr
                    ).append(
                    $new_tr.addClass('new-option').append(
                        $td.clone().append(
                            $input_default
                            )
                        ).append(
                        $td.clone().append(
                            $input_value
                            )
                        ).append(
                        $td.clone().append(
                            $input_label
                            )
                        ).append(
                        $td.clone().append(
                                $('<a href="javascript:;" class="button">+</a>').click(function(event) {
                                    if (error = validateForm($input_value.val(), $input_label.val(), $input_default.get(0).checked)) {
                                        alert(error);
                                    } else {
                                        addNewOption($input_value.val(), $input_label.val(), $input_default.get(0).checked)
                                    }
                                    return false;

                                })
                            )
                        )
                    )

                $optionsEditor.before($table);
                
                var $div = $('<div></div>').addClass('options-editor');
                $table.wrap($div);
                $optionsEditor.hide();
                for (var key in Options.options) {
                    addNewOption(key, Options.options[key], $.inArray(key, Options['default']) != -1);
                }
                
                $optionsEditor.val($.toJSON(Options));

                $optionsEditor.closest('div').find('.options-editor table tbody').
                    sortable({
                    items: 'tr.option',
                    stop : function(event, ui) {
                        var newOrder = [];
                        var arr = $optionsEditor.closest('div').find('.options-editor table tbody').sortable('toArray');
                        for (var key in arr) {
                            newOrder.push($('#' + arr[key]).data('key'));
                        }
                        updateOrder(newOrder);
                    }

                });
            }
           
        })
    }
})(jQuery);