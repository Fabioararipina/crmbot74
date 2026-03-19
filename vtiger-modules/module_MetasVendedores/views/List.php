<?php
/**
 * MetasVendedores_List_View — Lista todas as metas com barras de progresso
 */
class MetasVendedores_List_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/MetasVendedores/models/Record.php';
        require_once 'modules/MetasVendedores/models/Picklist.php';

        $filters = [
            'secao'      => $request->get('secao'),
            'equipe_id'  => $request->get('equipe_id'),
            'usuario_id' => $request->get('usuario_id'),
            'periodo_inicio' => $request->get('fi') ?: date('Y-m-01'),
            'periodo_fim'    => $request->get('ff') ?: date('Y-m-t'),
        ];

        $metas   = MetasVendedores_Record_Model::getAll($filters);
        $equipes = MetasVendedores_Picklist_Model::getEquipes();

        $fi = $filters['periodo_inicio'];
        $ff = $filters['periodo_fim'];
        $secaoFiltro = $filters['secao'];

        $currentUser = Users_Record_Model::getCurrentUserModel();
        $isAdmin = ($currentUser->get('is_admin') === 'on');

        echo $this->_css();
        ?>
        <div class="mv-container">

            <!-- Filtros -->
            <div class="mv-filters panel panel-default">
                <div class="panel-body">
                    <form method="GET" class="form-inline" id="mv-filter-form">
                        <input type="hidden" name="module" value="MetasVendedores">
                        <input type="hidden" name="view"   value="List">

                        <div class="form-group">
                            <label>Período:</label>
                            <input type="date" name="fi" class="form-control input-sm" value="<?= htmlspecialchars($fi) ?>">
                            <span>até</span>
                            <input type="date" name="ff" class="form-control input-sm" value="<?= htmlspecialchars($ff) ?>">
                        </div>

                        <div class="form-group">
                            <label>Seção:</label>
                            <select name="secao" class="form-control input-sm">
                                <option value="">Todas</option>
                                <option value="oportunidades" <?= $secaoFiltro === 'oportunidades' ? 'selected' : '' ?>>Oportunidades</option>
                                <option value="funil"         <?= $secaoFiltro === 'funil'         ? 'selected' : '' ?>>Funil de Leads</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Equipe:</label>
                            <select name="equipe_id" class="form-control input-sm">
                                <option value="">Todas</option>
                                <?php foreach ($equipes as $eq): ?>
                                    <option value="<?= $eq['id'] ?>" <?= (string)($filters['equipe_id']) === (string)$eq['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eq['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-filter"></i> Filtrar</button>
                        <?php if ($isAdmin): ?>
                        &nbsp;
                        <a href="index.php?module=MetasVendedores&view=Edit" class="btn btn-sm btn-success"><i class="fa fa-plus"></i> Nova Meta</a>
                        <?php endif; ?>
                        &nbsp;
                        <a href="index.php?module=MetasVendedores&view=Dashboard&fi=<?= $fi ?>&ff=<?= $ff ?>" class="btn btn-sm btn-primary"><i class="fa fa-bar-chart"></i> Dashboard</a>
                    </form>
                </div>
            </div>

            <!-- Tabela -->
            <?php if (empty($metas)): ?>
                <div class="alert alert-info"><i class="fa fa-info-circle"></i> Nenhuma meta encontrada para os filtros selecionados.</div>
            <?php else: ?>
            <table class="table table-bordered table-hover mv-table">
                <thead>
                    <tr class="mv-thead">
                        <th>Título</th>
                        <th>Seção</th>
                        <th>Equipe</th>
                        <th>Vendedor</th>
                        <th>Tipo / Estágios</th>
                        <th>Período</th>
                        <th style="width:200px">Progresso Quantidade</th>
                        <th style="width:200px">Progresso Valor / Taxa</th>
                        <?php if ($isAdmin): ?><th>Ações</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($metas as $meta): ?>
                    <?php
                    $secao = $meta->get('secao');
                    try {
                        if ($secao === 'oportunidades') {
                            $prog = $meta->calcularProgressoOportunidades();
                            $pctQtd   = $prog['pct_quantidade'];
                            $pctValor = $prog['pct_valor'];
                            $labelQtd   = $prog['qtd_realizada'] . ' / ' . $prog['meta_quantidade'];
                            $labelValor = 'R$ ' . number_format($prog['valor_realizado'], 2, ',', '.') . ' / R$ ' . number_format($prog['meta_valor'], 2, ',', '.');
                        } else {
                            $prog = $meta->calcularProgressoFunil();
                            $pctQtd   = $prog['pct_qtd'];
                            $pctValor = $prog['pct_taxa'];
                            $labelQtd   = $prog['total_destino'] . ' / ' . $prog['meta_qtd'] . ' leads';
                            $labelValor = $prog['taxa_real'] . '% / meta: ' . $prog['meta_taxa'] . '%';
                        }
                    } catch (\Exception $e) {
                        error_log("[MetasVendedores] List calc error id=" . $meta->get('id') . ": " . $e->getMessage());
                        $pctQtd = 0; $pctValor = 0; $labelQtd = '—'; $labelValor = '—';
                    }
                    $tipo = ($secao === 'oportunidades') ? ($meta->get('tipo_produto') ?: '—') : '—';
                    $estagios = ($secao === 'oportunidades')
                        ? ($meta->get('sales_stage_alvo') ?: 'Closed Won')
                        : (($meta->get('estagio_origem') ?: '?') . ' → ' . ($meta->get('estagio_destino') ?: '?'));
                    ?>
                    <tr>
                        <td><a href="index.php?module=MetasVendedores&view=Detail&record=<?= $meta->get('id') ?>"><?= htmlspecialchars($meta->get('titulo')) ?></a></td>
                        <td><?= $secao === 'oportunidades' ? '<span class="label label-info">Oportunidades</span>' : '<span class="label label-warning">Funil Leads</span>' ?></td>
                        <td><?= htmlspecialchars($meta->get('equipe_nome') ?: '—') ?></td>
                        <td><?= htmlspecialchars($meta->get('usuario_nome') ?: 'Equipe Toda') ?></td>
                        <td><small><?= htmlspecialchars($estagios) ?></small></td>
                        <td><small><?= $meta->get('periodo_inicio') ?> <br> <?= $meta->get('periodo_fim') ?></small></td>
                        <td>
                            <?= $this->_progressBar($pctQtd) ?>
                            <small class="mv-label"><?= htmlspecialchars($labelQtd) ?></small>
                        </td>
                        <td>
                            <?= $this->_progressBar($pctValor) ?>
                            <small class="mv-label"><?= htmlspecialchars($labelValor) ?></small>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td class="mv-actions">
                            <a href="index.php?module=MetasVendedores&view=Detail&record=<?= $meta->get('id') ?>" title="Ver"><i class="fa fa-eye"></i></a>
                            <a href="index.php?module=MetasVendedores&view=Edit&record=<?= $meta->get('id') ?>" title="Editar"><i class="fa fa-pencil"></i></a>
                            <a href="index.php?module=MetasVendedores&action=Delete&record=<?= $meta->get('id') ?>"
                               onclick="return confirm('Excluir esta meta?')" title="Excluir"><i class="fa fa-trash text-danger"></i></a>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

        </div>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function _progressBar(float $pct): string {
        $color = $pct >= 100 ? 'mv-bar-done' : ($pct >= 80 ? 'mv-bar-ok' : ($pct >= 50 ? 'mv-bar-warn' : 'mv-bar-danger'));
        return '<div class="mv-bar-wrap"><div class="mv-bar ' . $color . '" style="width:' . min(100, $pct) . '%"></div></div>';
    }

    private function _css(): string {
        return '<style>
            .mv-container { padding: 15px; }
            .mv-filters   { margin-bottom: 15px; }
            .mv-filters .form-group { margin-right: 10px; }
            .mv-thead th  { background: #2c3e50; color: #fff; }
            .mv-table td  { vertical-align: middle; }
            .mv-bar-wrap  { background: #e9ecef; border-radius: 4px; height: 12px; margin-bottom: 3px; }
            .mv-bar       { height: 12px; border-radius: 4px; transition: width .4s; }
            .mv-bar-done  { background: #27ae60; }
            .mv-bar-ok    { background: #2ecc71; }
            .mv-bar-warn  { background: #f39c12; }
            .mv-bar-danger{ background: #e74c3c; }
            .mv-label     { color: #555; font-size: 11px; }
            .mv-actions a { margin-right: 8px; font-size: 14px; color: #555; }
            .mv-actions a:hover { color: #2c3e50; }
        </style>';
    }
}
