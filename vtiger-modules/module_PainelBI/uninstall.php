<?php
/**
 * PainelBI — Desinstalação
 * docker exec crmbot74-vtiger-1 php /var/www/html/modules/PainelBI/uninstall.php
 */
$host = 'vtiger-db';
$user = 'vtiger';
$pass = getenv('VTIGER_DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: 'COLOQUE_NO_ENV');
$db   = 'vtiger';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) die("Erro: " . $mysqli->connect_error . "\n");
$mysqli->set_charset('utf8mb4');

$row = $mysqli->query("SELECT tabid FROM vtiger_tab WHERE name='PainelBI'")->fetch_assoc();
if (!$row) { echo "PainelBI não está instalado.\n"; exit(0); }
$tabId = $row['tabid'];

echo "Desinstalando PainelBI (tabid=$tabId)...\n";

$tables = ['vtiger_painelbi_widgets','vtiger_painelbi_tabs','vtiger_painelbi_boards','vtiger_painelbi_relatorios'];
foreach ($tables as $t) { $mysqli->query("DROP TABLE IF EXISTS `$t`"); echo "  DROP $t\n"; }

foreach (['vtiger_profile2tab','vtiger_profile2field','vtiger_def_org_share','vtiger_app2tab','vtiger_parenttabrel'] as $t) {
    $mysqli->query("DELETE FROM `$t` WHERE tabid='$tabId'");
}
$mysqli->query("DELETE FROM vtiger_field WHERE tabid='$tabId'");
$mysqli->query("DELETE FROM vtiger_blocks WHERE tabid='$tabId'");
$mysqli->query("DELETE FROM vtiger_links WHERE tabid='$tabId' OR linklabel='Painel BI'");
$mysqli->query("DELETE FROM vtiger_tab WHERE tabid='$tabId'");

chdir('/var/www/html');
define('VTIGER_ROOT_DIRECTORY', '/var/www/html');
require_once 'vendor/autoload.php';
require_once 'include/utils/CommonUtils.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/utils/VtlibUtils.php';
require_once 'modules/Users/CreateUserPrivilegeFile.php';
create_tab_data_file();
vtlib_RecreateUserPrivilegeFiles();

$mysqli->close();
echo "\n✅ PainelBI desinstalado.\n";
