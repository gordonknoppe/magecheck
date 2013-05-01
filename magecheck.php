<?php
/**
* NOTICE OF LICENSE
*
* Copyright 2012 Guidance Solutions
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
*
* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.

* @author Gordon Knoppe
* @category Guidance
* @package Magecheck
* @copyright Copyright (c) 2012 Guidance Solutions (http://www.guidance.com)
* @license http://www.apache.org/licenses/LICENSE-2.0 Apache License 2.0
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

    function addResult($section, Magecheck_Test_Interface $result)
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
            $html .= $result->toHtml();
        }
        $html .= "</ul>\n";
        return $html;
    }
}

interface Magecheck_Test_Interface
{
    public function toHtml();
}

class Magecheck_Test_Result implements Magecheck_Test_Interface
{
    var $result;
    var $message;

    public function toHtml()
    {
        if ($this->result) {
            $class = 'alert-success';
        } else {
            $class = 'alert-error';
        }
        return sprintf("<li class=\"alert %s\">%s</li>\n", $class, $this->message);
    }
}

class Magecheck_Test_ResultMessage implements Magecheck_Test_Interface
{
    var $message;

    public function toHtml()
    {
        $class = 'alert-warn';
        return sprintf("<li class=\"alert %s\">%s</li>\n", $class, $this->message);
    }
}


function check_keepalive($phpinfo)
{
    return magecheck_createresult(
        strpos($phpinfo, 'Keep Alive: off'),
        'Keep Alive is off',
        'Keep Alive should be off'
    );
}

function check_apachemodule($phpinfo, $module)
{
    return magecheck_createresult(
        strpos($phpinfo, $module),
        "$module is enabled",
        "$module is not enabled"
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
    $value       = $mysqlVars[$key];
    $label       = $recommended;
    $actualLabel = $value;

    if ($megabyte) {
        $label       = magecheck_mysqlbytestoconfig($recommended);
        $actualLabel = magecheck_mysqlbytestoconfig($value);
    }

    return magecheck_createresult(
        $value >= $recommended,
        "MySQL configuration value <code>$key</code> is <code>$actualLabel</code>",
        "MySQL configuration value <code>$key</code> should be <code>$label</code> or higher, currently: <code>" . $actualLabel . "</code>"
    );
}

function magecheck_mysqlbytestoconfig($bytes)
{
    return $bytes / 1048576 . "M";
}

function magecheck_mysqlconfigtobytes($config)
{
    return rtrim($config, 'Mm') * 1048576;
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


// Get phpinfo contents for Apache checks
ob_start();
phpinfo();
$phpinfo = ob_get_clean();

// Check Apache
$requiredApacheModules = array(
    'mod_env',
    'mod_php',
    'mod_expires',
    'mod_deflate',
    'mod_mime',
    'mod_dir',
    'mod_rewrite',
    'mod_authz_host',
    'mod_authz_user'
);
$test->addSection('Apache');
$test->addResult('Apache', check_keepalive($phpinfo));
foreach ($requiredApacheModules as $apacheModule) {
    $test->addResult('Apache', check_apachemodule($phpinfo, $apacheModule));
}

// Detect apache modules
$apacheModuleRegex = "mod_[a-z_]*";
$matches = array();
preg_match_all("/$apacheModuleRegex/", $phpinfo, $matches);

$modules_to_disable = array_diff($matches[0], $requiredApacheModules);

if (count($modules_to_disable)) {
    $message = new Magecheck_Test_ResultMessage();
    $message->message = sprintf(
        "The following Apache modules are enabled and not required, they should be disabled:<br />%s",
        implode(', ', $modules_to_disable)
    );
}

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

// Check memcache lib
$test->addResult('PHP Memcache - Required if using memcache for cache or session storage', check_phprequiredextension('memcache'));

// Check redis lib
$test->addResult('PHP Redis - Required if using redis for cache or session storage', check_phprequiredextension('redis'));

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
        $table_cache_key = 'table_cache';
        if (!isset($mysqlVars[$table_cache_key])) {
            $table_cache_key = 'table_open_cache';
        }
        $test->addResult('MySQL', check_mysqlvar($mysqlVars, $table_cache_key, 1024, false));
        $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'query_cache_size', 67108864));
        $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'query_cache_limit', 2097152));
        $test->addResult('MySQL', check_mysqlvar($mysqlVars, 'sort_buffer_size', 8388608));

        if (isset($_GET['check-permissions-submit'])) {
            // Check file permissions
            $dirs = array(
                'media' => Mage::getConfig()->getOptions()->getMediaDir(),
                'var'   => Mage::getConfig()->getOptions()->getVarDir()
            );

            foreach ($dirs as $label => $dir) {
                $ite = new RecursiveDirectoryIterator($dir);

                $notWritable = array();
                foreach (new RecursiveIteratorIterator($ite) as $filename => $cur) {
                    /** @var $cur SplFileInfo */
                    if ($cur->getFilename() == '..') {
                        continue;
                    }
                    if (!$cur->isWritable()) {
                        $notWritable[] = $cur->getRealPath();
                    }
                }
                if (count($notWritable)) {
                    $notWritableFiles = implode("<br>\n", $notWritable);
                }
                $test->addResult('Magento',
                    magecheck_createresult(
                        count($notWritable) == 0,
                        "All files in the $label directory are writable by the web server",
                        "The following files in the $label directory are not writable by the web server:<br><br><pre><code>$notWritableFiles</code></pre>"
                    )
                );
            }
        }
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
    if ($('#magento').length) {
        var h2 = $('#magento');
        h2.after($('#check-permissions').html());
    }
})
</script>
<script type="text/template" id="mysql-calculator">
    <form id="mysql-calculator-form">
        <label for="mcf-cores">How many cpu cores does your database server have? (grep -c processor /proc/cpuinfo)</label>
        <input type="text" id="mcf-cores" name="mcf-cores" />
        <label for="mcf-ram">How much available RAM does your database server have? (in Megabytes)</label>
        <input type="text" id="mcf-ram" name="mcf-ram" />
        <button type="submit" id="mcf-submit">Calculate</button>
    </form>
</script>
<script type="text/template" id="check-permissions">
    <form id="check-permissions-form">
        <label for="mcf-cores">Check file permissions in /media and /var to ensure all files are writable by the web server.</label>
        <input type="submit" id="check-permissions-submit" name="check-permissions-submit" value="Check file permissions" />
    </form>
</script>
</body>
</html>
