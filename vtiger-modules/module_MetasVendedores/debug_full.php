<?php
// Full vTiger bootstrap debug — place in webroot, access via browser
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/modules/MetasVendedores/debug_full.php';
$_SERVER['REQUEST_METHOD'] = 'GET';

chdir('/var/www/html');

echo "<pre>\n";

echo "Step 1: vendor autoload... ";
require_once 'vendor/autoload.php';
echo "OK\n";

echo "Step 2: config.inc.php... ";
require_once 'config.inc.php';
echo "OK\n";

echo "Step 3: Loader... ";
require_once 'includes/Loader.php';
echo "OK\n";

echo "Step 4: PearDatabase... ";
require_once 'include/database/PearDatabase.php';
echo "OK\n";

echo "Step 5: Load models... ";
require_once 'modules/MetasVendedores/models/Picklist.php';
require_once 'modules/MetasVendedores/models/Record.php';
echo "OK\n";

echo "Step 6: Load views (using Loader)... ";
Vtiger_Loader::includeOnce('~modules/Vtiger/views/Basic.php');
echo "Basic OK... ";
Vtiger_Loader::includeOnce('~modules/Vtiger/views/Index.php');
echo "Index OK... ";
Vtiger_Loader::includeOnce('~modules/MetasVendedores/views/List.php');
echo "List OK\n";

echo "\nAll OK!\n";
echo "</pre>\n";
