<?php
/**
 * PainelBI_SaveRelatorio_View — Endpoint AJAX: salvar/deletar relatórios
 * URL: index.php?module=PainelBI&view=SaveRelatorio
 * POST params:
 *   action_type = save | delete
 *   id, titulo, modulo_base, tipo, compartilhado, config (JSON)
 */
class PainelBI_SaveRelatorio_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {}

    public function process(Vtiger_Request $request): void {
        header('Content-Type: application/json; charset=utf-8');

        try {
            require_once 'modules/PainelBI/models/DataProvider.php';
            $dp     = new PainelBI_DataProvider_Model();
            $action = $request->get('action_type');

            if ($action === 'delete') {
                $id = (int)$request->get('id');
                if ($id > 0) {
                    $dp->deleteRelatorio($id);
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'ID inválido']);
                }
                exit;
            }

            // save
            $configRaw = $request->get('config');
            $config    = $configRaw ? (json_decode($configRaw, true) ?? []) : [];

            $titulo = trim($request->get('titulo') ?? '');
            if (!$titulo) {
                echo json_encode(['success' => false, 'error' => 'Título obrigatório']);
                exit;
            }

            $newId = $dp->saveRelatorio([
                'id'           => (int)($request->get('id') ?? 0),
                'titulo'       => $titulo,
                'descricao'    => $request->get('descricao') ?? '',
                'modulo_base'  => $request->get('modulo_base') ?? 'Leads',
                'tipo'         => $request->get('tipo') ?? 'summary',
                'compartilhado'=> (int)($request->get('compartilhado') ?? 1),
                'config'       => $config,
            ]);

            echo json_encode(['success' => true, 'id' => $newId], JSON_UNESCAPED_UNICODE);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }

        exit;
    }

    public function postProcess(Vtiger_Request $request): void {}
}
