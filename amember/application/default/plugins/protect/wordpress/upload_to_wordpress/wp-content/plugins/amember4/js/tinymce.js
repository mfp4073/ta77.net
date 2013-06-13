(function(){
tinymce.create('tinymce.plugins.am4plugin', {
    createControl: function(n, cm) {
        switch(n){
           case    "am4button" :
               var b = cm.createButton('am4button', {
                   title    :   'aMember shortcodes',
                   image    :   '../wp-content/plugins/amember4/img/lock.png',
                   cmd      :   "amInsertaMemberShortcodeWindow"
               })
               return b;
        }

        return null;
    },
    
    init : function(ed, url){
        ed.addCommand('amInsertaMemberShortcodeWindow', function(){
                            /////// Now open php file
                            var win = window.dialogArguments || opener || parent || top;
                            
                            r = ed.windowManager.open({
                                url     : url+'/../shortcode_select.php',
                                width   :   800,
                                height  :   500,
                                inline  :   1
                            });
                            
                            

        })
    }
});

// Register plugin with a short name
tinymce.PluginManager.add('am4plugin', tinymce.plugins.am4plugin);
})();
