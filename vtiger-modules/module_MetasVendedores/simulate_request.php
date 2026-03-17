<?php
/**
 * Simulates a full vTiger request to the MetasVendedores module
 * Run: docker exec crmbot74-vtiger-1 php /var/www/html/modules/MetasVendedores/simulate_request.php
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// Capture output
ob_start();

// Fake the web environment
$_SERVER['HTTP_HOST'] = 'localhost:8181';
$_SERVER['REQUEST_URI'] = '/index.php?module=MetasVendedores&view=List';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTPS'] = '';
$_REQUEST['module'] = 'MetasVendedores';
$_REQUEST['view'] = 'List';
$_GET['module'] = 'MetasVendedores';
$_GET['view'] = 'List';
$_SESSION = [];

chdir('/var/www/html');

// Load exactly what index.php loads
include_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'include/Webservices/Relation.php';
include_once 'vtlib/Vtiger/Module.php';
include_once 'includes/main/WebUI.php';

// Now load CommonUtils (important!)
require_once 'include/utils/CommonUtils.php';

$output = ob_get_clean();

echo "Bootstrap OK\n";
echo "Common Utils loaded: " . (function_exists('checkFileAccessForInclusion') ? "YES" : "NO") . "\n";

// Now test the critical path
echo "\nTesting Vtiger_Loader::getComponentClassName...\n";
try {
    $className = Vtiger_Loader::getComponentClassName('View', 'List', 'MetasVendedores');
    echo "Class name: $className\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTesting Vtiger_Module_Model::getInstance...\n";
try {
    $moduleModel = Vtiger_Module_Model::getInstance('MetasVendedores');
    if ($moduleModel) {
        echo "Module found: " . get_class($moduleModel) . " id=" . $moduleModel->getId() . "\n";
    } else {
        echo "Module returned FALSE (not found in cache/DB)\n";
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTesting class instantiation...\n";
try {
    $handler = new $className();
    echo "Handler instantiated: " . get_class($handler) . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
