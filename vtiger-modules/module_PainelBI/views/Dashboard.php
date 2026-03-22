<?php
/**
 * PainelBI_Dashboard_View
 * Dashboard multi-board/tab com widgets (gráficos + tabelas)
 * Similar ao VTExperts VReports Dashboard
 */
class PainelBI_Dashboard_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/PainelBI/models/DataProvider.php';

        $dp     = new PainelBI_DataProvider_Model();
        $boards = $dp->getBoards();

        // Board selecionado
        $boardId = (int)$request->get('board_id');
        if (!$boardId && !empty($boards)) {
            $boardId = (int)$boards[0]['id'];
        }

        // Tab selecionada
        $tabId   = (int)$request->get('tab_id');
        $tabs    = $boardId ? $dp->getBoardTabs($boardId) : [];
        if (!$tabId && !empty($tabs)) {
            $tabId = (int)$tabs[0]['id'];
        }

        // Widgets da tab ativa
        $widgets = $tabId ? $dp->getTabWidgets($tabId) : [];

        // KPIs rápidos
        $kpis = $dp->getKPIs();

        // Relatórios disponíveis para "Add Widget"
        $relatorios = $dp->getRelatorios();

        // cvid da view "Todos" para drill-down
        $allViewId = $dp->getAllViewId('Leads');

        // Separar boards por tipo
        $myBoards     = array_filter($boards, fn($b) => !$b['compartilhado']);
        $sharedBoards = array_filter($boards, fn($b) => $b['compartilhado']);

        echo $this->_css();
        ?>

        <div class="pbi-container">

        <!-- ═══ HEADER: Board Selector + KPIs ═══ -->
        <div class="pbi-header">
            <div class="pbi-header-left">
                <div class="dropdown">
                    <button class="btn btn-default dropdown-toggle pbi-board-btn" data-toggle="dropdown">
                        <?= pbi_e($this->_getBoardTitle($boards, $boardId)) ?>
                        <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu pbi-board-menu">
                        <li class="dropdown-header">Meus Boards</li>
                        <?php foreach ($myBoards as $b): ?>
                            <li class="<?= (int)$b['id'] === $boardId ? 'active' : '' ?>">
                                <a href="?module=PainelBI&view=Dashboard&board_id=<?= $b['id'] ?>">
                                    <?= pbi_e($b['titulo']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php if (!empty($sharedBoards)): ?>
                            <li class="divider"></li>
                            <li class="dropdown-header">Boards Compartilhados</li>
                            <?php foreach ($sharedBoards as $b): ?>
                                <li class="<?= (int)$b['id'] === $boardId ? 'active' : '' ?>">
                                    <a href="?module=PainelBI&view=Dashboard&board_id=<?= $b['id'] ?>">
                                        <i class="fa fa-share-alt text-muted" style="font-size:11px"></i>
                                        <?= pbi_e($b['titulo']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- KPIs rápidos -->
                <div class="pbi-kpis">
                    <span class="pbi-kpi"><b><?= $kpis['hoje'] ?></b> <small>Hoje</small></span>
                    <span class="pbi-kpi"><b><?= $kpis['mes'] ?></b> <small>Este Mês</small></span>
                    <span class="pbi-kpi pbi-kpi-warn"><b><?= $kpis['aguardando'] ?></b> <small>Aguardando</small></span>
                    <span class="pbi-kpi"><b><?= $kpis['total'] ?></b> <small>Total Leads</small></span>
                </div>
            </div>

            <div class="pbi-header-right">
                <?php if ($tabId): ?>
                <button class="btn btn-sm btn-primary" onclick="pbiShowAddWidget(<?= $tabId ?>)">
                    <i class="fa fa-plus"></i> Adicionar Widget
                </button>
                <?php endif; ?>

                <div class="dropdown">
                    <button class="btn btn-sm btn-default dropdown-toggle" data-toggle="dropdown">
                        Mais <span class="caret"></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-right">
                        <li class="dropdown-header">Relatórios</li>
                        <li><a href="?module=PainelBI&view=List"><i class="fa fa-list"></i> Ver Todos os Relatórios</a></li>
                        <li><a href="?module=PainelBI&view=Construtor"><i class="fa fa-plus"></i> Novo Relatório</a></li>
                        <li class="divider"></li>
                        <li class="dropdown-header">Abas</li>
                        <?php if ($boardId): ?>
                        <li><a href="javascript:void(0)" onclick="pbiAddTab(<?= $boardId ?>)"><i class="fa fa-plus"></i> Nova Aba</a></li>
                        <?php if ($tabId): ?>
                        <li><a href="javascript:void(0)" onclick="pbiRenameTab(<?= $tabId ?>)"><i class="fa fa-pencil"></i> Renomear Aba</a></li>
                        <li><a href="javascript:void(0)" onclick="pbiDeleteTab(<?= $tabId ?>)"><i class="fa fa-trash"></i> Excluir Aba</a></li>
                        <?php endif; ?>
                        <li class="divider"></li>
                        <li class="dropdown-header">Boards</li>
                        <li><a href="javascript:void(0)" onclick="pbiAddBoard()"><i class="fa fa-plus"></i> Novo Board</a></li>
                        <?php if ($boardId): ?>
                        <li><a href="javascript:void(0)" onclick="pbiRenameBoard(<?= $boardId ?>)"><i class="fa fa-pencil"></i> Renomear Board</a></li>
                        <li><a href="javascript:void(0)" onclick="pbiDeleteBoard(<?= $boardId ?>)"><i class="fa fa-trash text-danger"></i> Excluir Board</a></li>
                        <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- ═══ TABS ═══ -->
        <?php if (!empty($tabs)): ?>
        <ul class="nav nav-tabs pbi-tabs">
            <?php foreach ($tabs as $tab): ?>
                <li class="<?= (int)$tab['id'] === $tabId ? 'active' : '' ?>">
                    <a href="?module=PainelBI&view=Dashboard&board_id=<?= $boardId ?>&tab_id=<?= $tab['id'] ?>">
                        <?= pbi_e($tab['titulo']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- ═══ WIDGETS ═══ -->
        <div class="pbi-widgets-area">
            <?php if (empty($widgets)): ?>
                <div class="pbi-empty">
                    <i class="fa fa-bar-chart fa-3x text-muted"></i>
                    <p>Nenhum widget nesta aba.</p>
                    <?php if ($tabId): ?>
                    <button class="btn btn-primary" onclick="pbiShowAddWidget(<?= $tabId ?>)">
                        <i class="fa fa-plus"></i> Adicionar Widget
                    </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <div class="row pbi-widget-row" id="pbi-widget-row">
                <?php foreach ($widgets as $widget):
                    $config     = json_decode(html_entity_decode($widget['config'] ?? '{}', ENT_QUOTES, 'UTF-8'), true) ?? [];
                    $chartConfig= $config['chart'] ?? ['tipo' => 'bar'];
                    $data       = (new PainelBI_DataProvider_Model())->runReport($config);
                    $chartData  = PainelBI_DataProvider_Model::prepareChartData($chartConfig, $data);
                    $largura    = (int)($widget['largura'] ?? 6);
                    $widgetId   = (int)$widget['id'];
                    $relId      = (int)$widget['relatorio_id'];
                    $relTitulo  = $widget['titulo'] ?? '';
                ?>
                <div class="col-md-<?= $largura ?> pbi-widget-col" data-widget-id="<?= $widgetId ?>">
                    <div class="pbi-widget panel panel-default">
                        <div class="pbi-widget-header">
                            <span><?= pbi_e($widget['titulo']) ?></span>
                            <div class="pbi-widget-actions">
                                <a href="?module=PainelBI&view=Relatorio&record=<?= $relId ?>" title="Ver Relatório"><i class="fa fa-external-link"></i></a>
                                <a href="?module=PainelBI&view=Construtor&record=<?= $relId ?>" title="Editar"><i class="fa fa-pencil"></i></a>
                                <a href="javascript:void(0)" onclick="pbiRemoveWidget(<?= $widgetId ?>)" title="Remover"><i class="fa fa-times"></i></a>
                            </div>
                        </div>
                        <div class="pbi-widget-body">
                            <?php if ($chartData['tipo'] !== 'none'): ?>
                                <canvas id="chart-<?= $widgetId ?>" class="pbi-chart-canvas"></canvas>
                                <script>
                                window._pbiCharts = window._pbiCharts || [];
                                window._pbiCharts.push({
                                    id: 'chart-<?= $widgetId ?>',
                                    data: <?= json_encode($chartData, JSON_UNESCAPED_UNICODE) ?>,
                                    grupo: <?= json_encode($config['grupo'] ?? '', JSON_UNESCAPED_UNICODE) ?>
                                });
                                </script>
                            <?php endif; ?>

                            <?php if (in_array($chartConfig['tipo'] ?? '', ['none']) || $largura === 12): ?>
                                <?php $this->_renderTable($data, $chartConfig['tipo'] ?? 'none', $relId, $config['grupo'] ?? ''); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        </div><!-- /pbi-container -->

        <!-- ═══ Modal: Adicionar Widget ═══ -->
        <div class="modal fade" id="pbiAddWidgetModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title"><i class="fa fa-plus"></i> Adicionar Widget</h4>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="pbi-add-tab-id" value="">
                        <div class="form-group">
                            <label>Selecionar Relatório:</label>
                            <input type="text" class="form-control" id="pbi-search-relatorio" placeholder="Buscar relatório...">
                        </div>
                        <div class="form-group">
                            <label>Largura:</label>
                            <select class="form-control" id="pbi-widget-largura">
                                <option value="6">Meia tela (6 colunas)</option>
                                <option value="12">Tela inteira (12 colunas)</option>
                                <option value="4">1/3 de tela (4 colunas)</option>
                            </select>
                        </div>
                        <div id="pbi-relatorio-list">
                            <?php foreach ($relatorios as $r): ?>
                            <div class="pbi-rel-item" data-id="<?= $r['id'] ?>" data-titulo="<?= pbi_e($r['titulo']) ?>">
                                <label class="pbi-rel-label">
                                    <input type="radio" name="pbi_relatorio" value="<?= $r['id'] ?>">
                                    <i class="fa fa-bar-chart text-primary"></i>
                                    <?= pbi_e($r['titulo']) ?>
                                    <small class="text-muted">(<?= $r['modulo_base'] ?>)</small>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" onclick="pbiDoAddWidget()">Adicionar</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Modal: Novo Board/Tab ═══ -->
        <div class="modal fade" id="pbiNameModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <button class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title" id="pbiNameModalTitle">Nome</h4>
                    </div>
                    <div class="modal-body">
                        <input type="text" class="form-control" id="pbiNameInput" placeholder="Nome...">
                        <input type="hidden" id="pbiNameAction">
                        <input type="hidden" id="pbiNameId">
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-default" data-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" onclick="pbiDoNameAction()">Salvar</button>
                    </div>
                </div>
            </div>
        </div>

        <?php $this->_chartScript($boardId, $tabId); ?>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }

    // ─── Render table ─────────────────────────────────────────────────────────

    private function _renderTable(array $data, string $chartTipo = 'bar', int $relId = 0, string $grupo = ''): void {
        if (empty($data['dados'])) {
            echo '<p class="text-muted text-center" style="margin:10px 0"><small>Sem dados</small></p>';
            return;
        }
        $maxRows = $chartTipo === 'none' ? 50 : 8;
        $rows    = array_slice($data['dados'], 0, $maxRows);
        ?>
        <div class="table-responsive pbi-table-wrap">
            <table class="table table-condensed table-hover pbi-table">
                <thead><tr>
                    <?php foreach ($data['labels'] as $lbl): ?>
                        <th><?= pbi_e($lbl) ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                    <?php foreach ($rows as $row):
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
            <?php if (count($data['dados']) > $maxRows): ?>
                <p class="text-muted text-center" style="font-size:11px">
                    Mostrando <?= $maxRows ?> de <?= $data['total'] ?> registros.
                    <?php if ($relId): ?>
                    <a href="?module=PainelBI&view=Relatorio&record=<?= $relId ?>">Ver todos</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function _getBoardTitle(array $boards, int $boardId): string {
        foreach ($boards as $b) {
            if ((int)$b['id'] === $boardId) return $b['titulo'];
        }
        return 'Selecionar Board';
    }

    // ─── CSS ──────────────────────────────────────────────────────────────────

    private function _css(): string { return '<style>
        .pbi-container          { padding: 0; }
        /* Header */
        .pbi-header             { display:flex; align-items:center; justify-content:space-between; padding:10px 15px; background:#fff; border-bottom:1px solid #e0e0e0; flex-wrap:wrap; gap:8px; }
        .pbi-header-left        { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
        .pbi-header-right       { display:flex; align-items:center; gap:6px; }
        .pbi-board-btn          { font-weight:600; background:#fff; border-color:#bbb; }
        .pbi-board-menu         { min-width:200px; }
        /* KPIs */
        .pbi-kpis               { display:flex; gap:15px; }
        .pbi-kpi                { display:flex; flex-direction:column; align-items:center; padding:4px 10px; border-left:1px solid #e8e8e8; }
        .pbi-kpi b              { font-size:18px; color:#2c3e50; line-height:1; }
        .pbi-kpi small          { font-size:10px; color:#7f8c8d; text-transform:uppercase; }
        .pbi-kpi-warn b         { color:#e67e22; }
        /* Tabs */
        .pbi-tabs               { margin:0; border-bottom:2px solid #dee2e6; padding:0 12px; background:#f8f9fa; }
        .pbi-tabs > li > a      { border-radius:0; padding:8px 15px; font-size:13px; color:#555; }
        .pbi-tabs > li.active > a { border-bottom:2px solid #3498db; color:#3498db; font-weight:600; background:transparent; }
        /* Widget Area */
        .pbi-widgets-area       { padding:12px; background:#f4f5f7; min-height:300px; }
        .pbi-widget-row         { margin:0 -6px; }
        .pbi-widget-col         { padding:6px; }
        /* Widget Card */
        .pbi-widget             { border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.08); margin:0; }
        .pbi-widget-header      { background:#2c3e50; color:#fff; padding:10px 14px; border-radius:6px 6px 0 0; display:flex; align-items:center; justify-content:space-between; font-size:13px; font-weight:600; }
        .pbi-widget-actions     { display:flex; gap:8px; }
        .pbi-widget-actions a   { color:rgba(255,255,255,0.7); font-size:12px; cursor:pointer; }
        .pbi-widget-actions a:hover { color:#fff; }
        .pbi-widget-body        { padding:10px; background:#fff; border-radius:0 0 6px 6px; }
        .pbi-chart-canvas       { max-height:260px; }
        /* Table */
        .pbi-table-wrap         { max-height:280px; overflow-y:auto; }
        .pbi-table              { font-size:12px; margin-bottom:0; }
        .pbi-table thead th     { background:#f8f9fa; font-size:11px; text-transform:uppercase; color:#666; position:sticky; top:0; }
        /* Empty */
        .pbi-empty              { text-align:center; padding:60px 20px; color:#aaa; }
        .pbi-empty p            { margin:12px 0; }
        /* Modal */
        #pbi-relatorio-list     { max-height:300px; overflow-y:auto; border:1px solid #e0e0e0; border-radius:4px; padding:5px; }
        .pbi-rel-item           { padding:5px 8px; border-radius:3px; }
        .pbi-rel-item:hover     { background:#f0f7ff; }
        .pbi-rel-label          { display:flex; align-items:center; gap:8px; font-weight:normal; cursor:pointer; margin:0; }
    </style>'; }

    // ─── Chart.js Script ──────────────────────────────────────────────────────

    private function _chartScript(int $boardId, int $tabId): void { ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        // Mapeamento campo PainelBI → campo de busca vTiger
        var _pbiFieldMap = {
            'leadstatus': 'leadstatus', 'leadsource': 'leadsource',
            'user_name': 'assigned_user_id', 'data_criacao': 'createdtime',
            'mes_criacao': 'createdtime', 'semana_criacao': 'createdtime',
            'firstname': 'firstname', 'lastname': 'lastname',
            'company': 'company', 'email': 'email', 'phone': 'phone'
        };
        var _pbiAllViewId = <?= (int)$allViewId ?>;

        function pbiDrillDown(grupo, label) {
            if (!grupo || !label) return;
            var searchKey = _pbiFieldMap[grupo] || grupo;
            // Campos de data/tempo não fazem drill-down simples
            if (['data_criacao','mes_criacao','semana_criacao','createdtime','modifiedtime'].indexOf(grupo) >= 0) return;
            var val = label;
            if (val === '(sem status)' || val === '(sem valor)' || val === 'Não informado') { val = ''; }
            // Usar search_params (formato nativo vTiger) + forçar view "Todos"
            var params = JSON.stringify([[[searchKey, 'e', val]]]);
            var url = 'index.php?module=Leads&view=List&search_params=' + encodeURIComponent(params);
            if (_pbiAllViewId) url += '&viewname=' + _pbiAllViewId;
            window.location.href = url;
        }

        // Inicializa todos os gráficos registrados
        document.addEventListener('DOMContentLoaded', function() {
            (window._pbiCharts || []).forEach(function(cfg) {
                var canvas = document.getElementById(cfg.id);
                if (!canvas) return;
                var d = cfg.data;
                var opts = d.options || {};
                var grupo = cfg.grupo || '';
                var chart = new Chart(canvas, {
                    type: d.tipo,
                    data: { labels: d.labels, datasets: d.datasets },
                    options: {
                        responsive: true,
                        indexAxis: d.is_h ? 'y' : 'x',
                        plugins: {
                            legend: { display: !!opts.mostrar_legenda, position: opts.posicao_legenda || 'top' },
                            datalabels: false,
                        },
                        scales: d.tipo === 'pie' || d.tipo === 'doughnut' ? {} : {
                            x: { grid: { display: !!opts.mostrar_grid } },
                            y: { grid: { display: !!opts.mostrar_grid }, beginAtZero: true }
                        },
                        onClick: function(evt, elements) {
                            if (!elements.length || !grupo) return;
                            var idx = elements[0].index;
                            var label = d.labels[idx];
                            pbiDrillDown(grupo, label);
                        }
                    }
                });
                // Cursor pointer ao passar sobre elementos clicáveis
                if (grupo) {
                    canvas.style.cursor = 'default';
                    canvas.addEventListener('mousemove', function(e) {
                        var pts = chart.getElementsAtEventForMode(e, 'nearest', {intersect: true}, false);
                        canvas.style.cursor = pts.length ? 'pointer' : 'default';
                    });
                }
            });
        });

        // ─── Busca de relatório no modal ───
        document.getElementById('pbi-search-relatorio') && document.getElementById('pbi-search-relatorio').addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('.pbi-rel-item').forEach(function(el) {
                var titulo = el.getAttribute('data-titulo').toLowerCase();
                el.style.display = titulo.includes(q) ? '' : 'none';
            });
        });

        // ─── Add Widget ───
        function pbiShowAddWidget(tabId) {
            document.getElementById('pbi-add-tab-id').value = tabId;
            document.querySelector('input[name="pbi_relatorio"]') && (document.querySelector('input[name="pbi_relatorio"]').checked = false);
            jQuery('#pbiAddWidgetModal').modal('show');
        }

        function pbiDoAddWidget() {
            var tabId   = document.getElementById('pbi-add-tab-id').value;
            var relEl   = document.querySelector('input[name="pbi_relatorio"]:checked');
            var largura = document.getElementById('pbi-widget-largura').value;
            if (!relEl) { alert('Selecione um relatório.'); return; }
            var relId = relEl.value;
            pbiAjax('AddWidget', {tab_id: tabId, relatorio_id: relId, largura: largura}, function(r) {
                if (r.success) { location.reload(); }
                else { alert('Erro: ' + (r.error || 'desconhecido')); }
            });
        }

        function pbiRemoveWidget(widgetId) {
            if (!confirm('Remover este widget?')) return;
            pbiAjax('RemoveWidget', {widget_id: widgetId}, function(r) {
                if (r.success) { document.querySelector('[data-widget-id="'+widgetId+'"]').remove(); }
            });
        }

        // ─── Board / Tab ───
        function pbiAddBoard() {
            pbiShowNameModal('Novo Board', 'add_board', 0);
        }
        function pbiRenameBoard(id) {
            pbiShowNameModal('Renomear Board', 'rename_board', id);
        }
        function pbiDeleteBoard(id) {
            if (!confirm('Excluir este board e todas as suas abas?')) return;
            pbiAjax('DeleteBoard', {board_id: id}, function(r) {
                if (r.success) { location.href = '?module=PainelBI&view=Dashboard'; }
            });
        }
        function pbiAddTab(boardId) {
            pbiShowNameModal('Nova Aba', 'add_tab', boardId);
        }
        function pbiRenameTab(id) {
            pbiShowNameModal('Renomear Aba', 'rename_tab', id);
        }
        function pbiDeleteTab(id) {
            if (!confirm('Excluir esta aba e seus widgets?')) return;
            pbiAjax('DeleteTab', {tab_id: id}, function(r) {
                if (r.success) { location.href = '?module=PainelBI&view=Dashboard&board_id=<?= $boardId ?>'; }
            });
        }

        function pbiShowNameModal(title, action, id) {
            document.getElementById('pbiNameModalTitle').textContent = title;
            document.getElementById('pbiNameAction').value = action;
            document.getElementById('pbiNameId').value = id;
            document.getElementById('pbiNameInput').value = '';
            jQuery('#pbiNameModal').modal('show');
        }
        function pbiDoNameAction() {
            var action  = document.getElementById('pbiNameAction').value;
            var id      = document.getElementById('pbiNameId').value;
            var titulo  = document.getElementById('pbiNameInput').value.trim();
            if (!titulo) { alert('Informe um nome.'); return; }

            var boardId = <?= $boardId ?: 0 ?>;
            var tabId   = <?= $tabId ?: 0 ?>;

            var params = {titulo: titulo};
            if (action === 'add_board')    { params.action_type = 'add_board'; params.compartilhado = 0; }
            if (action === 'rename_board') { params.action_type = 'rename_board'; params.board_id = id; }
            if (action === 'add_tab')      { params.action_type = 'add_tab'; params.board_id = id; }
            if (action === 'rename_tab')   { params.action_type = 'rename_tab'; params.tab_id = id; }

            pbiAjax('SaveBoard', params, function(r) {
                if (r.success) {
                    jQuery('#pbiNameModal').modal('hide');
                    location.reload();
                } else { alert('Erro: ' + (r.error || 'desconhecido')); }
            });
        }

        // ─── AJAX helper ───
        function pbiAjax(view, data, cb) {
            data.module = 'PainelBI';
            data.view   = view;
            jQuery.ajax({
                url: 'index.php',
                type: 'POST',
                data: data,
                dataType: 'json',
                success: cb,
                error: function() { cb({success:false,error:'Erro de rede'}); }
            });
        }
        </script>
        <?php
    }
}
