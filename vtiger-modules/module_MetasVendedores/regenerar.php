<?php
chdir('/var/www/html');
define('VTIGER_ROOT_DIRECTORY', '/var/www/html');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once 'vendor/autoload.php';
require_once 'include/utils/CommonUtils.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/utils/VtlibUtils.php';
require_once 'modules/Users/CreateUserPrivilegeFile.php';

$adb = PearDatabase::getInstance();

// Verificar se o módulo está no banco
$r = $adb->query("SELECT tabid, name, presence FROM vtiger_tab WHERE name='MetasVendedores'");
if ($adb->num_rows($r) > 0) {
    $row = $adb->query_result_rowdata($r, 0);
    echo "Módulo encontrado: tabid=" . $row['tabid'] . " presence=" . $row['presence'] . "\n";
} else {
    echo "ERRO: Módulo não encontrado no vtiger_tab!\n";
    exit(1);
}

echo "Regenerando tabdata.php...\n";
create_tab_data_file();

echo "Regenerando user_privileges...\n";
vtlib_RecreateUserPrivilegeFiles();

echo "Limpando cache de módulos...\n";
$cacheDir = '/var/www/html/cache/modules/';
if (is_dir($cacheDir)) {
    array_map('unlink', glob($cacheDir . '*'));
}
$tplDir = '/var/www/html/cache/templates_c/';
if (is_dir($tplDir)) {
    array_map('unlink', glob($tplDir . '*'));
}

echo "OK - tudo regenerado!\n";
echo "Acesse: http://localhost:8181/index.php?module=MetasVendedores&view=List\n";
