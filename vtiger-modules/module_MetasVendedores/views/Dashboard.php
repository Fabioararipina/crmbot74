<?php
/**
 * MetasVendedores_Dashboard_View
 * Vista consolidada hierárquica: Organização → Equipe → Vendedor → Tipo/Meta
 * Filtros: período, equipe, seção
 */
class MetasVendedores_Dashboard_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/MetasVendedores/models/Record.php';
        require_once 'modules/MetasVendedores/models/Picklist.php';

        $fi    = $request->get('fi')    ?: date('Y-m-01');
        $ff    = $request->get('ff')    ?: date('Y-m-t');
        $secao = $request->get('secao') ?: '';
        $eqId  = $request->get('equipe_id') ?: '';

        $filters = [
            'periodo_inicio' => $fi,
            'periodo_fim'    => $ff,
        ];
        if ($secao) $filters['secao']    = $secao;
        if ($eqId)  $filters['equipe_id']= $eqId;

        $consolidado = MetasVendedores_Record_Model::getConsolidado($filters);
        $equipes     = MetasVendedores_Picklist_Model::getEquipes();

        $currentUser = Users_Record_Model::getCurrentUserModel();
        $isAdmin = ($currentUser->get('is_admin') === 'on');

        echo $this->_css();
        ?>
        <div class="mv-container">

            <!-- Filtros do Dashboard -->
            <div class="panel panel-default">
                <div class="panel-body">
                    <form method="GET" class="form-inline" id="mv-dash-form">
                        <input type="hidden" name="module" value="MetasVendedores">
                        <input type="hidden" name="view"   value="Dashboard">
                        <div class="form-group">
                            <label><i class="fa fa-calendar"></i> Período:</label>
                            <input type="date" name="fi" class="form-control input-sm" value="<?= htmlspecialchars($fi) ?>">
                            <span>até</span>
                            <input type="date" name="ff" class="form-control input-sm" value="<?= htmlspecialchars($ff) ?>">
                        </div>
                        <div class="form-group">
                            <label>Seção:</label>
                            <select name="secao" class="form-control input-sm">
                                <option value="">Todas</option>
                                <option value="oportunidades" <?= $secao === 'oportunidades' ? 'selected' : '' ?>>Oportunidades</option>
                                <option value="funil"         <?= $secao === 'funil'         ? 'selected' : '' ?>>Funil de Leads</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Equipe:</label>
                            <select name="equipe_id" class="form-control input-sm">
                                <option value="">Todas</option>
                                <?php foreach ($equipes as $eq): ?>
                                    <option value="<?= $eq['id'] ?>" <?= (string)$eqId === (string)$eq['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($eq['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-sm btn-default"><i class="fa fa-search"></i> Atualizar</button>
                        &nbsp;
                        <a href="index.php?module=MetasVendedores&view=List&fi=<?= $fi ?>&ff=<?= $ff ?>" class="btn btn-sm btn-default"><i class="fa fa-list"></i> Lista</a>
                        <?php if ($isAdmin): ?>
                        &nbsp;
                        <a href="index.php?module=MetasVendedores&view=Edit" class="btn btn-sm btn-success"><i class="fa fa-plus"></i> Nova Meta</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <?php if (empty($consolidado['equipes'])): ?>
                <div class="alert alert-info"><i class="fa fa-info-circle"></i> Nenhuma meta cadastrada para o período selecionado.</div>
            <?php else: ?>

            <!-- ═══ TOTAL ORGANIZAÇÃO ═══ -->
            <?php $orgTotal = $consolidado['total_org']; ?>
            <div class="mv-org-box">
                <div class="row">
                    <div class="col-md-6">
                        <span class="mv-org-label"><i class="fa fa-building"></i> ORGANIZAÇÃO — <?= date('d/m/Y', strtotime($fi)) ?> a <?= date('d/m/Y', strtotime($ff)) ?></span>
                    </div>
                    <?php if ($orgTotal['valor'] > 0): ?>
                    <div class="col-md-3">
                        <small>Valor</small>
                        <?= $this->_bar($orgTotal['valor'] > 0 ? min(100, round($orgTotal['valor_realizado'] / $orgTotal['valor'] * 100)) : 0) ?>
                        <small>R$ <?= number_format($orgTotal['valor_realizado'],2,',','.') ?> / R$ <?= number_format($orgTotal['valor'],2,',','.') ?></small>
                    </div>
                    <?php endif; ?>
                    <?php if ($orgTotal['qtd'] > 0): ?>
                    <div class="col-md-3">
                        <small>Quantidade</small>
                        <?= $this->_bar($orgTotal['qtd'] > 0 ? min(100, round($orgTotal['qtd_realizada'] / $orgTotal['qtd'] * 100)) : 0) ?>
                        <small><?= $orgTotal['qtd_realizada'] ?> / <?= $orgTotal['qtd'] ?> unid</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ POR EQUIPE ═══ -->
            <?php foreach ($consolidado['equipes'] as $equipe): ?>
                <div class="mv-equipe-block">
                    <div class="mv-equipe-header" onclick="mvToggle('eq-<?= $equipe['id'] ?>')">
                        <i class="fa fa-chevron-down mv-chevron" id="chev-eq-<?= $equipe['id'] ?>"></i>
                        <i class="fa fa-users"></i>
                        <b><?= htmlspecialchars($equipe['nome']) ?></b>

                        <?php $eqT = $equipe['total']; if ($eqT['valor'] > 0): ?>
                            <span class="mv-equipe-stat">
                                Valor: <?= $this->_minibar($eqT['valor'] > 0 ? min(100, round($eqT['valor_realizado'] / $eqT['valor'] * 100)) : 0) ?>
                                R$ <?= number_format($eqT['valor_realizado'],2,',','.') ?>/R$ <?= number_format($eqT['valor'],2,',','.') ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="mv-equipe-body" id="eq-<?= $equipe['id'] ?>">
                        <?php foreach ($equipe['vendedores'] as $vendedor): ?>
                            <div class="mv-vendedor-block">
                                <div class="mv-vendedor-header" onclick="mvToggle('vend-<?= $equipe['id'] ?>-<?= $vendedor['id'] ?>')">
                                    <i class="fa fa-chevron-down mv-chevron" id="chev-vend-<?= $equipe['id'] ?>-<?= $vendedor['id'] ?>"></i>
                                    <i class="fa fa-user"></i>
                                    <b><?= htmlspecialchars($vendedor['nome']) ?></b>

                                    <?php $vT = $vendedor['total']; if ($vT['valor'] > 0 || $vT['qtd'] > 0): ?>
                                        <span class="mv-vend-stat">
                                            <?php if ($vT['valor'] > 0): ?>
                                                Valor: <?= $this->_minibar($vT['valor'] > 0 ? min(100, round($vT['valor_realizado'] / $vT['valor'] * 100)) : 0) ?>
                                                R$ <?= number_format($vT['valor_realizado'],2,',','.') ?>/R$ <?= number_format($vT['valor'],2,',','.') ?>
                                            <?php endif; ?>
                                            <?php if ($vT['qtd'] > 0): ?>
                                                &nbsp; Qtd: <?= $this->_minibar($vT['qtd'] > 0 ? min(100, round($vT['qtd_realizada'] / $vT['qtd'] * 100)) : 0) ?>
                                                <?= $vT['qtd_realizada'] ?>/<?= $vT['qtd'] ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="mv-vendedor-body" id="vend-<?= $equipe['id'] ?>-<?= $vendedor['id'] ?>">
                                    <?php foreach ($vendedor['metas'] as $item): ?>
                                        <?php
                                        $meta  = $item['meta'];
                                        $prog  = $item['progresso'];
                                        $mSecao= $item['secao'];
                                        ?>
                                        <div class="mv-meta-row">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <a href="index.php?module=MetasVendedores&view=Detail&record=<?= $meta['id'] ?>"
                                                       class="mv-meta-titulo">
                                                        <span class="label <?= $mSecao === 'oportunidades' ? 'label-info' : 'label-warning' ?>" style="font-size:10px">
                                                            <?= $mSecao === 'oportunidades' ? 'Opor' : 'Funil' ?>
                                                        </span>
                                                        <?= htmlspecialchars($meta['titulo']) ?>
                                                    </a>
                                                    <?php if (!empty($meta['tipo_produto'])): ?>
                                                        <br><small class="text-muted"><i class="fa fa-tag"></i> <?= htmlspecialchars($meta['tipo_produto']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($meta['estagio_origem'])): ?>
                                                        <br><small class="text-muted"><i class="fa fa-arrow-right"></i> <?= htmlspecialchars($meta['estagio_origem']) ?> → <?= htmlspecialchars($meta['estagio_destino']) ?></small>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($mSecao === 'oportunidades'): ?>
                                                    <div class="col-md-4">
                                                        <small>Quantidade</small>
                                                        <?= $this->_bar($prog['pct_quantidade']) ?>
                                                        <small><?= $prog['qtd_realizada'] ?>/<?= $prog['meta_quantidade'] ?> (<?= $prog['pct_quantidade'] ?>%)</small>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small>Valor (R$)</small>
                                                        <?= $this->_bar($prog['pct_valor']) ?>
                                                        <small>R$ <?= number_format($prog['valor_realizado'],2,',','.') ?>/R$ <?= number_format($prog['meta_valor'],2,',','.') ?> (<?= $prog['pct_valor'] ?>%)</small>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="col-md-4">
                                                        <small>Taxa de Conversão</small>
                                                        <?= $this->_bar($prog['pct_taxa']) ?>
                                                        <small><?= $prog['taxa_real'] ?>% / meta <?= $prog['meta_taxa'] ?>% (<?= $prog['pct_taxa'] ?>%)</small>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <small>Leads no estágio destino</small>
                                                        <?= $this->_bar($prog['pct_qtd']) ?>
                                                        <small><?= $prog['total_destino'] ?>/<?= $prog['meta_qtd'] ?> leads (<?= $prog['pct_qtd'] ?>%)</small>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="col-md-1 mv-meta-acoes">
                                                    <a href="index.php?module=MetasVendedores&view=Detail&record=<?= $meta['id'] ?>" title="Ver"><i class="fa fa-eye"></i></a>
                                                    <?php if ($isAdmin): ?>
                                                    <a href="index.php?module=MetasVendedores&view=Edit&record=<?= $meta['id'] ?>"   title="Editar"><i class="fa fa-pencil"></i></a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div><!-- /mv-vendedor-body -->
                            </div><!-- /mv-vendedor-block -->
                        <?php endforeach; ?>
                    </div><!-- /mv-equipe-body -->
                </div><!-- /mv-equipe-block -->
            <?php endforeach; ?>

            <?php endif; ?>
        </div>

        <script>
        function mvToggle(id) {
            var el   = document.getElementById(id);
            var chev = document.getElementById('chev-' + id);
            if (!el) return;
            var isOpen = el.style.display !== 'none';
            el.style.display = isOpen ? 'none' : '';
            if (chev) chev.className = isOpen ? 'fa fa-chevron-right mv-chevron' : 'fa fa-chevron-down mv-chevron';
        }
        </script>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }

    private function _bar(float $pct): string {
        if ($pct >= 100)    $c = '#27ae60';
        elseif ($pct >= 80) $c = '#2ecc71';
        elseif ($pct >= 50) $c = '#f39c12';
        else                $c = '#e74c3c';
        return "<div class='mv-b-wrap'><div class='mv-b' style='width:" . min(100,$pct) . "%;background:{$c}'></div></div>";
    }

    private function _minibar(float $pct): string {
        if ($pct >= 100)    $c = '#27ae60';
        elseif ($pct >= 80) $c = '#2ecc71';
        elseif ($pct >= 50) $c = '#f39c12';
        else                $c = '#e74c3c';
        return "<span class='mv-mini-wrap'><span class='mv-mini-bar' style='width:" . min(100,$pct) . "%;background:{$c}'></span></span>";
    }

    private function _css(): string {
        return '<style>
            .mv-container         { padding: 15px; }
            /* Organização */
            .mv-org-box           { background:#2c3e50; color:#fff; padding:15px 20px; border-radius:8px; margin-bottom:15px; }
            .mv-org-label         { font-size:16px; font-weight:bold; }
            /* Equipe */
            .mv-equipe-block      { border:1px solid #bdc3c7; border-radius:6px; margin-bottom:10px; overflow:hidden; }
            .mv-equipe-header     { background:#34495e; color:#fff; padding:12px 15px; cursor:pointer; display:flex; align-items:center; gap:8px; }
            .mv-equipe-header:hover{ background:#2c3e50; }
            .mv-equipe-body       { padding:10px 15px; background:#f8f9fa; }
            .mv-equipe-stat       { margin-left:auto; display:flex; align-items:center; gap:8px; font-size:12px; }
            /* Vendedor */
            .mv-vendedor-block    { border:1px solid #dde; border-radius:4px; margin-bottom:8px; background:#fff; }
            .mv-vendedor-header   { background:#ecf0f1; padding:10px 12px; cursor:pointer; display:flex; align-items:center; gap:8px; border-radius:4px; }
            .mv-vendedor-header:hover{ background:#dfe6e9; }
            .mv-vendedor-body     { padding:8px 12px; }
            .mv-vend-stat         { margin-left:auto; display:flex; align-items:center; gap:8px; font-size:12px; }
            /* Meta Row */
            .mv-meta-row          { border-bottom:1px solid #f0f0f0; padding:8px 0; }
            .mv-meta-row:last-child{ border-bottom:none; }
            .mv-meta-titulo       { font-weight:bold; color:#2c3e50; font-size:13px; }
            .mv-meta-acoes a      { display:block; color:#7f8c8d; font-size:13px; }
            .mv-meta-acoes a:hover{ color:#2c3e50; }
            /* Barras */
            .mv-b-wrap            { background:#e9ecef; border-radius:4px; height:10px; margin:2px 0; }
            .mv-b                 { height:10px; border-radius:4px; transition:width .4s; }
            /* Mini barras inline */
            .mv-mini-wrap         { display:inline-block; background:#e9ecef; border-radius:3px; height:8px; width:60px; vertical-align:middle; }
            .mv-mini-bar          { display:inline-block; height:8px; border-radius:3px; vertical-align:top; }
            /* Chevron */
            .mv-chevron           { transition:transform .2s; width:12px; }
        </style>';
    }
}
