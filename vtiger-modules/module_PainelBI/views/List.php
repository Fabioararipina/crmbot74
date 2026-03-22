<?php
/**
 * PainelBI_List_View
 * Lista de todos os relatórios salvos (pré-definidos + customizados)
 */
class PainelBI_List_View extends Vtiger_Index_View {

    public function requiresPermission(\Vtiger_Request $request): array { return []; }
    public function checkPermission($request): bool { return true; }

    public function preProcess(Vtiger_Request $request, $display = true): void {
        parent::preProcess($request, $display);
    }

    public function process(Vtiger_Request $request): void {
        require_once 'modules/PainelBI/models/DataProvider.php';
        $dp = new PainelBI_DataProvider_Model();
        $relatorios = $dp->getRelatorios();

        $currentUser = Users_Record_Model::getCurrentUserModel();
        $userId = $currentUser->getId();
        ?>
        <style>
            .pbi-list-container { padding:15px; }
            .pbi-list-header    { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
            .pbi-rel-card       { border:1px solid #e0e0e0; border-radius:6px; margin-bottom:10px; background:#fff; transition:box-shadow .2s; }
            .pbi-rel-card:hover { box-shadow:0 2px 8px rgba(0,0,0,0.1); }
            .pbi-rel-card-body  { display:flex; align-items:center; padding:12px 15px; gap:12px; }
            .pbi-rel-icon       { width:40px; height:40px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:18px; color:#fff; flex-shrink:0; }
            .pbi-rel-icon.summary  { background:#3498db; }
            .pbi-rel-icon.detail   { background:#27ae60; }
            .pbi-rel-info       { flex:1; }
            .pbi-rel-title      { font-weight:600; font-size:14px; color:#2c3e50; }
            .pbi-rel-meta       { font-size:11px; color:#95a5a6; margin-top:2px; }
            .pbi-rel-actions    { display:flex; gap:6px; }
            .pbi-search         { margin-bottom:15px; }
            .pbi-empty          { text-align:center; padding:40px; color:#aaa; }
        </style>

        <div class="pbi-list-container">
            <div class="pbi-list-header">
                <h4 style="margin:0"><i class="fa fa-bar-chart text-primary"></i> Relatórios</h4>
                <div>
                    <a href="?module=PainelBI&view=Dashboard" class="btn btn-sm btn-default">
                        <i class="fa fa-th-large"></i> Dashboard
                    </a>
                    <a href="?module=PainelBI&view=Construtor" class="btn btn-sm btn-primary">
                        <i class="fa fa-plus"></i> Novo Relatório
                    </a>
                </div>
            </div>

            <div class="pbi-search">
                <input type="text" class="form-control" id="pbi-list-search" placeholder="Buscar relatório...">
            </div>

            <?php if (empty($relatorios)): ?>
                <div class="pbi-empty">
                    <i class="fa fa-bar-chart fa-3x" style="margin-bottom:12px; display:block"></i>
                    Nenhum relatório encontrado.
                    <br><br>
                    <a href="?module=PainelBI&view=Construtor" class="btn btn-primary">Criar primeiro relatório</a>
                </div>
            <?php else: ?>
                <div id="pbi-rel-list">
                    <?php foreach ($relatorios as $rel):
                        $config = json_decode(html_entity_decode($rel['config'] ?? '{}', ENT_QUOTES, 'UTF-8'), true) ?? [];
                        $chart  = $config['chart'] ?? [];
                        $chartTipo = $chart['tipo'] ?? 'none';
                        $tipo      = $rel['tipo'] ?? 'summary';
                        $iconClass = $tipo === 'summary' ? 'summary' : 'detail';
                        $iconName  = $tipo === 'summary' ? 'bar-chart' : 'list';
                        $isOwner   = (int)$rel['smownerid'] === (int)$userId;
                    ?>
                    <div class="pbi-rel-card" data-titulo="<?= pbi_e(strtolower($rel['titulo'])) ?>">
                        <div class="pbi-rel-card-body">
                            <div class="pbi-rel-icon <?= $iconClass ?>">
                                <i class="fa fa-<?= $iconName ?>"></i>
                            </div>
                            <div class="pbi-rel-info">
                                <div class="pbi-rel-title">
                                    <a href="?module=PainelBI&view=Relatorio&record=<?= $rel['id'] ?>">
                                        <?= pbi_e($rel['titulo']) ?>
                                    </a>
                                    <?php if ($rel['compartilhado']): ?>
                                        <span class="label label-info" style="font-size:9px">Compartilhado</span>
                                    <?php endif; ?>
                                </div>
                                <div class="pbi-rel-meta">
                                    <i class="fa fa-database"></i> <?= pbi_e($rel['modulo_base']) ?>
                                    &nbsp;|&nbsp;
                                    <i class="fa fa-<?= $tipo === 'summary' ? 'bar-chart' : 'th-list' ?>"></i>
                                    <?= $tipo === 'summary' ? 'Resumo' : 'Detalhado' ?>
                                    <?php if ($chartTipo !== 'none'): ?>
                                        &nbsp;|&nbsp; <i class="fa fa-line-chart"></i> Gráfico
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="pbi-rel-actions">
                                <a href="?module=PainelBI&view=Relatorio&record=<?= $rel['id'] ?>" class="btn btn-xs btn-default" title="Executar">
                                    <i class="fa fa-play"></i> Executar
                                </a>
                                <a href="?module=PainelBI&view=Construtor&record=<?= $rel['id'] ?>" class="btn btn-xs btn-default" title="Personalizar">
                                    <i class="fa fa-pencil"></i>
                                </a>
                                <?php if ($isOwner): ?>
                                <a href="javascript:void(0)" onclick="pbiListDelete(<?= $rel['id'] ?>)" class="btn btn-xs btn-danger" title="Excluir">
                                    <i class="fa fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
        // Busca ao vivo
        document.getElementById('pbi-list-search') && document.getElementById('pbi-list-search').addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('.pbi-rel-card').forEach(function(el) {
                el.style.display = el.getAttribute('data-titulo').includes(q) ? '' : 'none';
            });
        });

        function pbiListDelete(id) {
            if (!confirm('Excluir este relatório? Esta ação não pode ser desfeita.')) return;
            jQuery.post('index.php', {module:'PainelBI', view:'SaveRelatorio', action_type:'delete', id:id}, function(r) {
                if (r.success) { location.reload(); }
                else { alert('Erro: ' + (r.error||'desconhecido')); }
            }, 'json');
        }
        </script>
        <?php
    }

    public function postProcess(Vtiger_Request $request): void {
        parent::postProcess($request);
    }
}
