<?php
/**
 * MetasVendedores_Save_Action — processa o POST do formulário de meta
 */
class MetasVendedores_Save_Action extends Vtiger_Save_Action {

    public function requiresPermission(\Vtiger_Request $request): array {
        return [];
    }

    public function checkPermission($request): bool {
        return true;
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/MetasVendedores/models/Record.php';
        require_once 'modules/MetasVendedores/models/Picklist.php';

        $id     = (int) $request->get('record');
        $record = $id > 0 ? (MetasVendedores_Record_Model::getById($id) ?? new MetasVendedores_Record_Model()) : new MetasVendedores_Record_Model();
        if ($id > 0) $record->set('id', $id);

        $secao = $request->get('secao') ?: 'oportunidades';
        $record->set('secao', $secao);
        $record->set('titulo',        trim($request->get('titulo')));
        $record->set('periodo_inicio',$request->get('periodo_inicio'));
        $record->set('periodo_fim',   $request->get('periodo_fim'));

        // Equipe
        $equipeId = (int) $request->get('equipe_id');
        $record->set('equipe_id', $equipeId ?: null);
        if ($equipeId) {
            $adb = PearDatabase::getInstance();
            $r   = $adb->pquery("SELECT groupname FROM vtiger_groups WHERE groupid = ?", [$equipeId]);
            $record->set('equipe_nome', $adb->num_rows($r) > 0 ? $adb->query_result($r, 0, 'groupname') : '');
        } else {
            $record->set('equipe_nome', '');
        }

        // Vendedor
        $usuarioId = (int) $request->get('usuario_id');
        $record->set('usuario_id', $usuarioId ?: null);
        if ($usuarioId) {
            $adb = PearDatabase::getInstance();
            $r   = $adb->pquery("SELECT first_name, last_name, user_name FROM vtiger_users WHERE id = ?", [$usuarioId]);
            if ($adb->num_rows($r) > 0) {
                $row  = $adb->query_result_rowdata($r, 0);
                $nome = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                $record->set('usuario_nome', $nome ?: $row['user_name']);
            }
        } else {
            $record->set('usuario_nome', '');
        }

        if ($secao === 'oportunidades') {
            $record->set('tipo_produto',    $request->get('tipo_produto'));
            $record->set('sales_stage_alvo',$request->get('sales_stage_alvo') ?: 'Closed Won');
            $record->set('meta_valor',      (float) str_replace(',', '.', $request->get('meta_valor')));
            $record->set('meta_quantidade', (int) $request->get('meta_quantidade'));
            // Limpar campos funil
            $record->set('estagio_origem', null);
            $record->set('estagio_destino', null);
            $record->set('meta_taxa_conversao', null);
            $record->set('meta_quantidade_funil', 0);
        } else {
            $record->set('estagio_origem',       $request->get('estagio_origem'));
            $record->set('estagio_destino',      $request->get('estagio_destino'));
            $record->set('meta_taxa_conversao',  (float) str_replace(',', '.', $request->get('meta_taxa_conversao')));
            $record->set('meta_quantidade_funil',(int) $request->get('meta_quantidade_funil'));
            // Limpar campos oportunidades
            $record->set('tipo_produto', null);
            $record->set('sales_stage_alvo', null);
            $record->set('meta_valor', 0);
            $record->set('meta_quantidade', 0);
        }

        $newId = $record->save();
        header("Location: index.php?module=MetasVendedores&view=Detail&record={$newId}");
        exit;
    }
}
