<style>    

body{
    font: 13px/1.231 arial,helvetica,clean,sans-serif;        
}
.am-layout {
    background: none;
}

.am-body {
    background: none;
}
.am-body-content-wrapper {
    border: none;
    background: inherit;
}
.am-footer {
    color: black;
    
}
.am-footer-content-wrapper {
    background: inherit;
    color: black;
    height: 30px;
}
.am-footer-content a {
    color: black;
}

.am-main {
    display: block;
    padding-left: 20px;
    padding-right: 20px;
    width: auto;
}

.am-form fieldset {
    border:0;
    padding : 0;
    margin: 0;
    float:none;
}

</style>    
        <div class="am-layout">
            <a name="top"></a>

            <div class="am-body">
                <div class="am-body-content-wrapper am-main">
                    <div class="am-body-content">
                        <?php echo $this->blocks('content/before'); ?>
                        <?php include $this->_script('_top.phtml'); ?>
                        <?php if (empty($this->layoutNoTitle)): ?>
                            <h1><?php echo $title ?></h1>
                        <?php endif; ?>
                        <!-- content starts here -->
                        <?php echo $content ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="am-footer">
            <div class="am-footer-content-wrapper am-main">
                <div class="am-footer-content">
                    <div class="am-footer-actions">
                        <a href="#top"><img src="<?php echo $this->_scriptImg('/top.png') ?>" /></a>
                    </div>
                    aMember Pro&trade; by <a href="http://www.amember.com">aMember.com</a>  &copy; 2002&ndash;<?php echo date('Y')?> CGI-Central.Net
                </div>
            </div>
        </div>
<br/>
<?php include $this->_script('_popup.phtml'); ?>
