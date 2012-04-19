<?php
/**
 * Magento health check
 *
 * Script used to analyze an environment hosting Magento
 * @author Gordon Knoppe
 */


function magecheck_result($test, $success, $failure, $recommended = false)
{
    $message = "";
    $class = "notice";
    if ($test) {
        $class .= " success";
        $message = $success;
    } else {
        if (!$recommended) {
            $class .= " error-message";
        }
        $message = $failure;
    }
    return sprintf('<li class="%s">%s</li>', $class, $message) . PHP_EOL;
    
}

function magecheck_inicheck($config, $recommended)
{
    return magecheck_result(ini_get($config) >= $recommended, "$config is " . ini_get($config), "$config should be $recommended or higher, currently: " . ini_get($config));
}

// Load phpinfo for apache vars not exposed via php functions
ob_start();
phpinfo();
$phpinfo = ob_get_contents();
ob_end_clean();

?>
<html>
<head>
    <title><?php echo $_SERVER['HTTP_HOST'];?> - Magento Check</title>
    <style type="text/css">/*! normalize.css 2012-03-11T12:53 UTC - http://github.com/necolas/normalize.css */article,aside,details,figcaption,figure,footer,header,hgroup,nav,section,summary{display:block}audio,canvas,video{display:inline-block;*display:inline;*zoom:1}audio:not([controls]){display:none;height:0}[hidden]{display:none}html{font-size:100%;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}html,button,input,select,textarea{font-family:sans-serif}body{margin:0}a:focus{outline:thin dotted}a:hover,a:active{outline:0}h1{font-size:2em;margin:.67em 0}h2{font-size:1.5em;margin:.83em 0}h3{font-size:1.17em;margin:1em 0}h4{font-size:1em;margin:1.33em 0}h5{font-size:.83em;margin:1.67em 0}h6{font-size:.75em;margin:2.33em 0}abbr[title]{border-bottom:1px dotted}b,strong{font-weight:bold}blockquote{margin:1em 40px}dfn{font-style:italic}mark{background:#ff0;color:#000}p,pre{margin:1em 0}pre,code,kbd,samp{font-family:monospace,serif;_font-family:'courier new',monospace;font-size:1em}pre{white-space:pre;white-space:pre-wrap;word-wrap:break-word}q{quotes:none}q:before,q:after{content:'';content:none}small{font-size:75%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sup{top:-0.5em}sub{bottom:-0.25em}dl,menu,ol,ul{margin:1em 0}dd{margin:0 0 0 40px}menu,ol,ul{padding:0 0 0 40px}nav ul,nav ol{list-style:none;list-style-image:none}img{border:0;-ms-interpolation-mode:bicubic}svg:not(:root){overflow:hidden}figure{margin:0}form{margin:0}fieldset{border:1px solid #c0c0c0;margin:0 2px;padding:.35em .625em .75em}legend{border:0;padding:0;white-space:normal;*margin-left:-7px}button,input,select,textarea{font-size:100%;margin:0;vertical-align:baseline;*vertical-align:middle}button,input{line-height:normal}button,input[type="button"],input[type="reset"],input[type="submit"]{cursor:pointer;-webkit-appearance:button;*overflow:visible}button[disabled],input[disabled]{cursor:default}input[type="checkbox"],input[type="radio"]{box-sizing:border-box;padding:0;*height:13px;*width:13px}input[type="search"]{-webkit-appearance:textfield;-moz-box-sizing:content-box;-webkit-box-sizing:content-box;box-sizing:content-box}input[type="search"]::-webkit-search-decoration,input[type="search"]::-webkit-search-cancel-button{-webkit-appearance:none}button::-moz-focus-inner,input::-moz-focus-inner{border:0;padding:0}textarea{overflow:auto;vertical-align:top}table{border-collapse:collapse;border-spacing:0}</style>
    <style type="text/css">
            /** Notices and Errors **/
        .message {
            clear: both;
            color: #fff;
            font-size: 140%;
            font-weight: bold;
            margin: 0 0 1em 0;
            padding: 5px;
        }

        .success,
        .message,
        .cake-error,
        .cake-debug,
        .notice,
        p.error,
        .error-message {
            background: #ffcc00;
            background-repeat: repeat-x;
            background-image: -moz-linear-gradient(top, #ffcc00, #E6B800);
            background-image: -ms-linear-gradient(top, #ffcc00, #E6B800);
            background-image: -webkit-gradient(linear, left top, left bottom, from(#ffcc00), to(#E6B800));
            background-image: -webkit-linear-gradient(top, #ffcc00, #E6B800);
            background-image: -o-linear-gradient(top, #ffcc00, #E6B800);
            background-image: linear-gradient(top, #ffcc00, #E6B800);
            text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(0, 0, 0, 0.2);
            margin-bottom: 18px;
            padding: 7px 14px;
            color: #404040;
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
            -webkit-border-radius: 4px;
            -moz-border-radius: 4px;
            border-radius: 4px;
            -webkit-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25);
            -moz-box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.25);
        }

        .success,
        .message,
        .cake-error,
        p.error,
        .error-message {
            clear: both;
            color: #fff;
            background: #c43c35;
            border: 1px solid rgba(0, 0, 0, 0.5);
            background-repeat: repeat-x;
            background-image: -moz-linear-gradient(top, #ee5f5b, #c43c35);
            background-image: -ms-linear-gradient(top, #ee5f5b, #c43c35);
            background-image: -webkit-gradient(linear, left top, left bottom, from(#ee5f5b), to(#c43c35));
            background-image: -webkit-linear-gradient(top, #ee5f5b, #c43c35);
            background-image: -o-linear-gradient(top, #ee5f5b, #c43c35);
            background-image: linear-gradient(top, #ee5f5b, #c43c35);
            text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.3);
        }

        .success {
            clear: both;
            color: #fff;
            border: 1px solid rgba(0, 0, 0, 0.5);
            background: #3B8230;
            background-repeat: repeat-x;
            background-image: -webkit-gradient(linear, left top, left bottom, from(#76BF6B), to(#3B8230));
            background-image: -webkit-linear-gradient(top, #76BF6B, #3B8230);
            background-image: -moz-linear-gradient(top, #76BF6B, #3B8230);
            background-image: -ms-linear-gradient(top, #76BF6B, #3B8230);
            background-image: -o-linear-gradient(top, #76BF6B, #3B8230);
            background-image: linear-gradient(top, #76BF6B, #3B8230);
            text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.3);
        }

        p.error {
            font-family: Monaco, Consolas, Courier, monospace;
            font-size: 120%;
            padding: 0.8em;
            margin: 1em 0;
        }

        p.error em {
            font-weight: normal;
            line-height: 140%;
        }

        .notice {
            color: #000;
            display: block;
            font-size: 90%;
            margin: 1em 0;
        }

        .success {
            color: #fff;
        }
    </style>
</head>
<body>

<h1>Magento Health Check</h1>

<ul>
    <?php echo magecheck_result(true, "The following statement is true", "The prior statement was false"); ?>
    <?php echo magecheck_result(false, "The following statement is true", "The prior statement was false"); ?>
</ul>

<h2>Apache</h2>

<ul>
    <?php echo magecheck_result(strpos($phpinfo, 'Keep Alive: off'), "Keep Alive is not enabled", "Keep Alive is enabled"); ?>
</ul>

<h2>PHP</h2>

<ul>
    <?php echo magecheck_result(version_compare(PHP_VERSION, '5.2.13') >= 0, "PHP ".PHP_VERSION." is installed", "PHP 5.2.13 or higher is required"); ?>
</ul>

<?php $extensions = get_loaded_extensions(); ?>
<ul>
    <?php $required_extensions = array('curl', 'dom', 'gd', 'hash', 'iconv', 'mcrypt', 'mhash', 'PDO', 'pdo_mysql', 'SimpleXML', 'soap'); ?>
    <?php foreach ($required_extensions as $extension): ?>
        <?php echo magecheck_result(in_array($extension, $extensions), "Extension $extension is installed", "Extension $extension is required"); ?>
    <?php endforeach; ?>
    <?php $recommended_extensions = array('apc', 'memcache'); ?>
    <?php foreach ($recommended_extensions as $extension): ?>
        <?php echo magecheck_result(in_array($extension, $extensions), "Extension $extension is installed", "Extension $extension is recommended", true); ?>
    <?php endforeach; ?>
</ul>

<h3>PHP APC Settings</h3>

<?php if (in_array('apc', $extensions)): ?>
<ul>
    <?php echo magecheck_inicheck('apc.shm_size', 256); ?>
    <?php echo magecheck_inicheck('apc.num_files_hint', 10000); ?>
    <?php echo magecheck_inicheck('apc.user_entries_hint', 10000); ?>
    <?php echo magecheck_inicheck('apc.max_file_size', 5); ?>
</ul>
<?php else: ?>
<ul>
    <?php echo magecheck_result(false, "", "APC not detected", true); ?>
</ul>
<?php endif; ?>

<h2>Magento</h2>

<h2>MySQL</h2>

</body>
</html>
