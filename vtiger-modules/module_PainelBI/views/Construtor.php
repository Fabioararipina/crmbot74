<?php
/**
 * PainelBI_Construtor_View
 * Construtor de Relatórios — similar ao VTExperts Report Builder:
 *   · Seção "Gráfico": Group by, Data fields (com função agr.), Opções avançadas
 *   · Seção "Condições": Grupos AND/OR, Add Condition dinâmico por campo
 *   · Seção "Colunas": checkboxes (para relatórios detalhados)
 *   · Preview AJAX ao vivo
 */
class PainelBI_Construtor_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/PainelBI/models/DataProvider.php';

        $dp  = new PainelBI_DataProvider_Model();
        $id  = (int)$request->get('record');
        $dup = (int)$request->get('duplicate');

        // Relatório existente ou novo
        $rel = $id ? $dp->getRelatorio($id) : null;
        if ($dup && $rel) { $rel['id'] = 0; $rel['titulo'] = 'Cópia de ' . $rel['titulo']; }

        $config      = $rel ? (json_decode($rel['config'] ?? '{}', true) ?? []) : [];
        $chartConfig = $config['chart'] ?? ['tipo'=>'bar','campo_label'=>'','campos_dados'=>[],'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>true,'posicao_legenda'=>'top'];
        $condGrupos  = $config['condicoes_grupos'] ?? [['condicoes'=>[]]];
        $colunas     = $config['colunas'] ?? ['nome_completo','phone','leadstatus','atendente','createdtime'];

        $fields     = PainelBI_DataProvider_Model::getLeadsFields();
        $aggFuncs   = PainelBI_DataProvider_Model::getAggregateFunctions();
        $chartTypes = PainelBI_DataProvider_Model::getChartTypes();
        $operators  = PainelBI_DataProvider_Model::getOperators();
        $periods    = PainelBI_DataProvider_Model::getPeriodOptions();

        $statuses = $dp->getLeadStatuses();
        $sources  = $dp->getLeadSources();
        $users    = $dp->getUsers();

        $tipo        = $rel['tipo'] ?? 'summary';
        $modulo      = $rel['modulo_base'] ?? 'Leads';
        $titulo      = $rel['titulo'] ?? '';
        $compartilhado = (int)($rel['compartilhado'] ?? 1);
        $grupoAtual  = $config['grupo'] ?? null;
        $agregacoes  = $config['agregacoes'] ?? [['func'=>'COUNT','campo'=>'*','alias'=>'total']];
        $ordem       = $config['ordem'] ?? 'createdtime';
        $ordemDir    = $config['ordem_dir'] ?? 'DESC';
        $limite      = $config['limite'] ?? 500;

        echo $this->_css();

        $fieldsJson    = json_encode($fields, JSON_UNESCAPED_UNICODE);
        $operatorsJson = json_encode($operators, JSON_UNESCAPED_UNICODE);
        $periodsJson   = json_encode($periods, JSON_UNESCAPED_UNICODE);
        $statusesJson  = json_encode($statuses, JSON_UNESCAPED_UNICODE);
        $sourcesJson   = json_encode($sources, JSON_UNESCAPED_UNICODE);
        $usersJson     = json_encode(array_map(fn($u) => ['id'=>$u['id'],'nome'=>trim($u['nome']),'user_name'=>$u['user_name']], $users), JSON_UNESCAPED_UNICODE);
        ?>

        <div class="pbi-con-container">

        <!-- ═══ Cabeçalho ═══ -->
        <div class="pbi-con-header">
            <div style="display:flex;gap:8px;align-items:center">
                <a href="?module=PainelBI&view=List" class="btn btn-sm btn-default">
                    <i class="fa fa-arrow-left"></i>
                </a>
                <input type="text" id="pbi-titulo" class="form-control pbi-titulo-input" value="<?= htmlspecialchars($titulo) ?>" placeholder="Nome do Relatório...">
            </div>
            <div style="display:flex;gap:6px">
                <label class="pbi-share-chk">
                    <input type="checkbox" id="pbi-compartilhado" <?= $compartilhado ? 'checked' : '' ?>>
                    Compartilhado
                </label>
                <button class="btn btn-sm btn-default" onclick="pbiPreview()">
                    <i class="fa fa-play"></i> Preview
                </button>
                <button class="btn btn-sm btn-primary" onclick="pbiSalvar()">
                    <i class="fa fa-save"></i> Salvar e Executar
                </button>
            </div>
        </div>

        <!-- Configurações rápidas: módulo, tipo -->
        <div class="pbi-con-meta">
            <div class="form-inline" style="display:flex;gap:15px;align-items:center;flex-wrap:wrap">
                <div class="form-group">
                    <label>Módulo: </label>
                    <select class="form-control input-sm" id="pbi-modulo">
                        <option value="Leads" <?= $modulo === 'Leads' ? 'selected' : '' ?>>Leads</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo de Relatório: </label>
                    <select class="form-control input-sm" id="pbi-tipo" onchange="pbiTipoChanged()">
                        <option value="summary" <?= $tipo === 'summary' ? 'selected' : '' ?>>Resumo (agrupado)</option>
                        <option value="detail"  <?= $tipo === 'detail'  ? 'selected' : '' ?>>Detalhado (linhas)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ordenar por: </label>
                    <select class="form-control input-sm" id="pbi-ordem">
                        <?php foreach ($fields as $k => $f): ?>
                            <option value="<?= $k ?>" <?= $ordem === $k ? 'selected' : '' ?>><?= htmlspecialchars($f['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-control input-sm" id="pbi-ordem-dir">
                        <option value="DESC" <?= $ordemDir === 'DESC' ? 'selected' : '' ?>>DESC</option>
                        <option value="ASC"  <?= $ordemDir === 'ASC'  ? 'selected' : '' ?>>ASC</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Limite: </label>
                    <input type="number" class="form-control input-sm" id="pbi-limite" value="<?= (int)$limite ?>" min="1" max="5000" style="width:80px">
                </div>
            </div>
        </div>

        <!-- ═══ Seção: Gráfico ═══ -->
        <div class="pbi-section" id="pbi-sec-chart">
            <div class="pbi-section-header" onclick="pbiToggle('pbi-sec-chart-body','chev-chart')">
                <i class="fa fa-chevron-down pbi-chevron" id="chev-chart"></i>
                <i class="fa fa-bar-chart"></i> <b>Gráfico do Relatório</b>
            </div>
            <div class="pbi-section-body" id="pbi-sec-chart-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Campo de Agrupamento <span class="text-danger">*</span></label>
                            <select class="form-control" id="pbi-grupo">
                                <option value="">(sem agrupamento)</option>
                                <?php foreach ($fields as $k => $f):
                                    if (!empty($f['no_group'])) continue;
                                ?>
                                    <option value="<?= $k ?>" <?= $grupoAtual === $k ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($f['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>Campos de Dados <span class="text-danger">*</span></label>
                            <div id="pbi-agg-list" class="pbi-agg-list">
                                <?php foreach ($agregacoes as $ai => $agg): ?>
                                <div class="pbi-agg-row" data-idx="<?= $ai ?>">
                                    <select class="form-control input-sm pbi-agg-func">
                                        <?php foreach ($aggFuncs as $fn => $fl): ?>
                                            <option value="<?= $fn ?>" <?= ($agg['func']??'COUNT') === $fn ? 'selected' : '' ?>><?= $fl ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="form-control input-sm pbi-agg-campo">
                                        <option value="*" <?= ($agg['campo']??'*') === '*' ? 'selected' : '' ?>>Total (*)</option>
                                        <?php foreach ($fields as $k => $f): ?>
                                            <option value="<?= $k ?>" <?= ($agg['campo']??'*') === $k ? 'selected' : '' ?>><?= htmlspecialchars($f['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="btn btn-xs btn-danger" onclick="this.closest('.pbi-agg-row').remove()">×</button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-xs btn-default" onclick="pbiAddAgg()" style="margin-top:5px">
                                <i class="fa fa-plus"></i> Adicionar Campo
                            </button>
                            <p class="help-block">Para Barras e Linhas, máximo 3 campos de dados.</p>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Posição da Legenda</label>
                            <select class="form-control" id="pbi-legend-pos">
                                <?php foreach (['top'=>'Topo','bottom'=>'Baixo','left'=>'Esquerda','right'=>'Direita'] as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($chartConfig['posicao_legenda']??'top') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <a href="javascript:void(0)" class="pbi-adv-link" onclick="pbiToggle('pbi-adv-opts','chev-adv')">
                            Opções Avançadas
                        </a>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tipo de Gráfico</label>
                            <select class="form-control" id="pbi-chart-tipo">
                                <?php foreach ($chartTypes as $v => $l): ?>
                                    <option value="<?= $v ?>" <?= ($chartConfig['tipo']??'bar') === $v ? 'selected' : '' ?>><?= $l ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Opções Avançadas (colapsável) -->
                <div id="pbi-adv-opts" style="display:none" class="pbi-adv-opts">
                    <div class="row">
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Exibir Grade</label>
                                <select class="form-control input-sm" id="pbi-opt-grid">
                                    <option value="1" <?= ($chartConfig['mostrar_grid']??true) ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= !($chartConfig['mostrar_grid']??true) ? 'selected' : '' ?>>Não</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Exibir Rótulo</label>
                                <select class="form-control input-sm" id="pbi-opt-label">
                                    <option value="1" <?= ($chartConfig['mostrar_label']??true) ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= !($chartConfig['mostrar_label']??true) ? 'selected' : '' ?>>Não</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label>Valor na Legenda</label>
                                <select class="form-control input-sm" id="pbi-opt-legenda">
                                    <option value="1" <?= ($chartConfig['mostrar_legenda']??true) ? 'selected' : '' ?>>Sim</option>
                                    <option value="0" <?= !($chartConfig['mostrar_legenda']??true) ? 'selected' : '' ?>>Não</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top:5px;font-size:12px">
                    <i class="fa fa-info-circle"></i>
                    Selecione ao menos um campo de agrupamento e um campo de dados. Para Barras e Linhas, máximo 3 campos de dados.
                </div>
            </div>
        </div>

        <!-- ═══ Seção: Condições ═══ -->
        <div class="pbi-section">
            <div class="pbi-section-header" onclick="pbiToggle('pbi-cond-build','chev-cond-b')">
                <i class="fa fa-chevron-down pbi-chevron" id="chev-cond-b"></i>
                <i class="fa fa-filter"></i> <b>Condições do Relatório</b>
                <button type="button" class="btn btn-xs btn-primary" style="margin-left:auto" onclick="event.stopPropagation(); pbiAddGroup()">
                    <i class="fa fa-plus"></i> Adicionar Grupo
                </button>
            </div>
            <div class="pbi-section-body" id="pbi-cond-build">
                <div id="pbi-grupos-container">
                    <?php foreach ($condGrupos as $gi => $grupo): ?>
                    <div class="pbi-cond-grupo" data-gi="<?= $gi ?>">
                        <div class="pbi-cond-grupo-header">
                            <?php if ($gi === 0): ?>
                                <b>Todas as Condições</b>
                                <small class="text-muted">(todas as condições devem ser atendidas)</small>
                            <?php else: ?>
                                <b>OU — Grupo <?= $gi + 1 ?></b>
                                <small class="text-muted">(basta um grupo ser atendido)</small>
                                <button type="button" class="btn btn-xs btn-danger pbi-btn-del-group" onclick="this.closest('.pbi-cond-grupo').remove()">
                                    <i class="fa fa-times"></i> Remover Grupo
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="pbi-cond-rows">
                            <?php foreach ($grupo['condicoes'] ?? [] as $ci => $cond): ?>
                            <div class="pbi-cond-row-build">
                                <?php $this->_renderCondRow($cond, $fields, $operators, $periods, $statuses, $sources, $users); ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-xs btn-default pbi-add-cond-btn" onclick="pbiAddCond(this)">
                            <i class="fa fa-plus"></i> Adicionar Condição
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ═══ Seção: Colunas (só para relatório Detalhado) ═══ -->
        <div class="pbi-section" id="pbi-sec-colunas">
            <div class="pbi-section-header" onclick="pbiToggle('pbi-cols-body','chev-cols')">
                <i class="fa fa-chevron-down pbi-chevron" id="chev-cols"></i>
                <i class="fa fa-columns"></i> <b>Colunas Exibidas</b>
                <small class="pbi-section-meta">(apenas para relatório Detalhado)</small>
            </div>
            <div class="pbi-section-body" id="pbi-cols-body" style="display:none">
                <div class="row">
                    <?php foreach ($fields as $k => $f): ?>
                    <div class="col-md-3" style="margin-bottom:5px">
                        <label class="pbi-col-chk">
                            <input type="checkbox" name="pbi_colunas" value="<?= $k ?>" <?= in_array($k, $colunas) ? 'checked' : '' ?>>
                            <?= htmlspecialchars($f['label']) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ═══ Preview ═══ -->
        <div class="pbi-section">
            <div class="pbi-section-header">
                <i class="fa fa-table"></i> <b>Preview dos Dados</b>
                <button class="btn btn-xs btn-primary" style="margin-left:auto" onclick="pbiPreview()">
                    <i class="fa fa-refresh"></i> Atualizar Preview
                </button>
            </div>
            <div id="pbi-preview-area" class="pbi-preview-area">
                <p class="text-muted text-center" style="padding:20px">Clique em "Preview" para visualizar os dados.</p>
            </div>
        </div>

        </div><!-- /pbi-con-container -->

        <script>
        // ─── Estado dos campos ───
        var PBI_FIELDS    = <?= $fieldsJson ?>;
        var PBI_OPERATORS = <?= $operatorsJson ?>;
        var PBI_PERIODS   = <?= $periodsJson ?>;
        var PBI_STATUSES  = <?= $statusesJson ?>;
        var PBI_SOURCES   = <?= $sourcesJson ?>;
        var PBI_USERS     = <?= $usersJson ?>;

        // ─── Toggle seções ───
        function pbiToggle(id, chevId) {
            var el = document.getElementById(id);
            if (!el) return;
            var open = el.style.display !== 'none';
            el.style.display = open ? 'none' : '';
            if (chevId) {
                var chev = document.getElementById(chevId);
                if (chev) chev.style.transform = open ? 'rotate(-90deg)' : '';
            }
        }

        // ─── Tipo de relatório ───
        function pbiTipoChanged() {
            var tipo = document.getElementById('pbi-tipo').value;
            document.getElementById('pbi-sec-colunas').style.display = tipo === 'detail' ? '' : 'none';
        }
        pbiTipoChanged();

        // ─── Adicionar campo de dados (agregação) ───
        function pbiAddAgg() {
            var html = '<div class="pbi-agg-row">' +
                '<select class="form-control input-sm pbi-agg-func">' +
                Object.entries({COUNT:"Contagem",SUM:"Soma",AVG:"Média",MAX:"Máximo",MIN:"Mínimo"}).map(([v,l]) => '<option value="'+v+'">'+l+'</option>').join('') +
                '</select>' +
                '<select class="form-control input-sm pbi-agg-campo">' +
                '<option value="*">Total (*)</option>' +
                Object.entries(PBI_FIELDS).map(([k,f]) => '<option value="'+k+'">'+f.label+'</option>').join('') +
                '</select>' +
                '<button type="button" class="btn btn-xs btn-danger" onclick="this.closest(\'.pbi-agg-row\').remove()">×</button>' +
                '</div>';
            document.getElementById('pbi-agg-list').insertAdjacentHTML('beforeend', html);
        }

        // ─── Adicionar grupo de condições ───
        function pbiAddGroup() {
            var container = document.getElementById('pbi-grupos-container');
            var gi = container.querySelectorAll('.pbi-cond-grupo').length;
            var html = '<div class="pbi-cond-grupo" data-gi="'+gi+'">' +
                '<div class="pbi-cond-grupo-header">' +
                '<b>OU — Grupo '+(gi+1)+'</b>' +
                '<small class="text-muted"> (basta um grupo ser atendido)</small>' +
                '<button type="button" class="btn btn-xs btn-danger pbi-btn-del-group" onclick="this.closest(\'.pbi-cond-grupo\').remove()">' +
                '<i class="fa fa-times"></i> Remover Grupo</button></div>' +
                '<div class="pbi-cond-rows"></div>' +
                '<button type="button" class="btn btn-xs btn-default pbi-add-cond-btn" onclick="pbiAddCond(this)">' +
                '<i class="fa fa-plus"></i> Adicionar Condição</button></div>';
            container.insertAdjacentHTML('beforeend', html);
        }

        // ─── Adicionar condição numa row ───
        function pbiAddCond(btn) {
            var grupo  = btn.closest('.pbi-cond-grupo');
            var rows   = grupo.querySelector('.pbi-cond-rows');
            var isFirst= rows.querySelectorAll('.pbi-cond-row-build').length === 0;

            // Select de campo
            var fieldOpts = Object.entries(PBI_FIELDS).map(([k,f]) => '<option value="'+k+'">'+f.label+'</option>').join('');

            var html = '<div class="pbi-cond-row-build">' +
                (isFirst ? '' : '<span class="pbi-cond-and-lbl">E</span>') +
                '<select class="form-control input-sm pbi-cond-campo" onchange="pbiUpdateOp(this)">' +
                fieldOpts + '</select>' +
                '<select class="form-control input-sm pbi-cond-op" onchange="pbiUpdateVal(this)"></select>' +
                '<div class="pbi-cond-val-wrap"></div>' +
                '<button type="button" class="btn btn-xs btn-danger" onclick="this.closest(\'.pbi-cond-row-build\').remove()">×</button>' +
                '</div>';

            rows.insertAdjacentHTML('beforeend', html);
            var newRow = rows.lastElementChild;
            var campoEl = newRow.querySelector('.pbi-cond-campo');
            pbiUpdateOp(campoEl);
        }

        // ─── Atualizar operadores ao mudar campo ───
        function pbiUpdateOp(campoEl) {
            var campo  = campoEl.value;
            var field  = PBI_FIELDS[campo];
            var tipo   = field ? field.type : 'text';
            var ops    = PBI_OPERATORS[tipo] || PBI_OPERATORS.text;
            var opEl   = campoEl.closest('.pbi-cond-row-build').querySelector('.pbi-cond-op');
            opEl.innerHTML = Object.entries(ops).map(([v,l]) => '<option value="'+v+'">'+l+'</option>').join('');
            pbiUpdateVal(opEl);
        }

        // ─── Atualizar input de valor ao mudar operador ───
        function pbiUpdateVal(opEl) {
            var row    = opEl.closest('.pbi-cond-row-build');
            var campo  = row.querySelector('.pbi-cond-campo').value;
            var op     = opEl.value;
            var field  = PBI_FIELDS[campo];
            var tipo   = field ? field.type : 'text';
            var wrap   = row.querySelector('.pbi-cond-val-wrap');

            if (op === 'is_empty' || op === 'is_not_empty') {
                wrap.innerHTML = '';
                return;
            }
            if (op === 'in_period') {
                wrap.innerHTML = '<select class="form-control input-sm pbi-cond-val">' +
                    Object.entries(PBI_PERIODS).map(([v,l]) => '<option value="'+v+'">'+l+'</option>').join('') +
                    '</select>';
                return;
            }
            if (op === 'between') {
                var inp = (tipo === 'datetime' || tipo === 'date') ? 'date' : 'number';
                wrap.innerHTML = '<input type="'+inp+'" class="form-control input-sm pbi-cond-val1" placeholder="De">' +
                    '<span style="margin:0 4px">—</span>' +
                    '<input type="'+inp+'" class="form-control input-sm pbi-cond-val2" placeholder="Até">';
                return;
            }
            if ((op === 'in_list' || op === 'not_in_list') && campo === 'leadstatus') {
                wrap.innerHTML = '<select class="form-control input-sm pbi-cond-val" multiple>' +
                    PBI_STATUSES.map(s => '<option value="'+s+'">'+s+'</option>').join('') +
                    '</select>';
                return;
            }
            if ((op === 'in_list' || op === 'not_in_list') && campo === 'leadsource') {
                wrap.innerHTML = '<select class="form-control input-sm pbi-cond-val" multiple size="4">' +
                    PBI_SOURCES.map(s => '<option value="'+s+'">'+s+'</option>').join('') +
                    '</select>';
                return;
            }
            if (campo === 'leadstatus' && op === 'eq') {
                wrap.innerHTML = '<select class="form-control input-sm pbi-cond-val">' +
                    PBI_STATUSES.map(s => '<option value="'+s+'">'+s+'</option>').join('') +
                    '</select>';
                return;
            }
            if (campo === 'leadsource' && op === 'eq') {
                wrap.innerHTML = '<select class="form-control input-sm pbi-cond-val">' +
                    PBI_SOURCES.map(s => '<option value="'+s+'">'+s+'</option>').join('') +
                    '</select>';
                return;
            }
            // Default: text input
            var inpType = (tipo === 'datetime' || tipo === 'date') ? 'date' : (tipo === 'integer' ? 'number' : 'text');
            wrap.innerHTML = '<input type="'+inpType+'" class="form-control input-sm pbi-cond-val" placeholder="Valor...">';
        }

        // ─── Coletar config do formulário ───
        function pbiGetConfig() {
            var tipo  = document.getElementById('pbi-tipo').value;
            var grupo = document.getElementById('pbi-grupo').value;

            // Agregações
            var agregacoes = [];
            document.querySelectorAll('#pbi-agg-list .pbi-agg-row').forEach(function(row, i) {
                var func  = row.querySelector('.pbi-agg-func').value;
                var campo = row.querySelector('.pbi-agg-campo').value;
                agregacoes.push({func:func, campo:campo, alias: func.toLowerCase()+'_'+i});
            });

            // Condições
            var condGrupos = [];
            document.querySelectorAll('.pbi-cond-grupo').forEach(function(gEl) {
                var conds = [];
                gEl.querySelectorAll('.pbi-cond-row-build').forEach(function(rEl) {
                    var campo = rEl.querySelector('.pbi-cond-campo') ? rEl.querySelector('.pbi-cond-campo').value : '';
                    var op    = rEl.querySelector('.pbi-cond-op')    ? rEl.querySelector('.pbi-cond-op').value    : '';
                    if (!campo || !op) return;

                    var valor;
                    var val1El = rEl.querySelector('.pbi-cond-val1');
                    var val2El = rEl.querySelector('.pbi-cond-val2');
                    var valEl  = rEl.querySelector('.pbi-cond-val');

                    if (val1El && val2El) {
                        valor = [val1El.value, val2El.value];
                    } else if (valEl && valEl.multiple) {
                        valor = Array.from(valEl.selectedOptions).map(o => o.value);
                    } else if (valEl) {
                        valor = valEl.value;
                    } else {
                        valor = '';
                    }
                    conds.push({campo:campo, op:op, valor:valor});
                });
                if (conds.length > 0) condGrupos.push({condicoes:conds});
            });

            // Colunas (relatório detalhado)
            var colunas = [];
            document.querySelectorAll('input[name="pbi_colunas"]:checked').forEach(function(el) {
                colunas.push(el.value);
            });
            if (!colunas.length) colunas = ['nome_completo','phone','leadstatus','atendente','createdtime'];

            // Campos de dados para o gráfico
            var camposDados = agregacoes.map(a => a.alias);

            return {
                tipo: tipo,
                grupo: grupo || null,
                agregacoes: agregacoes,
                colunas: colunas,
                condicoes_grupos: condGrupos,
                ordem: document.getElementById('pbi-ordem').value,
                ordem_dir: document.getElementById('pbi-ordem-dir').value,
                limite: parseInt(document.getElementById('pbi-limite').value) || 500,
                chart: {
                    tipo: document.getElementById('pbi-chart-tipo').value,
                    campo_label: grupo || (tipo === 'summary' ? 'leadstatus' : ''),
                    campos_dados: camposDados,
                    mostrar_grid:    !!parseInt(document.getElementById('pbi-opt-grid').value),
                    mostrar_label:   !!parseInt(document.getElementById('pbi-opt-label').value),
                    mostrar_legenda: !!parseInt(document.getElementById('pbi-opt-legenda').value),
                    posicao_legenda: document.getElementById('pbi-legend-pos').value,
                }
            };
        }

        // ─── Preview AJAX ───
        function pbiPreview() {
            var config = pbiGetConfig();
            var area   = document.getElementById('pbi-preview-area');
            area.innerHTML = '<p class="text-muted text-center" style="padding:20px"><i class="fa fa-spinner fa-spin"></i> Carregando...</p>';

            jQuery.ajax({
                url: 'index.php',
                type: 'POST',
                data: {
                    module: 'PainelBI',
                    view: 'GetData',
                    config: JSON.stringify(config)
                },
                dataType: 'json',
                success: function(r) {
                    if (!r.success) { area.innerHTML = '<div class="alert alert-danger" style="margin:10px">Erro: '+r.error+'</div>'; return; }
                    var d = r.data;
                    if (!d.dados || !d.dados.length) {
                        area.innerHTML = '<p class="text-muted text-center" style="padding:20px">Nenhum registro encontrado.</p>';
                        return;
                    }
                    // Tabela
                    var html = '<div class="table-responsive" style="max-height:300px;overflow-y:auto"><table class="table table-condensed table-bordered" style="font-size:12px"><thead><tr>';
                    d.labels.forEach(function(l) { html += '<th>'+l+'</th>'; });
                    html += '</tr></thead><tbody>';
                    d.dados.slice(0,30).forEach(function(row) {
                        html += '<tr>';
                        d.chaves.forEach(function(k) { html += '<td>'+(row[k]||'')+'</td>'; });
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                    if (d.total > 30) html += '<p style="padding:5px 10px;font-size:11px;color:#999">Mostrando 30 de '+d.total+' registros no preview.</p>';
                    html += '</div>';
                    area.innerHTML = html;
                },
                error: function() { area.innerHTML = '<div class="alert alert-danger" style="margin:10px">Erro de rede.</div>'; }
            });
        }

        // ─── Salvar ───
        function pbiSalvar() {
            var titulo = document.getElementById('pbi-titulo').value.trim();
            if (!titulo) { alert('Informe o nome do relatório.'); return; }

            var config = pbiGetConfig();
            var payload = {
                module: 'PainelBI',
                view: 'SaveRelatorio',
                action_type: 'save',
                id: <?= $rel ? ($rel['id'] ?? 0) : 0 ?>,
                titulo: titulo,
                modulo_base: document.getElementById('pbi-modulo').value,
                tipo: document.getElementById('pbi-tipo').value,
                compartilhado: document.getElementById('pbi-compartilhado').checked ? 1 : 0,
                config: JSON.stringify(config)
            };

            jQuery.ajax({
                url: 'index.php',
                type: 'POST',
                data: payload,
                dataType: 'json',
                success: function(r) {
                    if (r.success) {
                        window.location.href = '?module=PainelBI&view=Relatorio&record=' + r.id;
                    } else {
                        alert('Erro ao salvar: ' + (r.error||'desconhecido'));
                    }
                },
                error: function() { alert('Erro de rede ao salvar.'); }
            });
        }
        </script>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }

    // Renderiza uma row de condição com valores já preenchidos
    private function _renderCondRow(array $cond, array $fields, array $operators, array $periods, array $statuses, array $sources, array $users): void {
        $campo  = $cond['campo'] ?? 'leadstatus';
        $op     = $cond['op'] ?? 'eq';
        $valor  = $cond['valor'] ?? '';
        $fType  = $fields[$campo]['type'] ?? 'text';
        $fieldOps = $operators[$fType] ?? $operators['text'];
        ?>
        <select class="form-control input-sm pbi-cond-campo" onchange="pbiUpdateOp(this)">
            <?php foreach ($fields as $k => $f): ?>
                <option value="<?= $k ?>" <?= $campo === $k ? 'selected' : '' ?>><?= htmlspecialchars($f['label']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-control input-sm pbi-cond-op" onchange="pbiUpdateVal(this)">
            <?php foreach ($fieldOps as $ov => $ol): ?>
                <option value="<?= $ov ?>" <?= $op === $ov ? 'selected' : '' ?>><?= htmlspecialchars($ol) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="pbi-cond-val-wrap">
            <?php
            if ($op === 'is_empty' || $op === 'is_not_empty') {
                // sem input
            } elseif ($op === 'in_period') {
                echo '<select class="form-control input-sm pbi-cond-val">';
                foreach ($periods as $pv => $pl) {
                    $sel = $valor === $pv ? 'selected' : '';
                    echo "<option value=\"{$pv}\" {$sel}>" . htmlspecialchars($pl) . '</option>';
                }
                echo '</select>';
            } elseif ($op === 'between') {
                $v1 = is_array($valor) ? ($valor[0] ?? '') : $valor;
                $v2 = is_array($valor) ? ($valor[1] ?? '') : $valor;
                $inpType = in_array($fType, ['datetime','date']) ? 'date' : 'number';
                echo "<input type=\"{$inpType}\" class=\"form-control input-sm pbi-cond-val1\" value=\"" . htmlspecialchars($v1) . "\" placeholder=\"De\">";
                echo '<span style="margin:0 4px">—</span>';
                echo "<input type=\"{$inpType}\" class=\"form-control input-sm pbi-cond-val2\" value=\"" . htmlspecialchars($v2) . "\" placeholder=\"Até\">";
            } elseif (in_array($op, ['in_list','not_in_list']) && $campo === 'leadstatus') {
                $vals = is_array($valor) ? $valor : [$valor];
                echo '<select class="form-control input-sm pbi-cond-val" multiple>';
                foreach ($statuses as $s) {
                    $sel = in_array($s, $vals) ? 'selected' : '';
                    echo "<option value=\"{$s}\" {$sel}>" . htmlspecialchars($s) . '</option>';
                }
                echo '</select>';
            } elseif (in_array($op, ['in_list','not_in_list']) && $campo === 'leadsource') {
                $vals = is_array($valor) ? $valor : [$valor];
                echo '<select class="form-control input-sm pbi-cond-val" multiple>';
                foreach ($sources as $s) {
                    $sel = in_array($s, $vals) ? 'selected' : '';
                    echo "<option value=\"{$s}\" {$sel}>" . htmlspecialchars($s) . '</option>';
                }
                echo '</select>';
            } elseif ($campo === 'leadstatus') {
                echo '<select class="form-control input-sm pbi-cond-val">';
                foreach ($statuses as $s) {
                    $sel = $valor === $s ? 'selected' : '';
                    echo "<option value=\"{$s}\" {$sel}>" . htmlspecialchars($s) . '</option>';
                }
                echo '</select>';
            } elseif ($campo === 'leadsource') {
                echo '<select class="form-control input-sm pbi-cond-val">';
                foreach ($sources as $s) {
                    $sel = $valor === $s ? 'selected' : '';
                    echo "<option value=\"{$s}\" {$sel}>" . htmlspecialchars($s) . '</option>';
                }
                echo '</select>';
            } else {
                $inpType = in_array($fType, ['datetime','date']) ? 'date' : (in_array($fType, ['integer']) ? 'number' : 'text');
                echo "<input type=\"{$inpType}\" class=\"form-control input-sm pbi-cond-val\" value=\"" . htmlspecialchars(is_array($valor) ? implode(',', $valor) : $valor) . "\" placeholder=\"Valor...\">";
            }
            ?>
        </div>
        <button type="button" class="btn btn-xs btn-danger" onclick="this.closest('.pbi-cond-row-build').remove()">×</button>
        <?php
    }

    private function _css(): string { return '<style>
        .pbi-con-container    { padding:0; }
        /* Header */
        .pbi-con-header       { display:flex; align-items:center; justify-content:space-between; padding:10px 15px; background:#fff; border-bottom:1px solid #e0e0e0; gap:10px; }
        .pbi-titulo-input     { max-width:400px; font-weight:600; font-size:15px; }
        .pbi-share-chk        { display:flex; align-items:center; gap:5px; font-weight:normal; margin:0; cursor:pointer; }
        .pbi-con-meta         { padding:8px 15px; background:#f8f9fa; border-bottom:1px solid #e8e8e8; }
        /* Sections */
        .pbi-section          { border:1px solid #e0e0e0; margin:10px 15px; border-radius:6px; background:#fff; overflow:hidden; }
        .pbi-section-header   { display:flex; align-items:center; gap:8px; padding:11px 15px; cursor:pointer; background:#fafafa; border-bottom:1px solid #e8e8e8; font-size:13px; }
        .pbi-section-header:hover { background:#f0f4f8; }
        .pbi-section-body     { padding:15px; }
        .pbi-section-meta     { color:#7f8c8d; font-size:12px; font-weight:normal; }
        .pbi-chevron          { width:14px; transition:transform .2s; }
        /* Aggregations */
        .pbi-agg-list         { display:flex; flex-direction:column; gap:5px; }
        .pbi-agg-row          { display:flex; gap:6px; align-items:center; }
        .pbi-agg-row select   { flex:1; }
        /* Conditions */
        .pbi-cond-grupo       { border:1px solid #e8e8e8; border-radius:5px; margin-bottom:10px; }
        .pbi-cond-grupo-header{ background:#f0f4f8; padding:8px 12px; font-size:12px; display:flex; align-items:center; gap:8px; }
        .pbi-btn-del-group    { margin-left:auto; }
        .pbi-cond-rows        { padding:8px 12px; display:flex; flex-direction:column; gap:6px; }
        .pbi-cond-row-build   { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .pbi-cond-val-wrap    { display:flex; align-items:center; gap:4px; flex:1; min-width:150px; }
        .pbi-cond-and-lbl     { background:#e8f4f8; color:#2980b9; padding:3px 8px; border-radius:3px; font-size:11px; font-weight:700; white-space:nowrap; }
        .pbi-add-cond-btn     { margin:6px 12px 10px; }
        /* Columns */
        .pbi-col-chk          { display:flex; align-items:center; gap:6px; font-weight:normal; cursor:pointer; }
        /* Advanced options */
        .pbi-adv-opts         { background:#f8f9fa; padding:12px; border-radius:4px; margin-top:8px; border:1px solid #e8e8e8; }
        .pbi-adv-link         { font-size:12px; color:#3498db; cursor:pointer; text-decoration:underline; display:block; margin-top:6px; }
        /* Preview */
        .pbi-preview-area     { padding:0; min-height:80px; }
    </style>'; }
}
