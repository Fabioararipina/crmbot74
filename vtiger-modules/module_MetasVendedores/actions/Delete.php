<?php
/**
 * MetasVendedores_Delete_Action — deleção lógica de uma meta
 */
class MetasVendedores_Delete_Action extends Vtiger_Action_Controller {

    public function requiresPermission(\Vtiger_Request $request): array {
        return [];
    }

    public function checkPermission($request): bool {
        return true;
    }

    public function process(Vtiger_Request $request): void {
        // Somente admin pode excluir metas
        $currentUser = Users_Record_Model::getCurrentUserModel();
        if ($currentUser->get('is_admin') !== 'on') {
            header('Location: index.php?module=MetasVendedores&view=List');
            exit;
        }
        require_once 'modules/MetasVendedores/models/Record.php';

        $id     = (int) $request->get('record');
        $record = MetasVendedores_Record_Model::getById($id);
        if ($record) {
            $record->delete();
        }

        header('Location: index.php?module=MetasVendedores&view=List');
        exit;
    }
}
