<?php
/**
 * MetasVendedores_Detail_View — Detalhe de uma meta com barras de progresso grandes
 */
class MetasVendedores_Detail_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/MetasVendedores/models/Record.php';

        $id     = (int) $request->get('record');
        $record = MetasVendedores_Record_Model::getById($id);

        if (!$record) {
            echo '<div class="alert alert-danger mv-container">Meta não encontrada.</div>';
            return;
        }

        $secao = $record->get('secao');

        if ($secao === 'oportunidades') {
            $prog = $record->calcularProgressoOportunidades();
        } else {
            $prog = $record->calcularProgressoFunil();
        }

        // Calcular dias restantes
        $hoje  = new DateTime();
        $fim   = new DateTime($record->get('periodo_fim'));
        $diasRestantes = max(0, $hoje->diff($fim)->days * ($hoje <= $fim ? 1 : -1));
        $periodoFinalizado = $hoje > $fim;

        echo $this->_css();
        ?>
        <div class="mv-container">

            <!-- Breadcrumb / Ações -->
            <div class="mv-topbar clearfix">
                <div class="pull-left">
                    <a href="index.php?module=MetasVendedores&view=List" class="btn btn-default btn-sm"><i class="fa fa-arrow-left"></i> Lista</a>
                    &nbsp;
                    <a href="index.php?module=MetasVendedores&view=Dashboard" class="btn btn-default btn-sm"><i class="fa fa-bar-chart"></i> Dashboard</a>
                </div>
                <div class="pull-right">
                    <a href="index.php?module=MetasVendedores&view=Edit&record=<?= $id ?>" class="btn btn-warning btn-sm"><i class="fa fa-pencil"></i> Editar</a>
                    &nbsp;
                    <a href="index.php?module=MetasVendedores&action=Delete&record=<?= $id ?>"
                       onclick="return confirm('Excluir esta meta permanentemente?')"
                       class="btn btn-danger btn-sm"><i class="fa fa-trash"></i> Excluir</a>
                </div>
            </div>

            <!-- Cabeçalho da Meta -->
            <div class="mv-detail-header">
                <div class="row">
                    <div class="col-md-8">
                        <h2 class="mv-titulo"><?= htmlspecialchars($record->get('titulo')) ?></h2>
                        <div class="mv-meta-info">
                            <span class="label <?= $secao === 'oportunidades' ? 'label-info' : 'label-warning' ?> mv-badge">
                                <?= $secao === 'oportunidades' ? 'Oportunidades' : 'Funil de Leads' ?>
                            </span>
                            &nbsp;
                            <?php if ($record->get('equipe_nome')): ?>
                                <i class="fa fa-users"></i> <b>Equipe:</b> <?= htmlspecialchars($record->get('equipe_nome')) ?> &nbsp;
                            <?php endif; ?>
                            <?php if ($record->get('usuario_nome')): ?>
                                <i class="fa fa-user"></i> <b>Vendedor:</b> <?= htmlspecialchars($record->get('usuario_nome')) ?> &nbsp;
                            <?php else: ?>
                                <i class="fa fa-users"></i> <b>Escopo:</b> Equipe Toda &nbsp;
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-right">
                        <div class="mv-periodo-box">
                            <div><i class="fa fa-calendar"></i> <?= $record->get('periodo_inicio') ?> <b>→</b> <?= $record->get('periodo_fim') ?></div>
                            <div class="mv-dias <?= $periodoFinalizado ? 'mv-finalizado' : ($diasRestantes <= 7 ? 'mv-urgente' : '') ?>">
                                <?php if ($periodoFinalizado): ?>
                                    <i class="fa fa-flag-checkered"></i> Período encerrado
                                <?php else: ?>
                                    <i class="fa fa-clock-o"></i> <?= $diasRestantes ?> dias restantes
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Métricas de Progresso -->
            <?php if ($secao === 'oportunidades'): ?>
                <div class="row mv-metrics">
                    <!-- Tipo / Estágio -->
                    <div class="col-md-12">
                        <div class="mv-info-pills">
                            <?php if ($record->get('tipo_produto')): ?>
                                <span class="mv-pill"><i class="fa fa-tag"></i> Tipo: <b><?= htmlspecialchars($record->get('tipo_produto')) ?></b></span>
                            <?php else: ?>
                                <span class="mv-pill"><i class="fa fa-tag"></i> Tipo: <b>Todos</b></span>
                            <?php endif; ?>
                            <span class="mv-pill"><i class="fa fa-check-circle"></i> Estágio alvo: <b><?= htmlspecialchars($record->get('sales_stage_alvo') ?: 'Closed Won') ?></b></span>
                        </div>
                    </div>

                    <!-- Barra Quantidade -->
                    <div class="col-md-6">
                        <div class="mv-metric-card">
                            <div class="mv-metric-title"><i class="fa fa-hashtag"></i> Quantidade de Oportunidades</div>
                            <?= $this->_bigBar($prog['pct_quantidade']) ?>
                            <div class="mv-metric-nums">
                                <span class="mv-num-real"><?= $prog['qtd_realizada'] ?></span>
                                <span class="mv-num-sep"> de </span>
                                <span class="mv-num-meta"><?= $prog['meta_quantidade'] ?></span>
                                <span class="mv-num-pct"> (<?= $prog['pct_quantidade'] ?>%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Barra Valor -->
                    <div class="col-md-6">
                        <div class="mv-metric-card">
                            <div class="mv-metric-title"><i class="fa fa-money"></i> Valor em R$</div>
                            <?= $this->_bigBar($prog['pct_valor']) ?>
                            <div class="mv-metric-nums">
                                <span class="mv-num-real">R$ <?= number_format($prog['valor_realizado'], 2, ',', '.') ?></span>
                                <span class="mv-num-sep"> de </span>
                                <span class="mv-num-meta">R$ <?= number_format($prog['meta_valor'], 2, ',', '.') ?></span>
                                <span class="mv-num-pct"> (<?= $prog['pct_valor'] ?>%)</span>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="row mv-metrics">
                    <!-- Estágios -->
                    <div class="col-md-12">
                        <div class="mv-info-pills">
                            <span class="mv-pill"><i class="fa fa-arrow-right"></i>
                                Funil: <b><?= htmlspecialchars($record->get('estagio_origem') ?: '?') ?></b>
                                &nbsp;→&nbsp;
                                <b><?= htmlspecialchars($record->get('estagio_destino') ?: '?') ?></b>
                            </span>
                        </div>
                    </div>

                    <!-- Barra Taxa de Conversão -->
                    <div class="col-md-6">
                        <div class="mv-metric-card">
                            <div class="mv-metric-title"><i class="fa fa-percent"></i> Taxa de Conversão</div>
                            <?= $this->_bigBar($prog['pct_taxa']) ?>
                            <div class="mv-metric-nums">
                                <span class="mv-num-real"><?= $prog['taxa_real'] ?>%</span>
                                <span class="mv-num-sep"> de </span>
                                <span class="mv-num-meta"><?= $prog['meta_taxa'] ?>% meta</span>
                                <span class="mv-num-pct"> (<?= $prog['pct_taxa'] ?>% da meta)</span>
                            </div>
                            <div class="mv-sub-info">
                                Leads em origem: <b><?= $prog['total_origem'] ?></b> &nbsp;|&nbsp;
                                Leads em destino: <b><?= $prog['total_destino'] ?></b>
                            </div>
                        </div>
                    </div>

                    <!-- Barra Quantidade Leads -->
                    <div class="col-md-6">
                        <div class="mv-metric-card">
                            <div class="mv-metric-title"><i class="fa fa-users"></i> Quantidade de Leads (estágio destino)</div>
                            <?= $this->_bigBar($prog['pct_qtd']) ?>
                            <div class="mv-metric-nums">
                                <span class="mv-num-real"><?= $prog['total_destino'] ?> leads</span>
                                <span class="mv-num-sep"> de </span>
                                <span class="mv-num-meta"><?= $prog['meta_qtd'] ?> meta</span>
                                <span class="mv-num-pct"> (<?= $prog['pct_qtd'] ?>%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }

    private function _bigBar(float $pct): string {
        if ($pct >= 100)      $color = '#27ae60';
        elseif ($pct >= 80)   $color = '#2ecc71';
        elseif ($pct >= 50)   $color = '#f39c12';
        else                  $color = '#e74c3c';

        $w = min(100, $pct);
        return "
        <div class='mv-bigbar-wrap'>
            <div class='mv-bigbar' style='width:{$w}%;background:{$color}'></div>
        </div>";
    }

    private function _css(): string {
        return '<style>
            .mv-container    { padding: 15px; }
            .mv-topbar       { margin-bottom: 15px; }
            .mv-detail-header{ background:#2c3e50; color:#fff; padding:20px; border-radius:6px; margin-bottom:20px; }
            .mv-titulo       { margin:0 0 10px 0; font-size:22px; color:#fff; }
            .mv-meta-info    { color:#bdc3c7; font-size:14px; }
            .mv-badge        { font-size:13px; padding:5px 10px; }
            .mv-periodo-box  { background:rgba(255,255,255,.1); border-radius:6px; padding:12px; text-align:center; }
            .mv-dias         { font-size:18px; font-weight:bold; margin-top:5px; color:#f1c40f; }
            .mv-urgente      { color:#e74c3c !important; }
            .mv-finalizado   { color:#95a5a6 !important; }
            .mv-info-pills   { margin-bottom: 15px; }
            .mv-pill         { background:#ecf0f1; border-radius:20px; padding:5px 12px; margin-right:8px; font-size:13px; }
            .mv-metrics      { margin-top:10px; }
            .mv-metric-card  { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:20px; margin-bottom:15px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
            .mv-metric-title { font-size:15px; font-weight:bold; color:#2c3e50; margin-bottom:12px; }
            .mv-bigbar-wrap  { background:#e9ecef; border-radius:8px; height:28px; margin-bottom:8px; overflow:hidden; }
            .mv-bigbar       { height:28px; border-radius:8px; transition:width .6s ease; }
            .mv-metric-nums  { font-size:16px; }
            .mv-num-real     { font-size:22px; font-weight:bold; color:#2c3e50; }
            .mv-num-sep      { color:#7f8c8d; }
            .mv-num-meta     { color:#7f8c8d; }
            .mv-num-pct      { color:#95a5a6; font-size:13px; }
            .mv-sub-info     { margin-top:8px; color:#7f8c8d; font-size:13px; }
        </style>';
    }
}
