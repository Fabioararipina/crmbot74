<?php
// This must be accessed via web (browser), not CLI
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    echo "<b>ERROR [$errno]</b>: $errstr in $errfile line $errline<br>\n";
    return true;
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo "<b>FATAL ERROR</b>: {$error['message']} in {$error['file']} line {$error['line']}<br>\n";
    }
});

echo "<pre>\n";

// Simulate being in webroot
chdir('/var/www/html');

echo "1. Loading config...\n";
include 'config.php';
echo "OK\n";

echo "2. Loading autoload...\n";
require_once 'vendor/autoload.php';
echo "OK\n";

echo "3. Loading WebUI...\n";
include_once 'includes/main/WebUI.php';
echo "OK\n";

echo "4. Checking module file exists: ";
$f = '/var/www/html/modules/MetasVendedores/views/List.php';
echo (file_exists($f) ? "YES" : "NO") . "\n";

echo "5. Checking MetasVendedores.php: ";
$f2 = '/var/www/html/modules/MetasVendedores/MetasVendedores.php';
echo (file_exists($f2) ? "YES" : "NO") . "\n";

echo "6. Checking language file: ";
$f3 = '/var/www/html/modules/MetasVendedores/language/pt_br.lang.php';
echo (file_exists($f3) ? "YES" : "NO") . "\n";

echo "7. Handler class resolution...\n";
try {
    $className = Vtiger_Loader::getComponentClassName('View', 'List', 'MetasVendedores');
    echo "    Class: $className\n";
} catch (Exception $e) {
    echo "    EXCEPTION: " . $e->getMessage() . "\n";
}

echo "8. Loading view class...\n";
try {
    require_once '/var/www/html/modules/MetasVendedores/views/List.php';
    echo "    File loaded OK\n";
} catch (Exception $e) {
    echo "    EXCEPTION: " . $e->getMessage() . "\n";
}

echo "9. Check if class exists: ";
echo (class_exists('MetasVendedores_List_View', false) ? "YES" : "NO") . "\n";

echo "\nDone.\n</pre>";
