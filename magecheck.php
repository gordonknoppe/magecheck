<?php
/**
 * Magento health check
 *
 * Script used to analyze an environment hosting Magento
 * @author Gordon Knoppe
 */

class Magecheck_Test
{
    protected $_test;

    function __construct()
    {
        $this->_test = array();
    }

    function addSection($section)
    {
        $this->_test[$section] = array();
        return $this;
    }

    function addResult($section, Magecheck_Test_Result $result)
    {
        $this->_test[$section][] = $result;
        return $this;
    }

    function createResult($section, $result, $message)
    {
        $resultObject = new Magecheck_Test_Result();
        $resultObject->message = $message;
        $resultObject->result  = $result;
        $this->addResult($section, $resultObject);
        return $this;
    }

    function getTest()
    {
        return $this->_test;
    }

    function toHtml()
    {
        $html = '';
        foreach ($this->_test as $sectionTitle => $sectionResults) {
            $html .= $this->_formatSection($sectionTitle);
            if (count($sectionResults)) {
                $html .= $this->_formatResults($sectionResults);
            }
        }
        return $html;
    }

    protected function _formatSection($section)
    {
        return sprintf("<h2>%s</h2>\n", $section);
    }

    protected function _formatResults($results)
    {
        $html = '';
        $html .= '<ul>';
        foreach ($results as $result) {
            if ($result->result) {
                $class = 'alert-success';
            } else {
                $class = 'alert-error';
            }
            $html .= sprintf("<li class=\"alert %s\">%s</li>\n", $class, $result->message);
        }
        $html .= "</ul>\n";
        return $html;
    }
}

class Magecheck_Test_Result
{
    var $result;
    var $message;
}

function check_keepalive()
{
    // Load phpinfo for apache vars not exposed via php functions
    ob_start();
    phpinfo();
    $phpinfo = ob_get_contents();
    ob_end_clean();

    return magecheck_createresult(
        strpos($phpinfo, 'Keep Alive: off'),
        'Keep Alive is off',
        'Keep Alive is on'
    );
}

function check_phpversion()
{
    return magecheck_createresult(
        version_compare(PHP_VERSION, '5.2.13') >= 0,
        "PHP version ".PHP_VERSION." is installed",
        "PHP 5.2.13 or higher is required"
    );
}

function check_phpextension($extension)
{
    $extensions = get_loaded_extensions();
    return in_array($extension, $extensions);
}

function check_phprequiredextensions($test)
{
    $required_extensions = array('curl', 'dom', 'gd', 'hash', 'iconv', 'mcrypt', 'mhash', 'PDO', 'pdo_mysql', 'SimpleXML', 'soap');
    foreach ($required_extensions as $extension) {
        $result = check_phprequiredextension($extension);
        $test->addResult('PHP', $result);
    }
}

function check_phprequiredextension($extension)
{
    return magecheck_createresult(
        check_phpextension($extension),
        "Extension $extension is installed",
        "Extension $extension is required"
    );
}

function check_phpapc()
{
    return check_phprequiredextension('apc');
}

function check_phpini($ini, $recommended)
{
    return magecheck_createresult(
        ini_get($ini) >= $recommended,
        "PHP configuration value <code>$ini</code> is <code>" . ini_get($ini) . "</code>",
        "PHP configuration value <code>$ini</code> should be <code>$recommended</code> or higher, currently: <code>" . ini_get($ini) . "</code>"
    );
}

function magecheck_createresult($test, $pass, $fail)
{
    $result = new Magecheck_Test_Result();
    $result->result = $test;
    if ($result->result) {
        $result->message = $pass;
    } else {
        $result->message = $fail;
    }
    return $result;
}

$test = new Magecheck_Test();
$test->addSection('Apache');
$test->addResult('Apache', check_keepalive());

// Check PHP
$test->addSection('PHP');
$test->addResult('PHP', check_phpversion());
check_phprequiredextensions($test);

// Check APC
$test->addSection('PHP APC');
$test->addResult('PHP APC', check_phpapc());
if (check_phpextension('apc')) {
    $test->addResult('PHP APC', check_phpini('apc.shm_size', 256));
    $test->addResult('PHP APC', check_phpini('apc.num_files_hint', 10000));
    $test->addResult('PHP APC', check_phpini('apc.user_entries_hint', 10000));
    $test->addResult('PHP APC', check_phpini('apc.max_file_size', 5));
}

$test->addSection('Magento');
$test->addSection('MySQL');
?>
<html>
<head>
    <title><?php echo $_SERVER['HTTP_HOST'];?> - Magento Health Check</title>
    <style type="text/css">
        body {
            font-family: sans-serif;
        }
        .alert {
            background-color: #FCF8E3;
            border: 1px solid #FBEED5;
            border-radius: 4px 4px 4px 4px;
            color: #C09853;
            margin-bottom: 20px;
            padding: 8px 35px 8px 14px;
            text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
            list-style: none;
        }
        .alert-success {
            background-color: #DFF0D8;
            border-color: #D6E9C6;
            color: #468847;
        }
        .alert-error {
            background-color: #F2DEDE;
            border-color: #EED3D7;
            color: #B94A48;
        }
    </style>
</head>
<body>
<h1>Magento Health Check</h1>
<?php echo $test->toHtml(); ?>
</body>
</html>
