<?php
// Helper: htmlspecialchars sem double-encode (PearDatabase::fetch_array já aplica htmlentities)
if (!function_exists('pbi_e')) {
    function pbi_e($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8', false);
    }
}

/**
 * PainelBI_DataProvider_Model
 * Toda a lógica de consulta SQL: relatórios, condições, agregações.
 * Schema: vtiger_leaddetails (leadid, firstname, lastname, company, email,
 *         leadstatus, leadsource) + vtiger_crmentity (smownerid, createdtime, deleted)
 *         + vtiger_leadaddress (phone, mobile) + vtiger_users (first_name, last_name, user_name)
 */
class PainelBI_DataProvider_Model {

    private $adb;

    public function __construct() {
        $this->adb = PearDatabase::getInstance();
    }

    // ─── Definição de campos disponíveis por módulo ───────────────────────────

    public static function getLeadsFields(): array {
        return [
            // Campos básicos
            'firstname'      => ['label'=>'Nome',          'type'=>'text',     'sql_s'=>'ld.firstname',     'sql_w'=>'ld.firstname'],
            'lastname'       => ['label'=>'Sobrenome',     'type'=>'text',     'sql_s'=>'ld.lastname',      'sql_w'=>'ld.lastname'],
            'nome_completo'  => ['label'=>'Nome Completo', 'type'=>'text',     'sql_s'=>"CONCAT(COALESCE(ld.firstname,''),' ',ld.lastname)", 'sql_w'=>'ld.lastname', 'no_group'=>true],
            'company'        => ['label'=>'Empresa',       'type'=>'text',     'sql_s'=>'ld.company',       'sql_w'=>'ld.company'],
            'email'          => ['label'=>'E-mail',        'type'=>'text',     'sql_s'=>'ld.email',         'sql_w'=>'ld.email'],
            'phone'          => ['label'=>'Telefone',      'type'=>'text',     'sql_s'=>'la.phone',         'sql_w'=>'la.phone'],
            'mobile'         => ['label'=>'Celular',       'type'=>'text',     'sql_s'=>'la.mobile',        'sql_w'=>'la.mobile'],
            // Status e origem
            'leadstatus'     => ['label'=>'Status',        'type'=>'picklist', 'sql_s'=>"COALESCE(ld.leadstatus,'(sem status)')", 'sql_w'=>'ld.leadstatus', 'picklist'=>'vtiger_leadstatus',  'picklist_col'=>'leadstatus'],
            'leadsource'     => ['label'=>'Origem',        'type'=>'picklist', 'sql_s'=>"COALESCE(NULLIF(ld.leadsource,''),'Não informado')", 'sql_w'=>'ld.leadsource', 'picklist'=>'vtiger_leadsource', 'picklist_col'=>'leadsource'],
            'converted'      => ['label'=>'Convertido',   'type'=>'integer',  'sql_s'=>'ld.converted',     'sql_w'=>'ld.converted'],
            // Atendente
            'atendente'      => ['label'=>'Atendente',    'type'=>'text',     'sql_s'=>"CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))", 'sql_w'=>'u.user_name', 'no_group'=>true],
            'user_name'      => ['label'=>'Login',        'type'=>'text',     'sql_s'=>'u.user_name',      'sql_w'=>'u.user_name'],
            'smownerid'      => ['label'=>'ID Atendente', 'type'=>'integer',  'sql_s'=>'e.smownerid',      'sql_w'=>'e.smownerid'],
            // Tempo
            'createdtime'    => ['label'=>'Criado em',    'type'=>'datetime', 'sql_s'=>'e.createdtime',    'sql_w'=>'e.createdtime'],
            'modifiedtime'   => ['label'=>'Modificado em','type'=>'datetime', 'sql_s'=>'e.modifiedtime',   'sql_w'=>'e.modifiedtime'],
            'data_criacao'   => ['label'=>'Data',         'type'=>'date',     'sql_s'=>'DATE(e.createdtime)',    'sql_w'=>'DATE(e.createdtime)'],
            'mes_criacao'    => ['label'=>'Mês/Ano',      'type'=>'text',     'sql_s'=>"DATE_FORMAT(e.createdtime,'%m/%Y')", 'sql_w'=>"DATE_FORMAT(e.createdtime,'%Y-%m')"],
            'semana_criacao' => ['label'=>'Semana',       'type'=>'text',     'sql_s'=>"YEARWEEK(e.createdtime,1)", 'sql_w'=>"YEARWEEK(e.createdtime,1)"],
            // Outros
            'lead_no'        => ['label'=>'Nº Lead',      'type'=>'text',     'sql_s'=>'ld.lead_no',       'sql_w'=>'ld.lead_no'],
            'interest'       => ['label'=>'Interesse',    'type'=>'text',     'sql_s'=>'ld.interest',      'sql_w'=>'ld.interest'],
        ];
    }

