//
// This plugin implements the handling of Upload
// works together with AdminUploadController
//
(function( $ ){
    var methods = {
        init : function( options ) {
            return this.each(function(){
                var $this = $(this);
                if ($this.data('upload')) return; // the plugin has been initialized already               

                var params = $.extend({
                    onChange : function(filesCount) {},
                    onFileAdd : function(info) {},
                    onFileDraw : function(info) {},
                    onSelect : function(){},
                    onSubmit : function(){},
                    fileMime : false, // A list of file MIME types that are allowed for upload.
                    fileBrowser : true, //whether init file browser
                    urlUpload : '/admin-upload/upload',
                    urlGet : '/admin-upload/get'
                }, options);

                $this.
                data('params', params).
                data('upload', 1);

                var name = $this.attr('name');
                var end = name.substr(name.length-2, 2);
                var info = $this.upload('info');
                if (end=='[]') {
                    $this.data('multiple', 1);
                }
                    
                $this.upload('drawUpload');

                if ($this.attr('value')) {
                    if ($this.data('multiple')) {
                        var values = $this.attr('value').split(',');
                        for (var i=0; i<values.length; i++) {
                            $this.upload('drawFile', info[values[i]])
                        }
                    } else {
                        $(this).upload('drawFile', info[$this.attr('value')]);
                    }
                }
                $this.hide();
                $this.attr('disabled', 'disabled');
                $this.data('params').onChange.call($this, $this.upload('count'));

            });
        }
        ,
        increaseCount : function() {
            this.data('count', this.upload('count')+1);

            //in order to JS validation works
            if (this.upload('count') == 1) {
                //remove error message of JS validation
                this.parent().find('.error').not('input').remove();
            }
        }
        ,
        decreaseCount : function() {
            this.data('count', this.upload('count')-1);
        }
        ,
        count : function count() {
            return this.data('count') ? this.data('count') : 0;
        }
        ,
        drawFile : function(info) {
            var $this = this;

            $this.upload('destroyUploader');
            var $a = $('<a href="javascript:;">X</a>');
            var $div = $('<div></div>');
            var $aFile = $('<a></a>');
            $aFile.attr('href', window.rootUrl + $this.data('params').urlGet + '?id=' + info.upload_id.toString().split('|', 2)[0]).
            attr('target', '_top');

            $this.before(
                $div.append($aFile.append(info.name)).append(' (' + info.size_readable + ')'
                    ).append(' [').append($a).append(']').append(
                    $('<input type="hidden" />').
                    attr('name', $this.attr('name')).
                    attr('value', info.upload_id)
                    ));
            $a.click(function(){
                $(this).closest('div').remove();
                $this.upload('decreaseCount');
                $this.upload('destroyUploader');
                $this.upload('drawUpload');
                $this.data('params').onChange.call($this, $this.upload('count'));
            })
            $this.data('params').onFileDraw.call($this, info);
            $this.upload('increaseCount');
            $this.upload('drawUpload');
        }
        ,
        drawUpload : function(){
            var $this = this;

            $this.upload('destroyUploader');
            if (!$this.data('multiple') && $this.upload('count')) {
                return;
            }
            var $a = ($this.data('params').fileBrowser ? $('<div class="upload-control-browse"><span>browse</span></div>') : '');
            var $wrapper = $('<div class="upload-control"></div>');
            if ($this.upload('count')) {
                $wrapper.css('margin-top', '1em');
            }
            var $uploader = $this.upload('getUploader');
            $this.before(
                $wrapper.append(
                    $uploader
                    ).append($a)
                );
            $this.data('params').fileBrowser && $a.before(' ');
            var $div = $('<div></div>');
            $('body').append($div);
            $div.hide();
            $div.addClass('filesmanager-container');
            //so grid can update this
            $div.get(0).uploader = $this;
            if ($this.data('params').fileBrowser) {
                $a.click(function(){
                    $div.dialog({
                        modal : true,
                        title : "Uploaded Files",
                        width : 800,
                        height: 600,
                        position : ['center', 100],
                        buttons : {
                            Cancel : function(){
                                $(this).dialog("close")
                            }
                        },
                        open : function(){
                            $.get(window.rootUrl + '/admin-upload/grid', {
                                prefix: $this.data('prefix'),
                                secure: $this.data('secure')
                            }, function(data, textStatus, jqXHR){
                                $div.empty().append(data);
                                $(".grid-wrap").ngrid();
                            })
                        },
                        close : function() {
                            $div.empty();
                            $div.remove();
                        }
                    });
                });


                $a.bind('mouseover mouseout', function(){
                    $a.toggleClass('hover')
                })
            }
            
            $this.upload('initUploader', $uploader);
        }
        ,
        addFile: function(info) {
            var $this = this;
            
            if (!info.ok) {
                alert('Error: ' + info.error);
                $this.upload('drawUpload');
                return;
            } else if ($this.data('params').fileMime
                && $.inArray(info.mime, $this.data('params').fileMime) == -1) {

                alert('Incorrect file type : ' +
                    info.mime +
                    '. Expect one of: ' +
                    $this.data('params').fileMime.join(', '));
                $this.upload('drawUpload');
                return;
            }
            $(this).upload('drawFile', info);
            $this.data('params').onChange.call($this, $this.upload('count'));
            $this.data('params').onFileAdd.call($this, info);
        }
        ,
        info: function() {
            return this.data("info");
        }
        ,
        destroyUploader : function () {
            var $this = this;

            $this.closest('div').find('div.upload-control').remove();
            $('#uploader-iframe-' + $this.attr('id')).remove();
            $('#uploader-form-' + $this.attr('id')).remove();
        }
        ,
        getUploader : function () {
            var $this = this;
            var aUpload = $('<span>upload</span>');
            var $uploader = $('<div class="upload-control-upload"></div>').css({
                display: 'inline-block',
                overflow: 'hidden',
                'float':'left'
            }).append(aUpload);
            !$this.data('params').fileBrowser && $uploader.addClass('upload-control-upload-single');
            return $uploader;
        }
        ,
        initUploader : function($uploader) {
            var $this = this;

            var uploaderId = $this.attr('id');

            var $input = $('<input type="file" />').attr('name', 'upload');
            var $form = $('<form></form>').attr({
                method : 'post',
                enctype : 'multipart/form-data',
                action : window.rootUrl + $this.data('params').urlUpload,
                target :  'uploader-iframe-' + uploaderId,
                id : 'uploader-form-' + uploaderId
            }).css({
                margin: 0,
                padding: 0
            })

            var $input_hidden = $('<input />').attr({
                name : 'prefix',
                value : $this.data('prefix'),
                type : 'hidden'
            })

            var $input_hidden_secure = $('<input />').attr({
                name : 'secure',
                value : $this.data('secure'),
                type : 'hidden'
            })

            $form.append($input).append($input_hidden).append($input_hidden_secure);

            var $frame = $('<iframe></iframe>').attr({
                name : 'uploader-iframe-' + uploaderId,
                id : 'uploader-iframe-' + uploaderId
            });

            $('body').append($form);
            $('body').append($frame);
            $frame.hide();

            var $div = $input.wrap('<div></div>').parent().css({
                overflow : 'hidden',
                width : $uploader.outerWidth(),
                height : $uploader.outerHeight()
            }).css({
                position : 'absolute',
                'z-index' : 10000
            });

            //allow some time to colculate size for $uploader
            setTimeout(function(){
                $div.css({
                    width : $uploader.outerWidth(),
                    height : $uploader.outerHeight()
                })
            }, 100)

            $input.css({
                'float':'right'
            });
            $div.css({
                opacity: 0
            });

            $input.bind('mouseover mouseout', function(){
                $uploader.toggleClass('hover')
            })

            $uploader.mousemove(function(e){
                $div.css({
                    top: $uploader.offset().top+'px',
                    left: $uploader.offset().left+'px'
                });
            });

            $input.change(function() {
                $this.data('params').onSelect.call($this);

                $this.data('params').onSubmit.call($this);

                $uploader.find('span').empty().append('Uploading...').addClass('uploading')

                $form.submit();

                $frame.load(function() {
                    var frame = document.getElementById($frame.attr('id'));
                    var response = $(frame.contentWindow.document.body).text();
                    //console.log(response);
                    try {
                        response = $.parseJSON(response);
                    } catch (e) {
                        response = {
                            ok : false,
                            error : 'Error of file uploading on server side'
                        };
                    }
                    //console.log(response);
                    //allow to complete 'load' event up to the end
                    //before remove this element
                    setTimeout(function(){
                        $this.upload('addFile', response);
                    }, 10);
                });

            });
        }

    };

    $.fn.upload = function( method ) {
        if ( methods[method] ) {
            return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
        } else if ( typeof method === 'object' || ! method ) {
            return methods.init.apply( this, arguments );
        } else {
            $.error( 'Method ' +  method + ' does not exist on jQuery.upload' );
        }
    };

})( jQuery );