<?php
/**
 * PainelBI_GetData_View — Endpoint AJAX: executa relatório e retorna JSON
 * URL: index.php?module=PainelBI&view=GetData
 * POST: config (JSON string com configuração do relatório)
 */
class PainelBI_GetData_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        // Skip vTiger header
    }

    public function process(Vtiger_Request $request): void {
        header('Content-Type: application/json; charset=utf-8');

        try {
            require_once 'modules/PainelBI/models/DataProvider.php';

            $configRaw = $request->get('config');
            $config    = $configRaw ? (json_decode($configRaw, true) ?? []) : [];

            if (empty($config)) {
                echo json_encode(['success' => false, 'error' => 'Config vazia']);
                exit;
            }

            $dp   = new PainelBI_DataProvider_Model();
            $data = $dp->runReport($config);

            echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        exit;
    }

    public function postProcess(Vtiger_Request $request): void {
        // Skip vTiger footer
    }
}
