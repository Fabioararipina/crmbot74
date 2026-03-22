<?php
/**
 * PainelBI_Relatorio_View
 * Viewer completo: chart + tabela detalhada + seção de condições colapsável
 * Similar ao VTExperts VReports Report Detail
 */
class PainelBI_Relatorio_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/PainelBI/models/DataProvider.php';

        $dp  = new PainelBI_DataProvider_Model();
        $id  = (int)$request->get('record');

        if (!$id) {
            echo '<div class="alert alert-danger" style="margin:15px">Relatório não encontrado.</div>';
            return;
        }

        $rel = $dp->getRelatorio($id);
        if (!$rel) {
            echo '<div class="alert alert-danger" style="margin:15px">Relatório não encontrado (id=' . $id . ').</div>';
            return;
        }

        $config      = json_decode(html_entity_decode($rel['config'] ?? '{}', ENT_QUOTES, 'UTF-8'), true) ?? [];
        $chartConfig = $config['chart'] ?? ['tipo' => 'bar'];
        $fields      = PainelBI_DataProvider_Model::getLeadsFields();

        // Executar relatório
        $data      = $dp->runReport($config);
        $chartData = PainelBI_DataProvider_Model::prepareChartData($chartConfig, $data);

        // Informações de condições para exibição
        $condGrupos = $config['condicoes_grupos'] ?? [];
        $chartTypes = PainelBI_DataProvider_Model::getChartTypes();
        $operators  = PainelBI_DataProvider_Model::getOperators();
        $periods    = PainelBI_DataProvider_Model::getPeriodOptions();

        echo $this->_css();
        ?>

        <div class="pbi-rel-container">

        <!-- ═══ Cabeçalho ═══ -->
        <div class="pbi-rel-header">
            <div class="pbi-rel-header-left">
                <a href="?module=PainelBI&view=Construtor&record=<?= $id ?>" class="btn btn-sm btn-default">
                    <i class="fa fa-pencil"></i> Personalizar
                </a>
                <a href="?module=PainelBI&view=Construtor&record=<?= $id ?>&duplicate=1" class="btn btn-sm btn-default">
                    <i class="fa fa-copy"></i> Duplicar
                </a>
            </div>
            <div class="pbi-rel-title-center">
                <h4><?= pbi_e($rel['titulo']) ?></h4>
            </div>
            <div class="pbi-rel-header-right">
                <button class="btn btn-sm btn-default" onclick="window.print()">
                    <i class="fa fa-print"></i> Imprimir
                </button>
                <button class="btn btn-sm btn-default" onclick="pbiExportCSV()">
                    <i class="fa fa-download"></i> CSV
                </button>
                <a href="?module=PainelBI&view=List" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i> Voltar
                </a>
            </div>
        </div>

        <!-- ═══ Seção: Gráfico (colapsável) ═══ -->
        <div class="pbi-section" id="pbi-chart-section">
            <div class="pbi-section-header" onclick="pbiToggle('pbi-chart-body')">
                <i class="fa fa-chevron-down pbi-chevron" id="chev-chart"></i>
                <i class="fa fa-bar-chart"></i>
                <b>Gráfico do Relatório</b>
                <span class="pbi-section-meta">
                    <?= $chartTypes[$chartConfig['tipo'] ?? 'bar'] ?? 'Barras' ?>
                    <?php if ($config['grupo'] ?? null): ?>
                        — Agrupado por: <b><?= pbi_e($fields[$config['grupo']]['label'] ?? $config['grupo']) ?></b>
                    <?php endif; ?>
                </span>
            </div>
            <div class="pbi-section-body" id="pbi-chart-body">
                <!-- Configuração resumida do gráfico -->
                <div class="row pbi-chart-cfg">
                    <div class="col-md-3">
                        <label>Campo de Agrupamento</label>
                        <p><?= pbi_e($fields[$config['grupo'] ?? '']['label'] ?? '—') ?></p>
                    </div>
                    <div class="col-md-4">
                        <label>Campos de Dados</label>
                        <p>
                            <?php foreach ($config['agregacoes'] ?? [] as $agg): ?>
                                <span class="label label-default">× <?= $agg['func'] ?>(<?= $agg['campo'] === '*' ? 'Total' : pbi_e($fields[$agg['campo']]['label'] ?? $agg['campo']) ?>)</span>
                            <?php endforeach; ?>
                        </p>
                    </div>
                    <div class="col-md-2">
                        <label>Posição da Legenda</label>
                        <p><?= pbi_e($chartConfig['posicao_legenda'] ?? 'top') ?></p>
                    </div>
                    <div class="col-md-3 text-right" style="padding-top:15px">
                        <a href="?module=PainelBI&view=Construtor&record=<?= $id ?>" class="btn btn-xs btn-default">
                            <i class="fa fa-pencil"></i> Editar Gráfico
                        </a>
                    </div>
                </div>

                <?php if ($chartData['tipo'] !== 'none' && !empty($chartData['datasets'])): ?>
                <div class="pbi-chart-wrap">
                    <canvas id="pbi-main-chart"></canvas>
                </div>
                <?php else: ?>
                <div class="alert alert-info" style="margin:10px">Sem dados para gráfico ou tipo "Sem Gráfico" selecionado.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ Seção: Condições (colapsável) ═══ -->
        <div class="pbi-section" id="pbi-cond-section">
            <div class="pbi-section-header" onclick="pbiToggle('pbi-cond-body')">
                <i class="fa fa-chevron-down pbi-chevron" id="chev-cond"></i>
                <i class="fa fa-filter"></i>
                <b>Condições do Relatório</b>
                <?php
                $totalConds = 0;
                foreach ($condGrupos as $g) $totalConds += count($g['condicoes'] ?? []);
                ?>
                <span class="pbi-section-meta">
                    <?= $totalConds ?> condição(ões) — <?= count($condGrupos) ?> grupo(s)
                </span>
                <a href="?module=PainelBI&view=Construtor&record=<?= $id ?>" class="btn btn-xs btn-primary" style="margin-left:auto" onclick="event.stopPropagation()">
                    <i class="fa fa-plus"></i> Adicionar Grupo
                </a>
            </div>
            <div class="pbi-section-body" id="pbi-cond-body" style="display:none">
                <?php if (empty($condGrupos)): ?>
                    <p class="text-muted" style="padding:10px">Todas as Condições (sem filtros ativos)</p>
                <?php else: ?>
                    <?php foreach ($condGrupos as $gi => $grupo):
                        $conds = $grupo['condicoes'] ?? [];
                    ?>
                    <div class="pbi-cond-group">
                        <div class="pbi-cond-group-header">
                            <?= $gi === 0 ? 'Todas as Condições' : 'OU — Grupo ' . ($gi + 1) ?>
                            <small>(todas as condições deste grupo devem ser atendidas)</small>
                        </div>
                        <?php foreach ($conds as $ci => $cond):
                            $campo  = $cond['campo'] ?? '';
                            $op     = $cond['op'] ?? 'eq';
                            $valor  = $cond['valor'] ?? '';
                            $fLabel = $fields[$campo]['label'] ?? $campo;
                            $fType  = $fields[$campo]['type'] ?? 'text';
                            $opLabels = $operators[$fType] ?? [];
                            $opLabel  = $opLabels[$op] ?? $op;
                        ?>
                        <div class="pbi-cond-row">
                            <?php if ($ci > 0): ?>
                                <span class="pbi-cond-and">E</span>
                            <?php endif; ?>
                            <span class="pbi-cond-field"><?= pbi_e($fLabel) ?></span>
                            <span class="pbi-cond-op"><?= pbi_e($opLabel) ?></span>
                            <span class="pbi-cond-val">
                                <?php if (is_array($valor)): ?>
                                    <?= pbi_e(implode(' — ', $valor)) ?>
                                <?php elseif ($op === 'in_period'): ?>
                                    <?= pbi_e(PainelBI_DataProvider_Model::getPeriodOptions()[$valor] ?? $valor) ?>
                                <?php else: ?>
                                    <?= pbi_e($valor) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ═══ Tabela de Dados ═══ -->
        <div class="pbi-section pbi-data-section">
            <div class="pbi-section-header">
                <i class="fa fa-table"></i>
                <b>Dados (<?= $data['total'] ?> registros)</b>
                <span class="pbi-section-meta">
                    <?= $rel['modulo_base'] ?>
                    · <?= $rel['tipo'] === 'summary' ? 'Resumo' : 'Detalhado' ?>
                    · Ordem: <?= pbi_e($fields[$config['ordem'] ?? 'createdtime']['label'] ?? 'Criado em') ?>
                    <?= strtoupper($config['ordem_dir'] ?? 'DESC') ?>
                </span>
            </div>
            <div class="pbi-data-body">
                <?php if (empty($data['dados'])): ?>
                    <div class="pbi-no-data">
                        <i class="fa fa-search fa-2x" style="display:block;margin-bottom:8px"></i>
                        Nenhum registro encontrado com os filtros atuais.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover pbi-data-table" id="pbi-data-table">
                        <thead>
                            <tr>
                                <?php foreach ($data['labels'] as $lbl): ?>
                                    <th><?= pbi_e($lbl) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $grupo = $config['grupo'] ?? '';
                            foreach ($data['dados'] as $row):
                                $vals = array_values($row);
                                $drillVal = $grupo ? ($row[$grupo] ?? ($vals[0] ?? '')) : '';
                            ?>
                            <tr<?php if ($grupo): ?> style="cursor:pointer" onclick="pbiDrillDown(<?= htmlspecialchars(json_encode($grupo), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($drillVal), ENT_QUOTES) ?>)"<?php endif; ?>>
                                <?php foreach ($data['chaves'] as $i => $chave): ?>
                                    <td><?= pbi_e(($row[$chave] ?? ($vals[$i] ?? ''))) ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        </div><!-- /pbi-rel-container -->

        <script>
        var _pbiFieldMap = {
            'leadstatus': 'leadstatus', 'leadsource': 'leadsource',
            'user_name': 'assigned_user_id', 'company': 'company',
            'firstname': 'firstname', 'lastname': 'lastname',
            'email': 'email', 'phone': 'phone'
        };
        function pbiDrillDown(grupo, label) {
            if (!grupo || !label) return;
            var searchKey = _pbiFieldMap[grupo] || grupo;
            if (['data_criacao','mes_criacao','semana_criacao','createdtime','modifiedtime'].indexOf(grupo) >= 0) return;
            var val = label;
            if (val === '(sem status)' || val === '(sem valor)' || val === 'Não informado') { val = ''; }
            window.location.href = 'index.php?module=Leads&view=List&search_key=' +
                encodeURIComponent(searchKey) + '&search_value=' + encodeURIComponent(val) + '&operator=e';
        }
        </script>

        <?php if ($chartData['tipo'] !== 'none'): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var d = <?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>;
            var grupo = <?= json_encode($config['grupo'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
            var canvas = document.getElementById('pbi-main-chart');
            if (!canvas || !d || d.tipo === 'none') return;
            var chart = new Chart(canvas, {
                type: d.tipo,
                data: { labels: d.labels, datasets: d.datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: d.is_h ? 'y' : 'x',
                    plugins: {
                        legend: {
                            display: d.options.mostrar_legenda,
                            position: d.options.posicao_legenda || 'top'
                        },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: (d.tipo === 'pie' || d.tipo === 'doughnut') ? {} : {
                        x: { grid: { display: d.options.mostrar_grid } },
                        y: { grid: { display: d.options.mostrar_grid }, beginAtZero: true }
                    },
                    onClick: function(evt, elements) {
                        if (!elements.length || !grupo) return;
                        pbiDrillDown(grupo, d.labels[elements[0].index]);
                    }
                }
            });
            if (grupo) {
                canvas.style.cursor = 'default';
                canvas.addEventListener('mousemove', function(e) {
                    var pts = chart.getElementsAtEventForMode(e, 'nearest', {intersect: true}, false);
                    canvas.style.cursor = pts.length ? 'pointer' : 'default';
                });
            }
        });
        </script>
        <?php endif; ?>

        <script>
        function pbiToggle(id) {
            var el   = document.getElementById(id);
            var secId= id.replace('-body', '-header');
            if (!el) return;
            var open = el.style.display !== 'none';
            el.style.display = open ? 'none' : '';
            // Chevron
            var chevId = 'chev-' + id.replace('pbi-','').replace('-body','');
            var chev = document.getElementById(chevId);
            if (chev) chev.style.transform = open ? 'rotate(-90deg)' : '';
        }

        function pbiExportCSV() {
            var table = document.getElementById('pbi-data-table');
            if (!table) return;
            var rows = [];
            table.querySelectorAll('tr').forEach(function(tr) {
                var cols = [];
                tr.querySelectorAll('th,td').forEach(function(td) {
                    var v = td.textContent.trim().replace(/"/g,'""');
                    cols.push('"' + v + '"');
                });
                rows.push(cols.join(','));
            });
            var blob = new Blob(['\uFEFF' + rows.join('\n')], {type:'text/csv;charset=utf-8'});
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = '<?= addslashes(pbi_e($rel['titulo'])) ?>.csv';
            a.click();
        }
        </script>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }

    private function _css(): string { return '<style>
        .pbi-rel-container      { padding:0; }
        /* Header */
        .pbi-rel-header         { display:flex; align-items:center; justify-content:space-between; padding:12px 15px; background:#fff; border-bottom:1px solid #e8e8e8; }
        .pbi-rel-header-left, .pbi-rel-header-right { display:flex; gap:6px; flex:1; }
        .pbi-rel-header-right   { justify-content:flex-end; }
        .pbi-rel-title-center   { flex:2; text-align:center; }
        .pbi-rel-title-center h4{ margin:0; font-size:16px; }
        /* Sections */
        .pbi-section            { border:1px solid #e0e0e0; margin:10px 15px; border-radius:6px; background:#fff; overflow:hidden; }
        .pbi-section-header     { display:flex; align-items:center; gap:8px; padding:12px 15px; cursor:pointer; background:#fafafa; border-bottom:1px solid #e8e8e8; font-size:13px; }
        .pbi-section-header:hover { background:#f0f4f8; }
        .pbi-section-body       { padding:15px; }
        .pbi-section-meta       { margin-left:8px; color:#7f8c8d; font-size:12px; font-weight:normal; }
        .pbi-chevron            { width:14px; transition:transform .2s; }
        /* Chart */
        .pbi-chart-cfg          { margin-bottom:15px; padding:10px; background:#f8f9fa; border-radius:4px; }
        .pbi-chart-cfg label    { font-size:11px; text-transform:uppercase; color:#666; font-weight:600; margin-bottom:3px; }
        .pbi-chart-wrap         { height:320px; }
        .pbi-chart-wrap canvas  { max-height:320px; }
        /* Conditions */
        .pbi-cond-group         { margin-bottom:12px; border:1px solid #e8e8e8; border-radius:4px; }
        .pbi-cond-group-header  { background:#f0f4f8; padding:8px 12px; font-size:12px; font-weight:600; color:#2c3e50; }
        .pbi-cond-row           { display:flex; align-items:center; gap:10px; padding:6px 12px; border-top:1px solid #f0f0f0; font-size:13px; }
        .pbi-cond-and           { background:#e8f4f8; color:#2980b9; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; }
        .pbi-cond-field         { font-weight:600; color:#2c3e50; min-width:120px; }
        .pbi-cond-op            { color:#7f8c8d; font-style:italic; min-width:100px; }
        .pbi-cond-val           { color:#e74c3c; font-weight:500; }
        /* Data table */
        .pbi-data-section       { margin-bottom:15px; }
        .pbi-data-body          { padding:0; }
        .pbi-data-table         { font-size:13px; margin-bottom:0; }
        .pbi-data-table thead th{ background:#f8f9fa; font-size:11px; text-transform:uppercase; color:#555; position:sticky; top:0; z-index:1; }
        .pbi-no-data            { text-align:center; padding:40px; color:#aaa; }
        @media print {
            .pbi-rel-header-left, .pbi-rel-header-right { display:none; }
            .pbi-cond-section, .pbi-section-header { page-break-inside:avoid; }
        }
    </style>'; }
}