    public static function getModuleFields(string $module = 'Leads'): array {
        return self::getLeadsFields(); // Expandir para outros módulos futuramente
    }

    public static function getAggregateFunctions(): array {
        return ['COUNT'=>'Contagem','SUM'=>'Soma','AVG'=>'Média','MAX'=>'Máximo','MIN'=>'Mínimo'];
    }

    public static function getOperators(): array {
        return [
            'text'     => ['eq'=>'é igual a','neq'=>'é diferente de','contains'=>'contém','not_contains'=>'não contém','starts'=>'começa com','is_empty'=>'está vazio','is_not_empty'=>'não está vazio','in_list'=>'está em'],
            'picklist' => ['eq'=>'é igual a','neq'=>'é diferente de','in_list'=>'está em','not_in_list'=>'não está em','is_empty'=>'está vazio','is_not_empty'=>'não está vazio'],
            'datetime' => ['in_period'=>'no período','between'=>'entre datas','gt'=>'depois de','lt'=>'antes de'],
            'date'     => ['in_period'=>'no período','between'=>'entre datas','gt'=>'depois de','lt'=>'antes de'],
            'integer'  => ['eq'=>'é igual a','neq'=>'diferente de','gt'=>'maior que','lt'=>'menor que','between'=>'entre'],
        ];
    }

    public static function getPeriodOptions(): array {
        return [
            'today'      => 'Hoje',
            'yesterday'  => 'Ontem',
            'this_week'  => 'Esta Semana',
            'last_week'  => 'Semana Passada',
            'this_month' => 'Este Mês',
            'last_month' => 'Mês Passado',
            'last_7'     => 'Últimos 7 Dias',
            'last_30'    => 'Últimos 30 Dias',
            'last_90'    => 'Últimos 90 Dias',
            'this_year'  => 'Este Ano',
        ];
    }

    public static function getChartTypes(): array {
        return [
            'none'    => 'Sem Gráfico',
            'bar'     => 'Barras Verticais',
            'bar_h'   => 'Barras Horizontais',
            'line'    => 'Linhas',
            'area'    => 'Área',
            'pie'     => 'Pizza',
            'doughnut'=> 'Rosca',
        ];
    }

    // ─── FROM base ────────────────────────────────────────────────────────────

    private function getLeadsFrom(): string {
        return "FROM vtiger_leaddetails ld
        JOIN vtiger_crmentity e ON e.crmid = ld.leadid AND e.deleted = 0 AND e.setype = 'Leads'
        LEFT JOIN vtiger_leadaddress la ON la.leadaddressid = ld.leadid
        LEFT JOIN vtiger_users u ON u.id = e.smownerid AND u.deleted = 0";
    }

    // ─── Executar relatório (principal) ──────────────────────────────────────

