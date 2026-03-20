<?php
/**
 * MetasVendedores_Record_Model
 * CRUD + cálculos de progresso em tempo real.
 */
class MetasVendedores_Record_Model {

    protected array $data = [];

    public function set(string $key, $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key) {
        return $this->data[$key] ?? null;
    }

    public function getData(): array {
        return $this->data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CÁLCULO — OPORTUNIDADES
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcula realizado vs meta para uma meta de oportunidades.
     * Usa closingdate (data de fechamento) como referência de período.
     */
    public function calcularProgressoOportunidades(): array {
        $adb         = PearDatabase::getInstance();
        $usuarioId   = $this->get('usuario_id');
        $equipeId    = $this->get('equipe_id');
        $tipoProduto = $this->get('tipo_produto');
        $salesStage  = $this->get('sales_stage_alvo') ?: 'Closed Won';
        $inicio      = $this->get('periodo_inicio');
        $fim         = $this->get('periodo_fim');

        [$whereUser, $params] = $this->_buildUserWhere($usuarioId, $equipeId);

        $whereTipo = '';
        if ($tipoProduto) {
            $whereTipo = " AND p.potentialtype = ?";
            $params[]  = $tipoProduto;
        }

        $params = array_merge([$salesStage], $params, [$inicio, $fim]);

        $sql = "SELECT COUNT(*) AS qtd, COALESCE(SUM(p.amount), 0) AS valor
                FROM vtiger_potential p
                INNER JOIN vtiger_crmentity ce ON ce.crmid = p.potentialid
                WHERE ce.deleted = 0
                  AND p.sales_stage = ?
                  {$whereUser}
                  {$whereTipo}
                  AND p.closingdate BETWEEN ? AND ?";

        error_log("[MetasVendedores] OPR SQL: $sql | params: " . json_encode($params));
        $result = $adb->pquery($sql, $params);
        if (!is_object($result)) {
            error_log("[MetasVendedores] OPR query FAILED, result=" . gettype($result));
            return ['qtd_realizada'=>0,'valor_realizado'=>0,'meta_quantidade'=>(int)$this->get('meta_quantidade'),'meta_valor'=>(float)$this->get('meta_valor'),'pct_quantidade'=>0,'pct_valor'=>0];
        }
        $row = $adb->raw_query_result_rowdata($result, 0);

        $qtdRealizada    = (int)   ($row['qtd']   ?? 0);
        $valorRealizado  = (float) ($row['valor']  ?? 0);
        $metaQtd         = (int)   $this->get('meta_quantidade');
        $metaValor       = (float) $this->get('meta_valor');

        return [
            'qtd_realizada'   => $qtdRealizada,
            'valor_realizado' => $valorRealizado,
            'meta_quantidade' => $metaQtd,
            'meta_valor'      => $metaValor,
            'pct_quantidade'  => $metaQtd   > 0 ? min(100, round($qtdRealizada   / $metaQtd   * 100, 1)) : 0,
            'pct_valor'       => $metaValor > 0 ? min(100, round($valorRealizado / $metaValor * 100, 1)) : 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CÁLCULO — FUNIL DE LEADS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Calcula taxa de conversão e quantidade para uma meta de funil de leads.
     * Usa createdtime como referência de período.
     */
    public function calcularProgressoFunil(): array {
        $adb            = PearDatabase::getInstance();
        $usuarioId      = $this->get('usuario_id');
        $equipeId       = $this->get('equipe_id');
        $estagioOrigem  = $this->get('estagio_origem');
        $estagioDestino = $this->get('estagio_destino');
        $inicio         = $this->get('periodo_inicio');
        $fim            = $this->get('periodo_fim');

        [$whereUser, $paramsBase] = $this->_buildUserWhere($usuarioId, $equipeId);

        $totalOrigem  = 0;
        $totalDestino = 0;

        if ($estagioOrigem) {
            $params  = array_merge([$estagioOrigem], $paramsBase, [$inicio . ' 00:00:00', $fim . ' 23:59:59']);
            $sql     = "SELECT COUNT(*) AS total
                        FROM vtiger_leaddetails ld
                        INNER JOIN vtiger_crmentity ce ON ce.crmid = ld.leadid
                        WHERE ce.deleted = 0 AND ld.lead_status = ? {$whereUser}
                          AND ce.createdtime BETWEEN ? AND ?";
            error_log("[MetasVendedores] FUNIL-orig SQL: $sql | params: " . json_encode($params));
            $result  = $adb->pquery($sql, $params);
            if (is_object($result)) {
                $totalOrigem = (int)($adb->raw_query_result_rowdata($result, 0)['total'] ?? 0);
            }
        }

        if ($estagioDestino) {
            $params  = array_merge([$estagioDestino], $paramsBase, [$inicio . ' 00:00:00', $fim . ' 23:59:59']);
            $sql     = "SELECT COUNT(*) AS total
                        FROM vtiger_leaddetails ld
                        INNER JOIN vtiger_crmentity ce ON ce.crmid = ld.leadid
                        WHERE ce.deleted = 0 AND ld.lead_status = ? {$whereUser}
                          AND ce.createdtime BETWEEN ? AND ?";
            error_log("[MetasVendedores] FUNIL-dest SQL: $sql | params: " . json_encode($params));
            $result  = $adb->pquery($sql, $params);
            if (is_object($result)) {
                $totalDestino = (int)($adb->raw_query_result_rowdata($result, 0)['total'] ?? 0);
            }
        }

        $taxaReal = $totalOrigem > 0 ? round($totalDestino / $totalOrigem * 100, 1) : 0;
        $metaTaxa = (float) $this->get('meta_taxa_conversao');
        $metaQtd  = (int)   $this->get('meta_quantidade_funil');

        return [
            'total_origem'  => $totalOrigem,
            'total_destino' => $totalDestino,
            'taxa_real'     => $taxaReal,
            'meta_taxa'     => $metaTaxa,
            'meta_qtd'      => $metaQtd,
            'pct_taxa'      => $metaTaxa > 0 ? min(100, round($taxaReal      / $metaTaxa * 100, 1)) : 0,
            'pct_qtd'       => $metaQtd  > 0 ? min(100, round($totalDestino / $metaQtd  * 100, 1)) : 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD
    // ─────────────────────────────────────────────────────────────────────────

    public static function getAll(array $filters = []): array {
        $adb    = PearDatabase::getInstance();
        $where  = "WHERE deleted = 0";
        $params = [];

        if (!empty($filters['secao']))         { $where .= " AND secao = ?";          $params[] = $filters['secao']; }
        if (!empty($filters['equipe_id']))     { $where .= " AND equipe_id = ?";      $params[] = $filters['equipe_id']; }
        if (!empty($filters['usuario_id']))    { $where .= " AND usuario_id = ?";     $params[] = $filters['usuario_id']; }
        if (!empty($filters['periodo_inicio'])){ $where .= " AND periodo_fim >= ?";   $params[] = $filters['periodo_inicio']; }
        if (!empty($filters['periodo_fim']))   { $where .= " AND periodo_inicio <= ?";$params[] = $filters['periodo_fim']; }

        $sql    = "SELECT * FROM vtiger_metasvendedores {$where}
                   ORDER BY equipe_nome, usuario_nome, tipo_produto, titulo";
        $result = $adb->pquery($sql, $params);

        $metas = [];
        $rows  = $adb->num_rows($result);
        for ($i = 0; $i < $rows; $i++) {
            $m       = new self();
            $m->data = $adb->raw_query_result_rowdata($result, $i);
            $metas[] = $m;
        }
        return $metas;
    }

    public static function getById(int $id): ?self {
        $adb    = PearDatabase::getInstance();
        $result = $adb->pquery(
            "SELECT * FROM vtiger_metasvendedores WHERE id = ? AND deleted = 0", [$id]
        );
        if ($adb->num_rows($result) > 0) {
            $m       = new self();
            $m->data = $adb->raw_query_result_rowdata($result, 0);
            return $m;
        }
        return null;
    }

    public function save(): int {
        $adb = PearDatabase::getInstance();
        $id  = (int) $this->get('id');

        $cols = [
            'titulo','secao','equipe_id','equipe_nome','usuario_id','usuario_nome',
            'periodo_inicio','periodo_fim','tipo_produto','sales_stage_alvo',
            'meta_valor','meta_quantidade',
            'estagio_origem','estagio_destino','meta_taxa_conversao','meta_quantidade_funil'
        ];
        $vals = array_map(fn($c) => $this->get($c) ?? null, $cols);

        if ($id === 0) {
            $colStr   = implode(', ', $cols) . ', createdtime, modifiedtime';
            $phStr    = implode(', ', array_fill(0, count($cols), '?')) . ', NOW(), NOW()';
            $adb->pquery("INSERT INTO vtiger_metasvendedores ({$colStr}) VALUES ({$phStr})", $vals);
            $id = (int)$adb->getLastInsertID();
            $this->set('id', $id);
        } else {
            $setStr = implode(' = ?, ', $cols) . ' = ?, modifiedtime = NOW()';
            $adb->pquery(
                "UPDATE vtiger_metasvendedores SET {$setStr} WHERE id = ?",
                array_merge($vals, [$id])
            );
        }
        return $id;
    }

    public function delete(): void {
        $adb = PearDatabase::getInstance();
        $adb->pquery(
            "UPDATE vtiger_metasvendedores SET deleted = 1, modifiedtime = NOW() WHERE id = ?",
            [$this->get('id')]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DASHBOARD CONSOLIDADO
    // Retorna estrutura: [equipes => [nome, vendedores => [nome, metas, total]], total_org]
    // ─────────────────────────────────────────────────────────────────────────

    public static function getConsolidado(array $filters = []): array {
        $metas = self::getAll($filters);
        $data  = [
            'equipes'   => [],
            'total_org' => ['valor' => 0, 'valor_realizado' => 0, 'qtd' => 0, 'qtd_realizada' => 0],
        ];

        foreach ($metas as $meta) {
            $eqId   = $meta->get('equipe_id')   ?: 'sem_equipe';
            $eqNome = $meta->get('equipe_nome') ?: 'Sem Equipe';
            $uId    = $meta->get('usuario_id')   ?: 'equipe';
            $uNome  = $meta->get('usuario_nome') ?: 'Equipe Toda';
            $secao  = $meta->get('secao');

            if (!isset($data['equipes'][$eqId])) {
                $data['equipes'][$eqId] = [
                    'id' => $eqId, 'nome' => $eqNome, 'vendedores' => [],
                    'total' => ['valor' => 0, 'valor_realizado' => 0, 'qtd' => 0, 'qtd_realizada' => 0],
                ];
            }
            if (!isset($data['equipes'][$eqId]['vendedores'][$uId])) {
                $data['equipes'][$eqId]['vendedores'][$uId] = [
                    'id' => $uId, 'nome' => $uNome, 'metas' => [],
                    'total' => ['valor' => 0, 'valor_realizado' => 0, 'qtd' => 0, 'qtd_realizada' => 0],
                ];
            }

            if ($secao === 'oportunidades') {
                $prog = $meta->calcularProgressoOportunidades();

                $mv = (float)$meta->get('meta_valor');
                $mq = (int)  $meta->get('meta_quantidade');
                $vr = (float)$prog['valor_realizado'];
                $qr = (int)  $prog['qtd_realizada'];

                $data['equipes'][$eqId]['vendedores'][$uId]['total']['valor']           += $mv;
                $data['equipes'][$eqId]['vendedores'][$uId]['total']['valor_realizado'] += $vr;
                $data['equipes'][$eqId]['vendedores'][$uId]['total']['qtd']             += $mq;
                $data['equipes'][$eqId]['vendedores'][$uId]['total']['qtd_realizada']   += $qr;
                $data['equipes'][$eqId]['total']['valor']           += $mv;
                $data['equipes'][$eqId]['total']['valor_realizado'] += $vr;
                $data['total_org']['valor']           += $mv;
                $data['total_org']['valor_realizado'] += $vr;
                $data['total_org']['qtd']             += $mq;
                $data['total_org']['qtd_realizada']   += $qr;
            } else {
                $prog = $meta->calcularProgressoFunil();
            }

            $data['equipes'][$eqId]['vendedores'][$uId]['metas'][] = [
                'meta'     => $meta->getData(),
                'progresso'=> $prog,
                'secao'    => $secao,
            ];
        }

        return $data;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS INTERNOS
    // ─────────────────────────────────────────────────────────────────────────

    private function _buildUserWhere(?int $usuarioId, ?int $equipeId): array {
        if ($usuarioId) {
            return [" AND ce.smownerid = ?", [$usuarioId]];
        }
        if ($equipeId) {
            return [
                " AND ce.smownerid IN (SELECT userid FROM vtiger_users2group WHERE groupid = ?)",
                [$equipeId],
            ];
        }
        return ['', []];
    }
}
