<?php
/**
 * PainelBI_SaveBoard_View — Endpoint AJAX: gerenciar boards e tabs
 * POST: action_type = add_board | rename_board | add_tab | rename_tab
 */
class PainelBI_SaveBoard_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }
    public function preProcess(Vtiger_Request $request, $display = true): void {}

    public function process(Vtiger_Request $request): void {
        header('Content-Type: application/json; charset=utf-8');
        try {
            require_once 'modules/PainelBI/models/DataProvider.php';
            $dp     = new PainelBI_DataProvider_Model();
            $action = $request->get('action_type');
            $titulo = trim($request->get('titulo') ?? '');

            if (!$titulo) {
                echo json_encode(['success' => false, 'error' => 'Título obrigatório']);
                exit;
            }

            switch ($action) {
                case 'add_board':
                    $id = $dp->saveBoard(['titulo' => $titulo, 'compartilhado' => 0]);
                    // Criar tab padrão
                    $dp->saveTab(['board_id' => $id, 'titulo' => 'Principal']);
                    echo json_encode(['success' => true, 'id' => $id]);
                    break;
                case 'rename_board':
                    $id = (int)$request->get('board_id');
                    $dp->saveBoard(['id' => $id, 'titulo' => $titulo, 'compartilhado' => 0]);
                    echo json_encode(['success' => true, 'id' => $id]);
                    break;
                case 'add_tab':
                    $boardId = (int)$request->get('board_id');
                    $id = $dp->saveTab(['board_id' => $boardId, 'titulo' => $titulo]);
                    echo json_encode(['success' => true, 'id' => $id]);
                    break;
                case 'rename_tab':
                    $id = (int)$request->get('tab_id');
                    $dp->saveTab(['id' => $id, 'titulo' => $titulo]);
                    echo json_encode(['success' => true, 'id' => $id]);
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Ação inválida: ' . $action]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function postProcess(Vtiger_Request $request): void {}
}
