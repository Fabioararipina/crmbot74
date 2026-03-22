<?php
/**
 * add_vendas_reports.php
 * Cria aba "Vendas" com relatórios de leads vs vendas no board padrão.
 * Executar: docker exec crmbot74-vtiger-1 php /var/www/html/modules/PainelBI/add_vendas_reports.php
 */

chdir('/var/www/html');
define('VTIGER_ROOT', '/var/www/html');
require_once 'include/utils/utils.php';
require_once 'modules/PainelBI/models/DataProvider.php';

$db = PearDatabase::getInstance();

// Encontrar board compartilhado (id=1 ou primeiro disponível)
$r = $db->pquery("SELECT id FROM vtiger_painelbi_boards WHERE deleted=0 AND compartilhado=1 ORDER BY id ASC LIMIT 1", []);
$boardRow = $db->fetch_array($r);
if (!$boardRow) {
    die("Nenhum board compartilhado encontrado. Crie um board primeiro.\n");
}
$boardId = (int)$boardRow['id'];
echo "Board ID: $boardId\n";

// Verificar se aba Vendas já existe
$r2 = $db->pquery("SELECT id FROM vtiger_painelbi_tabs WHERE board_id=? AND titulo='Vendas' AND deleted=0", [$boardId]);
if ($db->fetch_array($r2)) {
    die("Aba 'Vendas' já existe. Nada a fazer.\n");
}

// Criar aba Vendas
$r3 = $db->pquery("SELECT COUNT(*) AS c FROM vtiger_painelbi_tabs WHERE board_id=? AND deleted=0", [$boardId]);
$seqTab = (int)$db->query_result($r3, 0, 'c');
$db->pquery("INSERT INTO vtiger_painelbi_tabs (board_id, titulo, sequencia, deleted) VALUES (?,?,?,0)", [$boardId, 'Vendas', $seqTab]);
$tabId = (int)$db->getLastInsertID('vtiger_painelbi_tabs', 'id');
echo "Aba 'Vendas' criada (id=$tabId)\n";

$now = date('Y-m-d H:i:s');
$adminId = 1;

$relatorios = [
    [
        'titulo'   => 'Leads vs Vendas por Atendente',
        'descricao'=> 'Total de leads recebidos, convertidos e vendas fechadas por atendente',
        'tipo'     => 'vendas',
        'largura'  => 12,
        'config'   => [
            'modulo_base'    => 'Leads',
            'tipo'           => 'vendas',
            'grupo'          => 'user_name',
            'estagio_vencido'=> 'Vencido',
            'ordem_dir'      => 'DESC',
            'limite'         => 100,
            'chart'          => [
                'tipo'            => 'bar',
                'campo_label'     => 'grupo',
                'campos_dados'    => ['total_leads', 'convertidos', 'vendas'],
                'mostrar_grid'    => true,
                'mostrar_label'   => true,
                'mostrar_legenda' => true,
                'posicao_legenda' => 'top',
            ],
        ],
    ],
    [
        'titulo'   => 'Leads vs Vendas por Origem',
        'descricao'=> 'Total de leads e vendas agrupados por canal de origem',
        'tipo'     => 'vendas',
        'largura'  => 6,
        'config'   => [
            'modulo_base'    => 'Leads',
            'tipo'           => 'vendas',
            'grupo'          => 'leadsource',
            'estagio_vencido'=> 'Vencido',
            'ordem_dir'      => 'DESC',
            'limite'         => 50,
            'chart'          => [
                'tipo'            => 'doughnut',
                'campo_label'     => 'grupo',
                'campos_dados'    => ['vendas'],
                'mostrar_grid'    => false,
                'mostrar_label'   => true,
                'mostrar_legenda' => true,
                'posicao_legenda' => 'right',
            ],
        ],
    ],
    [
        'titulo'   => 'Leads vs Vendas por Mês',
        'descricao'=> 'Evolução mensal de leads recebidos e vendas fechadas',
        'tipo'     => 'vendas',
        'largura'  => 6,
        'config'   => [
            'modulo_base'    => 'Leads',
            'tipo'           => 'vendas',
            'grupo'          => 'mes_criacao',
            'estagio_vencido'=> 'Vencido',
            'ordem_dir'      => 'ASC',
            'limite'         => 24,
            'chart'          => [
                'tipo'            => 'line',
                'campo_label'     => 'grupo',
                'campos_dados'    => ['total_leads', 'vendas'],
                'mostrar_grid'    => true,
                'mostrar_label'   => false,
                'mostrar_legenda' => true,
                'posicao_legenda' => 'top',
            ],
        ],
    ],
];

foreach ($relatorios as $seq => $rel) {
    $config = json_encode($rel['config'], JSON_UNESCAPED_UNICODE);
    $db->pquery(
        "INSERT INTO vtiger_painelbi_relatorios (titulo, descricao, modulo_base, tipo, config, compartilhado, smownerid, createdtime, modifiedtime, deleted)
         VALUES (?, ?, 'Leads', ?, ?, 1, ?, ?, ?, 0)",
        [$rel['titulo'], $rel['descricao'], $rel['tipo'], $config, $adminId, $now, $now]
    );
    $relId = (int)$db->getLastInsertID('vtiger_painelbi_relatorios', 'id');

    $db->pquery(
        "INSERT INTO vtiger_painelbi_widgets (tab_id, relatorio_id, largura, sequencia, deleted) VALUES (?, ?, ?, ?, 0)",
        [$tabId, $relId, $rel['largura'], $seq]
    );
    echo "Relatório '{$rel['titulo']}' criado (id=$relId, largura={$rel['largura']})\n";
}

echo "\nConcluído! Acesse o Dashboard e veja a aba 'Vendas'.\n";
