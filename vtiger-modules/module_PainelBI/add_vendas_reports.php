<?php
/**
 * add_vendas_reports.php
 * Cria aba "Vendas" com relatórios de leads vs vendas no board padrão.
 * Executar: docker exec crmbot74-vtiger-1 php /var/www/html/modules/PainelBI/add_vendas_reports.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli('vtiger-db', 'vtiger', 'FyyU8aWNoMCHI908izpnmTHfIBU', 'vtiger');
if ($mysqli->connect_error) die("ERRO: " . $mysqli->connect_error . "\n");
$mysqli->set_charset('utf8mb4');
echo "Conexão: OK\n\n";

$now = date('Y-m-d H:i:s');

function insertRel(mysqli $c, string $titulo, string $descricao, array $config, string $now): int {
    $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
    $stmt = $c->prepare(
        "INSERT INTO vtiger_painelbi_relatorios (titulo,descricao,modulo_base,tipo,config,compartilhado,smownerid,createdtime,modifiedtime,deleted)
         VALUES (?,?,'Leads','vendas',?,1,1,?,?,0)"
    );
    $stmt->bind_param('sssss', $titulo, $descricao, $configJson, $now, $now);
    $stmt->execute();
    return (int)$c->insert_id;
}

// Encontrar board compartilhado
$r = $mysqli->query("SELECT id FROM vtiger_painelbi_boards WHERE deleted=0 AND compartilhado=1 ORDER BY id ASC LIMIT 1");
$boardRow = $r->fetch_assoc();
if (!$boardRow) die("Nenhum board compartilhado encontrado.\n");
$boardId = (int)$boardRow['id'];
echo "Board ID: $boardId\n";

// Verificar se aba já existe
$r2 = $mysqli->query("SELECT id FROM vtiger_painelbi_tabs WHERE board_id=$boardId AND titulo='Vendas' AND deleted=0");
if ($r2->fetch_assoc()) die("Aba 'Vendas' já existe. Nada a fazer.\n");

// Sequência da nova aba
$r3 = $mysqli->query("SELECT COUNT(*) AS c FROM vtiger_painelbi_tabs WHERE board_id=$boardId AND deleted=0");
$seqTab = (int)$r3->fetch_assoc()['c'];

$stmt = $mysqli->prepare("INSERT INTO vtiger_painelbi_tabs (board_id,titulo,sequencia,deleted) VALUES (?,?,?,0)");
$stmt->bind_param('isi', $boardId, $titulo_tab, $seqTab);
$titulo_tab = 'Vendas';
$stmt->execute();
$tabId = (int)$mysqli->insert_id;
echo "Aba 'Vendas' criada (id=$tabId)\n\n";

// ── Relatório 1: Por Atendente ─────────────────────────────────────────────
$relId = insertRel($mysqli,
    'Leads vs Vendas por Atendente',
    'Total de leads recebidos, convertidos e vendas fechadas por atendente',
    [
        'modulo_base' => 'Leads', 'tipo' => 'vendas',
        'grupo' => 'user_name', 'estagio_vencido' => 'Vencido',
        'ordem_dir' => 'DESC', 'limite' => 100,
        'chart' => [
            'tipo' => 'bar_h', 'campo_label' => 'grupo',
            'campos_dados' => ['total_leads', 'convertidos', 'vendas'],
            'mostrar_grid' => true, 'mostrar_label' => true,
            'mostrar_legenda' => true, 'posicao_legenda' => 'top',
        ],
    ], $now
);
$mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabId,$relId,12,0,0)");
echo "Relatório 'Leads vs Vendas por Atendente' (id=$relId) ✓\n";

// ── Relatório 2: Por Origem ────────────────────────────────────────────────
$relId = insertRel($mysqli,
    'Vendas por Origem',
    'Quantidade de vendas por canal de origem do lead',
    [
        'modulo_base' => 'Leads', 'tipo' => 'vendas',
        'grupo' => 'leadsource', 'estagio_vencido' => 'Vencido',
        'ordem_dir' => 'DESC', 'limite' => 50,
        'chart' => [
            'tipo' => 'doughnut', 'campo_label' => 'grupo',
            'campos_dados' => ['vendas'],
            'mostrar_grid' => false, 'mostrar_label' => true,
            'mostrar_legenda' => true, 'posicao_legenda' => 'right',
        ],
    ], $now
);
$mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabId,$relId,6,1,0)");
echo "Relatório 'Vendas por Origem' (id=$relId) ✓\n";

// ── Relatório 3: Por Mês ───────────────────────────────────────────────────
$relId = insertRel($mysqli,
    'Leads vs Vendas por Mês',
    'Evolução mensal de leads recebidos e vendas fechadas',
    [
        'modulo_base' => 'Leads', 'tipo' => 'vendas',
        'grupo' => 'mes_criacao', 'estagio_vencido' => 'Vencido',
        'ordem_dir' => 'ASC', 'limite' => 24,
        'chart' => [
            'tipo' => 'line', 'campo_label' => 'grupo',
            'campos_dados' => ['total_leads', 'vendas'],
            'mostrar_grid' => true, 'mostrar_label' => false,
            'mostrar_legenda' => true, 'posicao_legenda' => 'top',
        ],
    ], $now
);
$mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabId,$relId,6,2,0)");
echo "Relatório 'Leads vs Vendas por Mês' (id=$relId) ✓\n";

echo "\nConcluído! Acesse o Dashboard → aba 'Vendas'.\n";
echo "Estágios encontrados: Agendado(59), Vencido(54), Avaliação Agendada(27)...\n";
