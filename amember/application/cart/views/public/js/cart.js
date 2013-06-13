/* Shopping cart javascript code */

var cart = {
    items: {}
    ,_detectRootUrl : function()
    {
        var t = document.getElementsByTagName("script");
        var js = t[ t.length - 1 ];
        if (js && js.src)
        {
            return js.src.replace(new RegExp('/application/cart/views/public/js/cart.js'), '');
        }
    }
    ,_goCategory: function(category_id)
    {
        window.location = 
            this._getUrl('index') + '?c=' + category_id;
    }
    ,_initCategorySelect : function() {
        jQuery("select#product-category").change(function(){
           cart._goCategory(jQuery(this).val());
        });
    }
    ,_getUrl: function(action)
    {
        return window.rootUrl + '/cart/index/' + action;
    }
    ,_getBillingPlanId: function(item_id)
    {
        return jQuery(":input[name='plan["+item_id+"]']").val();
    }
    ,_addOnly: function(data, callback, element)
    {
        this._showError(); // hide error message
        jQuery.post(
            cart._getUrl('ajax-add-only'),
            'data=' + JSON.stringify(data),
            function(response){
                if (response.status != 'ok') {
                    cart._showError(element, response.message);
                    cart.loadOnly();
                }
                else if (typeof callback == "function")
                        callback();
            }
        );
        return this;
    }
    ,_getObj: function(args)
    {
        var data = [];
        var elem = (args[0].nodeType) ? true : false;

        for (var key in args) {
            if (elem){
                elem = false;
                continue;
            }
            switch (typeof args[key]){
                case 'object':
                    if (typeof args[key][0] != 'undefined'){  // it's simple array
                        for (var k in args[key])
                            data.push({id:args[key][k]});
                    } else {    // it's object
                        data.push(args[key]);
                    }
                    break;
                case 'number':
                    // item_id only
                    data.push({id:args[key]});
                    break;
                case 'string':
                    // item_id only
                    var arr = args[0].split(',');
                    for (var k in arr)
                        data.push({id:arr[k]});
                    break;
            }
        }
        
        return data;
    }
    ,_showError: function(element, mess)
    {
        if (element && mess && mess.length > 0){
//            jQuery(element).parent().last().after('&nbsp;<span style="color:red"  id="am-cart-message-error">' + mess + '</span>');
            jQuery(element).parent().last().after('<div style="color:red" id="am-cart-message-error">' + mess + '</div>');
        } else {
            jQuery("#am-cart-message-error").remove();
        }
    }
    ,init : function()
    {
        if (!window.rootUrl) window.rootUrl = cart._detectRootUrl();
        var loadRunned = 0;
        if (typeof(jQuery) == 'undefined') {
            var jqueryUrl = window.rootUrl + "/application/default/views/public/js/jquery/jquery.js";
            if (! loadRunned++) 
            {
                document.write(x = "<scr" + "ipt type=\"text/javascript\" src=\""+jqueryUrl+"\"></scr" + "ipt>");
            }
            setTimeout("cart.init()", 200);
        } else {
            jQuery(function() {
                cart._initCategorySelect();
            });
        }
    }
    ,loadOnly: function()
    {
        var block = jQuery("#block-cart-basket").parents(".block");
        if (!block.length)
            block = jQuery("<div id='am-cart-basket-container'/>");
        block.load(this._getUrl('ajax-load-only'));
        return this;
    }
    ,add: function(th, item_id, qty, item_type)
    {
        if (arguments.length == 0)
            return cart.loadOnly();
        var callback = function(){
            cart.loadOnly();
        };
        var elem = (th.nodeType) ? th : false;
        cart._addOnly([{
            id : item_id
            ,qty : qty ? qty : 1
            ,type : item_type ? item_type : 'product'
            ,plan : cart._getBillingPlanId(item_id)
        }]
        , callback
        , elem);
        return this;
    }
    ,addAndCheckout: function(th, item_id, qty, item_type)
    {
        var callback = function(){
            window.location =
                cart._getUrl('add-and-checkout') +
                '?b=' + encodeURIComponent(window.location.pathname + window.location.search);
        };
        var elem = (th.nodeType) ? th : false;
        cart._addOnly([{
            id : item_id
            ,qty : qty ? qty : 1
            ,type : item_type ? item_type : 'product'
            ,plan : cart._getBillingPlanId(item_id)
        }]
        , callback
        , elem);
        return this;
    }
    ,addExternal: function()
    {
        var callback = function(){
            cart.loadOnly();
        };
        var elem = (arguments[0].nodeType) ? arguments[0] : false;
        cart._addOnly(cart._getObj(jQuery.makeArray(arguments)), callback, elem);
        return this;
    }
    ,addBasketExternal: function()
    {
        var callback = function(){
            window.location = cart._getUrl('view-basket') +
                '?b=' + encodeURIComponent(window.location.pathname + window.location.search);
        };
        var elem = (arguments[0].nodeType) ? arguments[0] : false;
        cart._addOnly(cart._getObj(jQuery.makeArray(arguments)), callback, elem);
        return this;
    }
    ,addCheckoutExternal: function()
    {
        var callback = function(){
            window.location = cart._getUrl('add-and-checkout');
        };
        var elem = (arguments[0].nodeType) ? arguments[0] : false;
        cart._addOnly(cart._getObj(jQuery.makeArray(arguments)), callback, elem);
        return this;
    }
};

cart.init();