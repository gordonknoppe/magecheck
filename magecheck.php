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
        $id = str_replace(' ', '_', strtolower($section));
        return sprintf("<h2 id=\"%s\">%s</h2>\n", $id, $section);
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

function check_phprequiredextensions(Magecheck_Test $test)
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

function check_mysqlvar($mysqlVars, $key, $recommended, $megabyte = true)
{
    if ($megabyte) {
        $label = $recommended / 1048576 . "M";
    } else {
        $label = $recommended;
    }
    return magecheck_createresult(
        $mysqlVars[$key] >= $recommended,
        "MySQL configuration value <code>$key</code> is <code>$label</code>",
        "MySQL configuration value <code>$key</code> should be <code>$label</code> or higher, currently: <code>" . $mysqlVars[$key] . "</code>"
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
    $cache = apc_cache_info('opcode');
    $test->addResult('PHP APC', magecheck_createresult(
        $cache['expunges'] < 1,
        "APC expunges count is 0",
        "APC expunges count is greater than 0, consider adjusting your cache size"
    ));
}

// Check Magento
$test->addSection('Magento');
$mageFile = 'app' . DIRECTORY_SEPARATOR . 'Mage.php';
if (file_exists($mageFile)) {

    // Initialize Magento
    require_once $mageFile;
    Mage::app('admin', 'store');

    if (Mage::isInstalled()) {
        // Check Magento cache types
        $mageCache = Mage::app()->getCacheInstance()->getTypes();
        foreach($mageCache as $cache) {
            $test->addResult('Magento', magecheck_createresult($cache->getStatus(), sprintf("Cache %s is enabled", $cache->getCacheType()), sprintf("Cache %s is not enabled", $cache->getCacheType())));
        }

        // Check MySQL values
        $test->addSection('MySQL');

        $db = Mage::getModel('core/store')->getResource()->getReadConnection();
        $mysqlVars = $db->fetchPairs("SHOW VARIABLES");
        if (isset($_GET['mcf-ram'])) {
            $ram = filter_var($_GET['mcf-ram'], FILTER_SANITIZE_NUMBER_INT);
            if ($ram > 0) {
                $buffer_pool_size = $ram * .8 * 1048576;
                $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'innodb_buffer_pool_size', $buffer_pool_size));
            }
        }
        if (isset($_GET['mcf-cores'])) {
            $cpu_cores = filter_var($_GET['mcf-cores'], FILTER_SANITIZE_NUMBER_INT);
            if ($cpu_cores > 0) {
                $concurrency = 2 * $cpu_cores + 2;
                $concurrency = max(array($concurrency, 8));
                $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'innodb_thread_concurrency', $concurrency, false));
            }
        }
        $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'query_cache_size', 67108864));
        $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'query_cache_limit', 2097152));
        $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'sort_buffer_size', 8388608));
    }
}
?>
<html>
<head>
    <title><?php echo $_SERVER['HTTP_HOST'];?> - Magento Health Check</title>
    <style type="text/css">
        body {
            font-family: sans-serif;
        }
        ul {
            padding-left: 0;
        }
        label, input {
            display: block;
            margin-bottom: 5px;
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
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
<script>
$(document).ready(function() {
    if ($('#mysql').length) {
        var h2 = $('#mysql');
        h2.after($('#mysql-calculator').html());
    }
})
</script>
<script type="text/template" id="mysql-calculator">
    <form id="mysql-calculator-form">
        <label for="mcf-cores">How many cpu cores does your database server have?</label>
        <input type="text" id="mcf-cores" name="mcf-cores" />
        <label for="mcf-ram">How much available RAM does your database server have? (MB)</label>
        <input type="text" id="mcf-ram" name="mcf-ram" />
        <button type="submit" id="mcf-submit">Calculate</button>
    </form>
</script>
</body>
</html>
