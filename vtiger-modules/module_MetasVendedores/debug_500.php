<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

chdir('/var/www/html');
define('VTIGER_ROOT_DIRECTORY', '/var/www/html');
define('NOVALIBFOUND', 1);

// Simulate a request
$_REQUEST['module'] = 'MetasVendedores';
$_REQUEST['view'] = 'List';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/index.php?module=MetasVendedores&view=List';

echo "Step 1: Loading autoloader...\n";
require_once 'vendor/autoload.php';
echo "OK\n";

echo "Step 2: Loading database...\n";
require_once 'include/database/PearDatabase.php';
echo "OK\n";

echo "Step 3: Loading Vtiger_Index_View...\n";
require_once 'include/MVC/View/IndexView.php';
echo "OK\n";

echo "Step 4: Loading our List view...\n";
require_once 'modules/MetasVendedores/views/List.php';
echo "OK\n";

echo "Step 5: Instantiating...\n";
$v = new MetasVendedores_List_View();
echo "OK\n";

echo "\nAll checks passed!\n";