    public function runReport(array $config): array {
        $tipo      = $config['tipo'] ?? 'detail';
        $colunas   = $config['colunas'] ?? ['nome_completo','leadstatus','atendente','createdtime'];
        $grupo     = $tipo === 'summary' ? ($config['grupo'] ?? null) : null;
        $agregacoes= $tipo === 'summary' ? ($config['agregacoes'] ?? []) : [];
        $condGrupos= $config['condicoes_grupos'] ?? [];
        $ordem     = $config['ordem'] ?? 'createdtime';
        $ordemDir  = strtoupper($config['ordem_dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $limite    = min(max((int)($config['limite'] ?? 500), 1), 5000);

        $fields = self::getLeadsFields();
        $params = [];

        // SELECT
        $selectParts  = [];
        $chaves       = [];
        $labelsArr    = [];
        $aliasAggrMap = [];

        // Colunas (para detail) ou campo grupo (para summary)
        if ($tipo === 'summary' && $grupo && isset($fields[$grupo])) {
            $f = $fields[$grupo];
            $selectParts[] = "{$f['sql_s']} AS `{$grupo}`";
            $chaves[]      = $grupo;
            $labelsArr[]   = $f['label'];
        } elseif ($tipo === 'detail') {
            foreach ($colunas as $col) {
                if (!isset($fields[$col])) continue;
                $f = $fields[$col];
                $selectParts[] = "{$f['sql_s']} AS `{$col}`";
                $chaves[]      = $col;
                $labelsArr[]   = $f['label'];
            }
        }

        // Agregações
        foreach ($agregacoes as $agg) {
            $func  = in_array($agg['func'] ?? 'COUNT', ['COUNT','SUM','AVG','MAX','MIN']) ? $agg['func'] : 'COUNT';
            $campo = $agg['campo'] ?? '*';
            $alias = preg_replace('/[^a-z0-9_]/i','_', $agg['alias'] ?? strtolower($func).'_agg');

            if ($campo === '*' || $func === 'COUNT') {
                $selectParts[] = "COUNT(*) AS `{$alias}`";
                $labelsArr[]   = 'Contagem';
            } elseif (isset($fields[$campo])) {
                $selectParts[] = "{$func}({$fields[$campo]['sql_s']}) AS `{$alias}`";
                $labelsArr[]   = ($func === 'COUNT' ? 'Contagem' : self::getAggregateFunctions()[$func] ?? $func) . ' ' . $fields[$campo]['label'];
            }
            $chaves[]          = $alias;
            $aliasAggrMap[]    = $alias;
        }

        if (empty($selectParts)) {
            $selectParts = ["COUNT(*) AS `total`"];
            $chaves      = ['total'];
            $labelsArr   = ['Total'];
            $aliasAggrMap = ['total'];
        }

        // FROM
        $from = $this->getLeadsFrom();

        // WHERE (condições aninhadas)
        $condSQL = $this->buildConditionsSQL($condGrupos, $fields, $params);

        // GROUP BY
        $groupSQL = '';
        if ($tipo === 'summary' && $grupo && isset($fields[$grupo])) {
            $groupSQL = "GROUP BY {$fields[$grupo]['sql_w']}";
        }

        // ORDER BY (whitelist)
        $orderSQL = 'ORDER BY e.createdtime DESC';
        if (isset($fields[$ordem])) {
            $orderSQL = "ORDER BY {$fields[$ordem]['sql_w']} {$ordemDir}";
        } elseif (in_array($ordem, $aliasAggrMap)) {
            $orderSQL = "ORDER BY `{$ordem}` {$ordemDir}";
        }

        $sql = "SELECT " . implode(', ', $selectParts) . "\n{$from}\n{$condSQL}\n{$groupSQL}\n{$orderSQL}\nLIMIT {$limite}";

        $result = $this->adb->pquery($sql, $params);
        $rows   = [];
        while ($row = $this->adb->fetch_array($result)) {
            $rows[] = $row;
        }

        return [
            'tipo'    => $tipo,
            'chaves'  => $chaves,
            'labels'  => $labelsArr,
            'dados'   => $rows,
            'total'   => count($rows),
            'sql_debug' => $sql, // remover em produção
        ];
    }

    // ─── Builder de condições ─────────────────────────────────────────────────

    private function buildConditionsSQL(array $grupos, array $fields, array &$params): string {
        $grupoParts = [];
        foreach ($grupos as $grupo) {
            $conds = $grupo['condicoes'] ?? [];
            $condParts = [];
            foreach ($conds as $cond) {
                $campo = $cond['campo'] ?? '';
                $op    = $cond['op'] ?? 'eq';
                $valor = $cond['valor'] ?? '';
                if (!isset($fields[$campo])) continue;
                $col   = $fields[$campo]['sql_w'];
                $tipo  = $fields[$campo]['type'];
                $part  = $this->buildSingleCond($col, $op, $valor, $tipo, $params);
                if ($part) $condParts[] = $part;
            }
            if (!empty($condParts)) {
                $grupoParts[] = '(' . implode(' AND ', $condParts) . ')';
            }
        }
        if (empty($grupoParts)) return '';
        return 'WHERE (' . implode(' OR ', $grupoParts) . ')';
    }

    private function buildSingleCond(string $col, string $op, $valor, string $tipo, array &$params): string {
        switch ($op) {
            case 'eq':
                $params[] = $valor;
                return "({$col} = ?)";
            case 'neq':
                $params[] = $valor;
                return "({$col} != ?)";
            case 'contains':
                $params[] = "%{$valor}%";
                return "({$col} LIKE ?)";
            case 'not_contains':
                $params[] = "%{$valor}%";
                return "({$col} NOT LIKE ?)";
            case 'starts':
                $params[] = "{$valor}%";
                return "({$col} LIKE ?)";
            case 'is_empty':
                return "({$col} IS NULL OR {$col} = '')";
            case 'is_not_empty':
                return "({$col} IS NOT NULL AND {$col} != '')";
            case 'in_list':
            case 'not_in_list':
                $vals = is_array($valor) ? $valor : array_filter(array_map('trim', explode(',', (string)$valor)));
                if (empty($vals)) return '';
                $ph = implode(',', array_fill(0, count($vals), '?'));
                foreach ($vals as $v) $params[] = $v;
                $not = $op === 'not_in_list' ? 'NOT ' : '';
                return "({$col} {$not}IN ({$ph}))";
            case 'between':
                $v = is_array($valor) ? $valor : [(string)$valor, (string)$valor];
                $params[] = $v[0] ?? date('Y-m-d');
                $params[] = $v[1] ?? date('Y-m-d');
                if (in_array($tipo, ['datetime','date'])) return "(DATE({$col}) BETWEEN ? AND ?)";
                return "({$col} BETWEEN ? AND ?)";
            case 'gt':
                $params[] = $valor;
                return "({$col} > ?)";
            case 'lt':
                $params[] = $valor;
                return "({$col} < ?)";
            case 'in_period':
                [$d1, $d2] = $this->getPeriodDates((string)$valor);
                $params[] = $d1;
                $params[] = $d2;
                return "(DATE({$col}) BETWEEN ? AND ?)";
            default:
                return '';
        }
    }

    private function getPeriodDates(string $period): array {
        switch ($period) {
            case 'today':      return [date('Y-m-d'), date('Y-m-d')];
            case 'yesterday':  return [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))];
            case 'this_week':  return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week'))];
            case 'last_week':  return [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week'))];
            case 'this_month': return [date('Y-m-01'), date('Y-m-t')];
            case 'last_month': return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))];
            case 'last_7':     return [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')];
            case 'last_30':    return [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')];
            case 'last_90':    return [date('Y-m-d', strtotime('-89 days')), date('Y-m-d')];
            case 'this_year':  return [date('Y-01-01'), date('Y-12-31')];
            default:           return [date('Y-m-d'), date('Y-m-d')];
        }
    }

