<?php
/**
 * MetasVendedores — Script de Desinstalação (EMERGÊNCIA)
 * Remove o módulo do vTiger completamente. Os dados da tabela são apagados.
 *
 * Comando:
 *   docker exec crmbot74-vtiger-1 php /var/www/html/modules/MetasVendedores/uninstall.php
 */

chdir('/var/www/html');
define('VTIGER_ROOT_DIRECTORY', '/var/www/html');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

require_once 'vendor/autoload.php';
require_once 'include/utils/CommonUtils.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/utils/VtlibUtils.php';
require_once 'modules/Users/CreateUserPrivilegeFile.php';

$adb = PearDatabase::getInstance();

$r     = $adb->pquery("SELECT tabid FROM vtiger_tab WHERE name = 'MetasVendedores'", []);
if ($adb->num_rows($r) === 0) {
    echo "[INFO] Módulo MetasVendedores não está instalado.\n";
    exit(0);
}

$tabId = (int)$adb->query_result($r, 0, 'tabid');
echo "=== Desinstalando MetasVendedores (tabid=$tabId) ===\n\n";

// Campos → blocos → tab
$adb->pquery("DELETE FROM vtiger_profile2field WHERE tabid = ?", [$tabId]);
$adb->pquery("DELETE FROM vtiger_field        WHERE tabid = ?", [$tabId]);
$adb->pquery("DELETE FROM vtiger_blocks       WHERE tabid = ?", [$tabId]);
$adb->pquery("DELETE FROM vtiger_profile2tab  WHERE tabid = ?", [$tabId]);
$adb->pquery("DELETE FROM vtiger_def_org_share WHERE tabid = ?", [$tabId]);
$adb->pquery("DELETE FROM vtiger_links        WHERE linklabel = 'Metas de Vendedores'", []);
$adb->pquery("DELETE FROM vtiger_tab          WHERE tabid = ?", [$tabId]);

// Tabela de dados (preservar se quiser manter histórico — comente a linha abaixo)
$adb->query("DROP TABLE IF EXISTS vtiger_metasvendedores");

create_tab_data_file();
vtlib_RecreateUserPrivilegeFiles();

echo "✅ Módulo removido com sucesso.\n";
echo "   Limpe o cache:\n";
echo "   docker exec crmbot74-vtiger-1 bash -c 'rm -rf /var/www/html/cache/templates_c/* /var/www/html/cache/modules/*'\n";
