<?php
/**
 * MetasVendedores_Picklist_Model
 *
 * vTiger 8.4 NÃO usa vtiger_picklistvalues (tabela central).
 * Cada picklist tem sua própria tabela: vtiger_{fieldname}
 * com coluna {fieldname}, sortorderid, presence.
 *
 * Mapeamento confirmado via DESCRIBE no banco local:
 *   leadstatus       → vtiger_leadstatus.leadstatus
 *   sales_stage      → vtiger_sales_stage.sales_stage
 *   opportunity_type → vtiger_opportunity_type.opportunity_type
 */
class MetasVendedores_Picklist_Model {

    private static $cache = [];

    /**
     * Lê valores de uma tabela individual de picklist (padrão vTiger 8.4).
     * WHERE presence = 1 → exclui valores desativados pelo admin.
     */
    private static function getValuesFromTable(string $table, string $column): array {
        $key = $table . '.' . $column;
        if (isset(self::$cache[$key])) return self::$cache[$key];

        $adb = PearDatabase::getInstance();
        $sql = "SELECT `$column` AS valor FROM `$table` WHERE presence = 1 ORDER BY sortorderid ASC";
        $result = $adb->query($sql);

        $values = [];
        if ($result) {
            $n = $adb->num_rows($result);
            for ($i = 0; $i < $n; $i++) {
                $row = $adb->raw_query_result_rowdata($result, $i);
                $values[] = $row['valor'];
            }
        }

        self::$cache[$key] = $values;
        return $values;
    }

    /**
     * Estágios do funil de Leads
     * vtiger_field.fieldname = 'leadstatus' (Leads, uitype=15)
     * Tabela: vtiger_leadstatus  Coluna: leadstatus
     */
    public static function getLeadStatuses(): array {
        return self::getValuesFromTable('vtiger_leadstatus', 'leadstatus');
    }

    /**
     * Estágios de venda das Oportunidades
     * vtiger_field.fieldname = 'sales_stage' (Potentials, uitype=15)
     * Tabela: vtiger_sales_stage  Coluna: sales_stage
     */
    public static function getSalesStages(): array {
        return self::getValuesFromTable('vtiger_sales_stage', 'sales_stage');
    }

    /**
     * Tipos de oportunidade
     * vtiger_field.fieldname = 'opportunity_type' (Potentials, uitype=15)
     * Tabela: vtiger_opportunity_type  Coluna: opportunity_type
     * NOTA: o campo se chama 'opportunity_type' no vTiger 8.4, não 'type'
     */
    public static function getOpportunityTypes(): array {
        return self::getValuesFromTable('vtiger_opportunity_type', 'opportunity_type');
    }

    /** Equipes (vtiger_groups nativos — chamados "Equipe" na UI) */
    public static function getEquipes(): array {
        $key = 'equipes';
        if (isset(self::$cache[$key])) return self::$cache[$key];

        $adb = PearDatabase::getInstance();
        // vtiger_groups pode não ter coluna 'deleted' — query sem WHERE
        $result = $adb->query(
            "SELECT groupid, groupname FROM vtiger_groups ORDER BY groupname ASC"
        );
        $equipes = [];
        if ($result) {
            $n = $adb->num_rows($result);
            for ($i = 0; $i < $n; $i++) {
                $row = $adb->raw_query_result_rowdata($result, $i);
                $equipes[] = ['id' => $row['groupid'], 'nome' => $row['groupname']];
            }
        }
        self::$cache[$key] = $equipes;
        return $equipes;
    }

    /** Vendedores ativos de uma equipe específica (ou todos se $equipeId = null) */
    public static function getVendedores(?int $equipeId = null): array {
        $key = 'vendedores_' . ($equipeId ?? 'all');
        if (isset(self::$cache[$key])) return self::$cache[$key];

        $adb = PearDatabase::getInstance();
        if ($equipeId) {
            $result = $adb->pquery(
                "SELECT u.id, u.first_name, u.last_name, u.user_name
                 FROM vtiger_users u
                 INNER JOIN vtiger_group2users g2u ON g2u.userid = u.id
                 WHERE g2u.groupid = ? AND u.deleted = 0 AND u.status = 'Active'
                 ORDER BY u.first_name, u.last_name",
                [$equipeId]
            );
        } else {
            $result = $adb->query(
                "SELECT id, first_name, last_name, user_name
                 FROM vtiger_users
                 WHERE deleted = 0 AND status = 'Active' AND is_admin IN ('off','0')
                 ORDER BY first_name, last_name"
            );
        }

        $vendedores = [];
        if ($result) {
            $n = $adb->num_rows($result);
            for ($i = 0; $i < $n; $i++) {
                $row = $adb->raw_query_result_rowdata($result, $i);
                $nome = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                if (empty($nome)) $nome = $row['user_name'];
                $vendedores[] = ['id' => $row['id'], 'nome' => $nome];
            }
        }
        self::$cache[$key] = $vendedores;
        return $vendedores;
    }

    /** Mapa equipeId → [vendedores] para uso no JS do formulário (JSON) */
    public static function getEquipeVendedoresMap(): array {
        $equipes = self::getEquipes();
        $map = [];
        foreach ($equipes as $eq) {
            $map[$eq['id']] = self::getVendedores((int)$eq['id']);
        }
        return $map;
    }
}