    // ─── Picklists ────────────────────────────────────────────────────────────

    public function getPicklistValues(string $table, string $col): array {
        $result = $this->adb->pquery("SELECT `{$col}` AS val FROM `{$table}` WHERE presence=1 ORDER BY sortorderid ASC", []);
        $vals = [];
        while ($row = $this->adb->fetch_array($result)) {
            $v = $row['val'] ?? '';
            if ($v !== '') $vals[] = $v;
        }
        return $vals;
    }

    public function getLeadStatuses(): array {
        return $this->getPicklistValues('vtiger_leadstatus', 'leadstatus');
    }

    public function getLeadSources(): array {
        return $this->getPicklistValues('vtiger_leadsource', 'leadsource');
    }

    public function getUsers(): array {
        $result = $this->adb->pquery(
            "SELECT id, user_name, CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) AS nome FROM vtiger_users WHERE deleted=0 AND status='Active' ORDER BY nome", []
        );
        $users = [];
        while ($row = $this->adb->fetch_array($result)) {
            $users[] = $row;
        }
        return $users;
    }

    // ─── CRUD Boards ─────────────────────────────────────────────────────────

    public function getBoards(?int $userId = null): array {
        $userId = $userId ?: Users_Record_Model::getCurrentUserModel()->getId();
        $result = $this->adb->pquery(
            "SELECT * FROM vtiger_painelbi_boards WHERE deleted=0 AND (smownerid=? OR compartilhado=1) ORDER BY compartilhado ASC, id ASC",
            [$userId]
        );
        $boards = [];
        while ($row = $this->adb->fetch_array($result)) {
            $boards[] = $row;
        }
        return $boards;
    }

