<?php
/**
 * MetasVendedores_Edit_View — Formulário de criação/edição de meta
 * Dropdowns de Equipe, Vendedor, Tipo, Estágio são todos dinâmicos (lidos do vTiger em tempo real)
 */
class MetasVendedores_Edit_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/MetasVendedores/models/Record.php';
        require_once 'modules/MetasVendedores/models/Picklist.php';

        $recordId = (int) $request->get('record');
        $isEdit   = $recordId > 0;
        $record   = $isEdit ? MetasVendedores_Record_Model::getById($recordId) : new MetasVendedores_Record_Model();

        // Carregar dados dinâmicos (todos sem hardcode)
        $equipes      = MetasVendedores_Picklist_Model::getEquipes();
        $vendedores   = MetasVendedores_Picklist_Model::getVendedores(); // todos
        $tiposProduto = MetasVendedores_Picklist_Model::getOpportunityTypes();
        $salesStages  = MetasVendedores_Picklist_Model::getSalesStages();
        $leadStatuses = MetasVendedores_Picklist_Model::getLeadStatuses();
        $eqVendMap    = MetasVendedores_Picklist_Model::getEquipeVendedoresMap();

        $secaoAtual = $record ? ($record->get('secao') ?: 'oportunidades') : 'oportunidades';

        $v = fn($k) => $record ? htmlspecialchars((string)($record->get($k) ?? '')) : '';
        $titulo = $isEdit ? 'Editar Meta' : 'Nova Meta';

        echo $this->_css();
        ?>
        <div class="mv-container">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-bullseye"></i> <?= $titulo ?></h3>
                </div>
                <div class="panel-body">
                    <form method="POST" action="index.php" id="mv-form">
                        <input type="hidden" name="module" value="MetasVendedores">
                        <input type="hidden" name="action" value="Save">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="record" value="<?= $recordId ?>">
                        <?php endif; ?>

                        <!-- ─── Informações da Meta ─── -->
                        <div class="panel panel-default">
                            <div class="panel-heading"><b>Informações da Meta</b></div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Título <span class="text-danger">*</span></label>
                                            <input type="text" name="titulo" class="form-control" required
                                                   value="<?= $v('titulo') ?>" placeholder="Ex: Meta Cartão – Laryssa – Março/2026">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Seção <span class="text-danger">*</span></label>
                                            <select name="secao" id="mv-secao" class="form-control" required>
                                                <option value="oportunidades" <?= $secaoAtual === 'oportunidades' ? 'selected' : '' ?>>Metas de Oportunidades (Vendas)</option>
                                                <option value="funil"         <?= $secaoAtual === 'funil'         ? 'selected' : '' ?>>Funil de Conversão de Leads</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Equipe</label>
                                            <select name="equipe_id" id="mv-equipe" class="form-control">
                                                <option value="">Toda a Organização</option>
                                                <?php foreach ($equipes as $eq): ?>
                                                    <option value="<?= $eq['id'] ?>"
                                                        <?= (string)($record ? $record->get('equipe_id') : '') === (string)$eq['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($eq['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Vendedor</label>
                                            <select name="usuario_id" id="mv-vendedor" class="form-control">
                                                <option value="">Equipe Toda</option>
                                                <?php foreach ($vendedores as $vend): ?>
                                                    <option value="<?= $vend['id'] ?>"
                                                        data-equipe="<?= $this->_getEquipesDoUsuario($eqVendMap, $vend['id']) ?>"
                                                        <?= (string)($record ? $record->get('usuario_id') : '') === (string)$vend['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($vend['nome']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Período Início <span class="text-danger">*</span></label>
                                            <input type="date" name="periodo_inicio" class="form-control" required
                                                   value="<?= $v('periodo_inicio') ?: date('Y-m-01') ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Período Fim <span class="text-danger">*</span></label>
                                            <input type="date" name="periodo_fim" class="form-control" required
                                                   value="<?= $v('periodo_fim') ?: date('Y-m-t') ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ─── Seção: Oportunidades ─── -->
                        <div id="mv-secao-opor" class="panel panel-info" <?= $secaoAtual !== 'oportunidades' ? 'style="display:none"' : '' ?>>
                            <div class="panel-heading"><b><i class="fa fa-trophy"></i> Metas de Oportunidades (Vendas)</b></div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Tipo de Produto</label>
                                            <select name="tipo_produto" id="mv-tipo" class="form-control">
                                                <option value="">Todos os Tipos</option>
                                                <?php foreach ($tiposProduto as $tp): ?>
                                                    <option value="<?= htmlspecialchars($tp) ?>"
                                                        <?= $v('tipo_produto') === htmlspecialchars($tp) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($tp) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Lido do picklist "Tipo" do módulo Oportunidades</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Estágio Alvo</label>
                                            <select name="sales_stage_alvo" class="form-control">
                                                <?php foreach ($salesStages as $ss): ?>
                                                    <option value="<?= htmlspecialchars($ss) ?>"
                                                        <?= ($v('sales_stage_alvo') ?: 'Closed Won') === htmlspecialchars($ss) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($ss) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Lido do picklist "Estágio de Venda"</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Meta Valor (R$)</label>
                                            <input type="number" name="meta_valor" class="form-control" step="0.01" min="0"
                                                   value="<?= $v('meta_valor') ?: '0' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Meta Quantidade (unidades)</label>
                                            <input type="number" name="meta_quantidade" class="form-control" step="1" min="0"
                                                   value="<?= $v('meta_quantidade') ?: '0' ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ─── Seção: Funil de Leads ─── -->
                        <div id="mv-secao-funil" class="panel panel-warning" <?= $secaoAtual !== 'funil' ? 'style="display:none"' : '' ?>>
                            <div class="panel-heading"><b><i class="fa fa-filter"></i> Metas de Funil de Conversão de Leads</b></div>
                            <div class="panel-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Estágio Origem (DE)</label>
                                            <select name="estagio_origem" class="form-control">
                                                <option value="">Selecione...</option>
                                                <?php foreach ($leadStatuses as $ls): ?>
                                                    <option value="<?= htmlspecialchars($ls) ?>"
                                                        <?= $v('estagio_origem') === htmlspecialchars($ls) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($ls) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted">Lido do picklist "Status do Lead"</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Estágio Destino (PARA)</label>
                                            <select name="estagio_destino" class="form-control">
                                                <option value="">Selecione...</option>
                                                <?php foreach ($leadStatuses as $ls): ?>
                                                    <option value="<?= htmlspecialchars($ls) ?>"
                                                        <?= $v('estagio_destino') === htmlspecialchars($ls) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($ls) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Meta Taxa de Conversão (%)</label>
                                            <input type="number" name="meta_taxa_conversao" class="form-control" step="0.1" min="0" max="100"
                                                   value="<?= $v('meta_taxa_conversao') ?: '' ?>"
                                                   placeholder="Ex: 50 (para meta de 50%)">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label>Meta Quantidade de Leads (destino)</label>
                                            <input type="number" name="meta_quantidade_funil" class="form-control" step="1" min="0"
                                                   value="<?= $v('meta_quantidade_funil') ?: '0' ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botões -->
                        <div class="row">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Salvar Meta</button>
                                &nbsp;
                                <a href="index.php?module=MetasVendedores&view=List" class="btn btn-default">Cancelar</a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>

        <script>
        // Mapa equipe → vendedores (gerado server-side, sem AJAX)
        var mvEquipeMap = <?= json_encode($eqVendMap, JSON_UNESCAPED_UNICODE) ?>;
        var mvVendedorAtual = <?= $record ? (int)$record->get('usuario_id') : 0 ?>;

        document.getElementById('mv-secao').addEventListener('change', function() {
            var secao = this.value;
            document.getElementById('mv-secao-opor').style.display  = (secao === 'oportunidades') ? '' : 'none';
            document.getElementById('mv-secao-funil').style.display  = (secao === 'funil')         ? '' : 'none';
        });

        document.getElementById('mv-equipe').addEventListener('change', function() {
            var equipeId = this.value;
            var sel = document.getElementById('mv-vendedor');
            sel.innerHTML = '<option value="">Equipe Toda</option>';

            var lista = equipeId ? (mvEquipeMap[equipeId] || []) : [];
            // Se equipe vazia, mostrar todos
            if (!equipeId) {
                var todos = [];
                Object.values(mvEquipeMap).forEach(function(arr) {
                    arr.forEach(function(v) {
                        if (!todos.find(function(x){ return x.id == v.id; })) todos.push(v);
                    });
                });
                lista = todos;
            }
            lista.forEach(function(vend) {
                var opt = document.createElement('option');
                opt.value = vend.id;
                opt.textContent = vend.nome;
                sel.appendChild(opt);
            });
        });
        </script>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }

    private function _getEquipesDoUsuario(array $eqVendMap, int $userId): string {
        $eqs = [];
        foreach ($eqVendMap as $eqId => $vends) {
            foreach ($vends as $v) {
                if ((int)$v['id'] === $userId) { $eqs[] = $eqId; break; }
            }
        }
        return implode(',', $eqs);
    }

    private function _css(): string {
        return '<style>
            .mv-container { padding: 15px; }
            .mv-container .panel-heading { font-size: 14px; }
            .mv-container .panel-info .panel-heading  { background: #d9edf7; color: #31708f; }
            .mv-container .panel-warning .panel-heading { background: #fcf8e3; color: #8a6d3b; }
        </style>';
    }
}
