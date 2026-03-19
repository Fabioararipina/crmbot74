<?php
/**
 * MetasVendedores_Module_Model
 */
class MetasVendedores_Module_Model extends Vtiger_Module_Model {

    public function getCreateRecordUrl(): string {
        return 'index.php?module=MetasVendedores&view=Edit';
    }

    public function getDashboardUrl(): string {
        return 'index.php?module=MetasVendedores&view=Dashboard';
    }

    public function isActive(): bool {
        // vTiger: presence=0 significa ATIVO, presence=1 significa INATIVO
        $adb = PearDatabase::getInstance();
        $r   = $adb->pquery("SELECT presence FROM vtiger_tab WHERE name = 'MetasVendedores'", []);
        if ($adb->num_rows($r) > 0) {
            return (int)$adb->query_result($r, 0, 'presence') === 0;
        }
        return false;
    }

    public function getSideBarLinks($linkParams) {
        $links = [];
        $links['SIDEBARLINK'][] = Vtiger_Link_Model::getInstanceFromValues([
            'linktype' => 'SIDEBARLINK', 'linklabel' => 'Lista de Metas',
            'linkurl'  => 'index.php?module=MetasVendedores&view=List', 'linkicon' => '',
        ]);
        $links['SIDEBARLINK'][] = Vtiger_Link_Model::getInstanceFromValues([
            'linktype' => 'SIDEBARLINK', 'linklabel' => 'Dashboard',
            'linkurl'  => 'index.php?module=MetasVendedores&view=Dashboard', 'linkicon' => '',
        ]);
        return $links;
    }

    public function getModuleBasicLinks(): array {
        return [
            ['linktype' => 'BASIC', 'linklabel' => 'Nova Meta',
             'linkurl'  => $this->getCreateRecordUrl(), 'linkicon' => 'fa-plus'],
            ['linktype' => 'BASIC', 'linklabel' => 'Dashboard',
             'linkurl'  => $this->getDashboardUrl(), 'linkicon' => 'fa-bar-chart'],
        ];
    }

    public function isQuickSearchEnabled(): bool {
        return false;
    }
}