    public function getBoardTabs(int $boardId): array {
        $result = $this->adb->pquery(
            "SELECT * FROM vtiger_painelbi_tabs WHERE board_id=? AND deleted=0 ORDER BY sequencia ASC, id ASC",
            [$boardId]
        );
        $tabs = [];
        while ($row = $this->adb->fetch_array($result)) {
            $tabs[] = $row;
        }
        return $tabs;
    }

    public function getTabWidgets(int $tabId): array {
        $result = $this->adb->pquery(
            "SELECT w.*, r.titulo, r.modulo_base, r.tipo, r.config
             FROM vtiger_painelbi_widgets w
             JOIN vtiger_painelbi_relatorios r ON r.id = w.relatorio_id AND r.deleted = 0
             WHERE w.tab_id=? AND w.deleted=0
             ORDER BY w.sequencia ASC, w.id ASC",
            [$tabId]
        );
        $widgets = [];
        while ($row = $this->adb->fetch_array($result)) {
            $widgets[] = $row;
        }
        return $widgets;
    }

    // ─── CRUD Relatórios ─────────────────────────────────────────────────────

    public function getRelatorios(bool $includePrivate = true): array {
        $userId = Users_Record_Model::getCurrentUserModel()->getId();
        $sql = "SELECT * FROM vtiger_painelbi_relatorios WHERE deleted=0 AND (compartilhado=1 OR smownerid=?) ORDER BY titulo ASC";
        $result = $this->adb->pquery($sql, [$userId]);
        $relatorios = [];
        while ($row = $this->adb->fetch_array($result)) {
            $relatorios[] = $row;
        }
        return $relatorios;
    }

    public function getRelatorio(int $id): ?array {
        $result = $this->adb->pquery("SELECT * FROM vtiger_painelbi_relatorios WHERE id=? AND deleted=0", [$id]);
        $row = $this->adb->fetch_array($result);
        return $row ?: null;
    }

    public function saveRelatorio(array $data): int {
        $now    = date('Y-m-d H:i:s');
        $userId = Users_Record_Model::getCurrentUserModel()->getId();
        $config = json_encode($data['config'] ?? [], JSON_UNESCAPED_UNICODE);
        $id     = (int)($data['id'] ?? 0);

        if ($id > 0) {
            $this->adb->pquery(
                "UPDATE vtiger_painelbi_relatorios SET titulo=?,descricao=?,modulo_base=?,tipo=?,config=?,compartilhado=?,modifiedtime=? WHERE id=?",
                [$data['titulo'],$data['descricao']??'',$data['modulo_base']??'Leads',$data['tipo']??'summary',$config,(int)($data['compartilhado']??1),$now,$id]
            );
            return $id;
        }

        $this->adb->pquery(
            "INSERT INTO vtiger_painelbi_relatorios (titulo,descricao,modulo_base,tipo,config,compartilhado,smownerid,createdtime,modifiedtime,deleted) VALUES (?,?,?,?,?,?,?,?,?,0)",
            [$data['titulo'],$data['descricao']??'',$data['modulo_base']??'Leads',$data['tipo']??'summary',$config,(int)($data['compartilhado']??1),$userId,$now,$now]
        );
        return (int)$this->adb->getLastInsertID('vtiger_painelbi_relatorios','id');
    }

