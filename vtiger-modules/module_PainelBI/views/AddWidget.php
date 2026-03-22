<?php
/**
 * PainelBI_AddWidget_View — Endpoint AJAX: adicionar/remover widgets de tabs
 */
class PainelBI_AddWidget_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }
    public function preProcess(Vtiger_Request $request, $display = true): void {}

    public function process(Vtiger_Request $request): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            require_once 'modules/PainelBI/models/DataProvider.php';
            $dp = new PainelBI_DataProvider_Model();

            $tabId      = (int)$request->get('tab_id');
            $relatorioId= (int)$request->get('relatorio_id');
            $largura    = (int)($request->get('largura') ?? 6);

            if (!$tabId || !$relatorioId) {
                echo json_encode(['success' => false, 'error' => 'tab_id e relatorio_id obrigatórios']);
                exit;
            }

            $id = $dp->addWidget($tabId, $relatorioId, $largura);
            echo json_encode(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function postProcess(Vtiger_Request $request): void {}
}
