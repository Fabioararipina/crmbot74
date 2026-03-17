<?php
/**
 * Trigger the module request and log any errors
 * Access this via browser: http://localhost:8181/modules/MetasVendedores/trigger_error.php
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set up error log
$logFile = '/tmp/metas_error.log';
ini_set('error_log', $logFile);
ini_set('log_errors', 1);

echo "<pre>Checking module...\n";

// Check files
$files = [
    '/var/www/html/modules/MetasVendedores/MetasVendedores.php',
    '/var/www/html/modules/MetasVendedores/views/List.php',
    '/var/www/html/modules/MetasVendedores/views/Edit.php',
    '/var/www/html/modules/MetasVendedores/views/Detail.php',
    '/var/www/html/modules/MetasVendedores/views/Dashboard.php',
    '/var/www/html/modules/MetasVendedores/models/Picklist.php',
    '/var/www/html/modules/MetasVendedores/models/Record.php',
    '/var/www/html/modules/MetasVendedores/models/Module.php',
    '/var/www/html/modules/MetasVendedores/actions/Save.php',
    '/var/www/html/modules/MetasVendedores/actions/Delete.php',
    '/var/www/html/modules/MetasVendedores/language/pt_br.lang.php',
];
foreach ($files as $f) {
    echo basename($f) . ": " . (file_exists($f) ? "OK" : "MISSING") . "\n";
}

echo "\nPHP version: " . phpversion() . "\n";

// Load config and try the full bootstrap
chdir('/var/www/html');
include 'config.php';
require_once 'vendor/autoload.php';
include_once 'vtlib/Vtiger/Module.php';

echo "\nTesting Vtiger_Module_Model::getInstance('MetasVendedores'):\n";
$m = Vtiger_Module_Model::getInstance('MetasVendedores');
echo "Result: " . ($m ? get_class($m) . " (id=" . $m->getId() . ")" : "NULL") . "\n";

if ($m) {
    echo "\nModule fields:\n";
    $fields = $m->getFields();
    echo count($fields) . " fields found\n";
    foreach ($fields as $fn => $fm) {
        echo "  - $fn\n";
    }
}

echo "\nAll done. Check $logFile for errors.\n";
echo "</pre>";
