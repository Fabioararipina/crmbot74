<?php
class PainelBI_RemoveWidget_View extends Vtiger_Index_View {
    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }
    public function preProcess(Vtiger_Request $request, $display = true): void {}
    public function process(Vtiger_Request $request): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            require_once 'modules/PainelBI/models/DataProvider.php';
            $dp = new PainelBI_DataProvider_Model();
            $widgetId = (int)$request->get('widget_id');
            if (!$widgetId) { echo json_encode(['success'=>false,'error'=>'widget_id obrigatório']); exit; }
            $dp->removeWidget($widgetId);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    public function postProcess(Vtiger_Request $request): void {}
}
