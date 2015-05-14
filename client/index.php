<!DOCTYPE html>
<?php

    $file = file_get_contents('js/config.json');
    $config = json_decode($file);

?>
<html>
    
    <head>

        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0" />
        <meta name="viewport" content="initial-scale=1.0"/>
        
        <link rel="icon" type="image/ico" href="<?php echo $config->appurl ?>/favicon.ico" />
        
        <link rel="stylesheet" type="text/css" href="<?php echo $config->appurl ?>//assets/css/stylesheets/main.css">
        <link rel="stylesheet" href="<?php echo $config->appurl ?>/assets/font-awesome/css/font-awesome.min.css">

        <script type="text/javascript" data-main="<?php echo $config->appurl ?>/<?php echo $config->production ? 'dist' : 'js' ?>/main" src="<?php echo $config->appurl ?>/<?php echo $config->production ? 'dist' : 'js' ?>/vendor/require/require.js"></script>

        <title></title>
                
    </head>
    
    <body>
        <div id="dialog"></div>
        
        <div id="login">
            <iframe></iframe>
        </div>

        <div id="wrapper">
        
            <div id="header">
                <!--[if lte IE 11]>
                 <a class="icon"><i class="fa fa-2x fa-home"></i>&nbsp;</a>
                <![endif]-->
            </div>
            <div id="sidebar"></div>

            <div class="cont_wrap">
                <div id="container">
                    <!--[if lte IE 11]>
                        <div class="content">
                            <h1>Unsupported Browser</h1>
                            <p>Internet Explorer versions less than 11 are not supported. Please consider using <a href="http://www.mozilla.org/en-GB/firefox/new">Firefox</a> or <a href="http://www.google.co.uk/chrome">Chrome</a></p>
                        </div>
                    <![endif]-->
                </div>
            </div>
        
            <div id="footer">
                <p><a href="http://diamond.ac.uk">Diamond Light Source</a> &copy;2014</p>
            </div>
        
        </div>

    </body>
</html>