    public function deleteRelatorio(int $id): void {
        $this->adb->pquery("UPDATE vtiger_painelbi_relatorios SET deleted=1 WHERE id=?", [$id]);
        $this->adb->pquery("UPDATE vtiger_painelbi_widgets SET deleted=1 WHERE relatorio_id=?", [$id]);
    }

    // ─── CRUD Boards/Tabs/Widgets ─────────────────────────────────────────────

    public function saveBoard(array $data): int {
        $now    = date('Y-m-d H:i:s');
        $userId = Users_Record_Model::getCurrentUserModel()->getId();
        $id     = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $this->adb->pquery("UPDATE vtiger_painelbi_boards SET titulo=?,compartilhado=? WHERE id=?",
                [$data['titulo'],(int)($data['compartilhado']??0),$id]);
            return $id;
        }
        $this->adb->pquery("INSERT INTO vtiger_painelbi_boards (titulo,compartilhado,smownerid,createdtime,deleted) VALUES (?,?,?,?,0)",
            [$data['titulo'],(int)($data['compartilhado']??0),$userId,$now]);
        return (int)$this->adb->getLastInsertID('vtiger_painelbi_boards','id');
    }

    public function saveTab(array $data): int {
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            $this->adb->pquery("UPDATE vtiger_painelbi_tabs SET titulo=? WHERE id=?", [$data['titulo'],$id]);
            return $id;
        }
        $seq = (int)$this->adb->query_result(
            $this->adb->pquery("SELECT COUNT(*) AS c FROM vtiger_painelbi_tabs WHERE board_id=? AND deleted=0",[$data['board_id']]),
            0, 'c'
        );
        $this->adb->pquery("INSERT INTO vtiger_painelbi_tabs (board_id,titulo,sequencia,deleted) VALUES (?,?,?,0)",
            [$data['board_id'],$data['titulo'],$seq]);
        return (int)$this->adb->getLastInsertID('vtiger_painelbi_tabs','id');
    }

    public function deleteTab(int $id): void {
        $this->adb->pquery("UPDATE vtiger_painelbi_tabs SET deleted=1 WHERE id=?", [$id]);
        $this->adb->pquery("UPDATE vtiger_painelbi_widgets SET deleted=1 WHERE tab_id=?", [$id]);
    }

    public function deleteBoard(int $id): void {
        $tabs = $this->getBoardTabs($id);
        foreach ($tabs as $t) $this->deleteTab((int)$t['id']);
        $this->adb->pquery("UPDATE vtiger_painelbi_boards SET deleted=1 WHERE id=?", [$id]);
    }

    public function addWidget(int $tabId, int $relatorioId, int $largura = 6): int {
        $seq = (int)$this->adb->query_result(
            $this->adb->pquery("SELECT COUNT(*) AS c FROM vtiger_painelbi_widgets WHERE tab_id=? AND deleted=0",[$tabId]),
            0, 'c'
        );
        $this->adb->pquery("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES (?,?,?,?,0)",
            [$tabId,$relatorioId,$largura,$seq]);
        return (int)$this->adb->getLastInsertID('vtiger_painelbi_widgets','id');
    }

    public function removeWidget(int $widgetId): void {
        $this->adb->pquery("UPDATE vtiger_painelbi_widgets SET deleted=1 WHERE id=?", [$widgetId]);
    }

    // ─── Dados do dashboard: KPIs rápidos ────────────────────────────────────

    public function getKPIs(): array {
        $from = $this->getLeadsFrom();

        $hoje  = $this->adb->query_result($this->adb->pquery("SELECT COUNT(*) AS c {$from} WHERE DATE(e.createdtime)=CURDATE()", []), 0, 'c');
        $mes   = $this->adb->query_result($this->adb->pquery("SELECT COUNT(*) AS c {$from} WHERE DATE_FORMAT(e.createdtime,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')", []), 0, 'c');
        $total = $this->adb->query_result($this->adb->pquery("SELECT COUNT(*) AS c {$from}", []), 0, 'c');

        // Status "Aguardando Atendimento" ou equivalentes
        $aguardando = $this->adb->query_result(
            $this->adb->pquery("SELECT COUNT(*) AS c {$from} WHERE ld.leadstatus IN ('Aguardando Atendimento','Em Atendimento')", []), 0, 'c'
        );

        return [
            'hoje'       => (int)$hoje,
            'mes'        => (int)$mes,
            'total'      => (int)$total,
            'aguardando' => (int)$aguardando,
        ];
    }

    // ─── Preparar dados para Chart.js ────────────────────────────────────────

    public static function prepareChartData(array $chartConfig, array $reportData): array {
        $tipo        = $chartConfig['tipo'] ?? 'bar';
        $campoLabel  = $chartConfig['campo_label'] ?? '';
        $camposDados = $chartConfig['campos_dados'] ?? [];

        if ($tipo === 'none' || empty($reportData['dados']) || empty($campoLabel)) {
            return ['tipo' => 'none'];
        }

        $labels   = [];
        $datasets = [];

        foreach ($reportData['dados'] as $row) {
            $labels[] = $row[$campoLabel] ?? '(sem valor)';
        }

        $colors = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#34495e','#e91e63','#00bcd4'];

        foreach ($camposDados as $idx => $campo) {
            $data = [];
            foreach ($reportData['dados'] as $row) {
                $data[] = is_numeric($row[$campo] ?? null) ? (float)$row[$campo] : 0;
            }

            $labelIdx = array_search($campo, $reportData['chaves'] ?? []);
            $dsLabel  = $reportData['labels'][$labelIdx] ?? $campo;
            $color    = $colors[$idx % count($colors)];

            $ds = ['label' => $dsLabel, 'data' => $data, 'backgroundColor' => $color, 'borderColor' => $color];

            if (in_array($tipo, ['line','area'])) {
                $ds['fill']        = $tipo === 'area';
                $ds['tension']     = 0.3;
                $ds['borderWidth'] = 2;
                if ($tipo === 'area') {
                    $hex = ltrim($color, '#');
                    $rgb = array_map('hexdec', str_split($hex, 2));
                    $ds['backgroundColor'] = "rgba({$rgb[0]},{$rgb[1]},{$rgb[2]},0.2)";
                } else {
                    $ds['backgroundColor'] = $color;
                }
            }

            if (in_array($tipo, ['pie','doughnut'])) {
                $ds['backgroundColor'] = array_map(fn($i) => $colors[$i % count($colors)], range(0, count($data)-1));
            }

            $datasets[] = $ds;
        }

        return [
            'tipo'    => $tipo === 'bar_h' ? 'bar' : $tipo,
            'is_h'    => $tipo === 'bar_h',
            'labels'  => $labels,
            'datasets'=> $datasets,
            'options' => [
                'mostrar_grid'    => $chartConfig['mostrar_grid'] ?? true,
                'mostrar_label'   => $chartConfig['mostrar_label'] ?? true,
                'mostrar_legenda' => $chartConfig['mostrar_legenda'] ?? true,
                'posicao_legenda' => $chartConfig['posicao_legenda'] ?? 'top',
            ],
        ];
    }

    // ─── Utilitários ────────────────────────────────────────────────────────

    /** Retorna o cvid da CustomView "All" para um módulo (drill-down) */
    public function getAllViewId(string $module = 'Leads'): int {
        $row = $this->adb->fetch_array(
            $this->adb->pquery("SELECT cvid FROM vtiger_customview WHERE entitytype=? AND viewname='All' LIMIT 1", [$module])
        );
        return $row ? (int)$row['cvid'] : 0;
    }
}
