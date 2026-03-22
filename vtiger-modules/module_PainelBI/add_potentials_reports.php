<?php
/**
 * PainelBI — Adiciona relatórios pré-definidos para Potentials (Oportunidades)
 *
 * Comando:
 *   docker exec crmbot74-vtiger-1 php /var/www/html/modules/PainelBI/add_potentials_reports.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'vtiger-db';
$user = 'vtiger';
$pass = getenv('VTIGER_DB_PASS') ?: (getenv('MYSQL_PASSWORD') ?: 'COLOQUE_NO_ENV');
$db   = 'vtiger';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) die("ERRO DE CONEXÃO: " . $mysqli->connect_error . "\n");
$mysqli->set_charset('utf8mb4');
echo "Conexão: OK\n\n";

$now = date('Y-m-d H:i:s');

function insertRelatorio(mysqli $c, string $titulo, string $tipo, string $modulo, array $config, string $now): int {
    $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
    $stmt = $c->prepare(
        "INSERT INTO vtiger_painelbi_relatorios (titulo,descricao,modulo_base,tipo,config,compartilhado,smownerid,createdtime,modifiedtime,deleted)
         VALUES (?,?,?,?,?,1,1,?,?,0)"
    );
    $desc = '';
    $stmt->bind_param('sssssss', $titulo, $desc, $modulo, $tipo, $configJson, $now, $now);
    if (!$stmt->execute()) die("ERRO: " . $stmt->error . "\n");
    $id = $c->insert_id;
    $stmt->close();
    return $id;
}

// Verificar se já existem relatórios de Potentials
$check = $mysqli->query("SELECT COUNT(*) AS c FROM vtiger_painelbi_relatorios WHERE modulo_base='Potentials' AND deleted=0");
$row = $check->fetch_assoc();
if ((int)$row['c'] > 0) {
    echo "[INFO] Já existem " . $row['c'] . " relatórios de Potentials. Pulando criação.\n";
    echo "Para recriar, delete os existentes primeiro.\n";
    $mysqli->close();
    exit(0);
}

echo "=== Adicionando relatórios de Potentials ===\n\n";

// 1. Pipeline por Etapa de Venda
$p1 = insertRelatorio($mysqli, 'Pipeline por Etapa', 'summary', 'Potentials', [
    'modulo_base' => 'Potentials',
    'tipo'        => 'summary',
    'grupo'       => 'sales_stage',
    'agregacoes'  => [
        ['func'=>'COUNT','campo'=>'*','alias'=>'total'],
        ['func'=>'SUM','campo'=>'amount','alias'=>'valor_total'],
    ],
    'colunas'     => ['sales_stage','total','valor_total'],
    'condicoes_grupos' => [],
    'ordem' => 'total', 'ordem_dir' => 'DESC', 'limite' => 100,
    'chart' => ['tipo'=>'bar_h','campo_label'=>'sales_stage','campos_dados'=>['total'],
                'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>false,'posicao_legenda'=>'top'],
], $now);
echo "  [1] Pipeline por Etapa — id=$p1\n";

// 2. Oportunidades por Tipo
$p2 = insertRelatorio($mysqli, 'Oportunidades por Tipo', 'summary', 'Potentials', [
    'modulo_base' => 'Potentials',
    'tipo'        => 'summary',
    'grupo'       => 'potentialtype',
    'agregacoes'  => [
        ['func'=>'COUNT','campo'=>'*','alias'=>'total'],
        ['func'=>'SUM','campo'=>'amount','alias'=>'valor_total'],
    ],
    'colunas'     => ['potentialtype','total','valor_total'],
    'condicoes_grupos' => [],
    'ordem' => 'total', 'ordem_dir' => 'DESC', 'limite' => 50,
    'chart' => ['tipo'=>'doughnut','campo_label'=>'potentialtype','campos_dados'=>['total'],
                'mostrar_grid'=>false,'mostrar_label'=>true,'mostrar_legenda'=>true,'posicao_legenda'=>'right'],
], $now);
echo "  [2] Oportunidades por Tipo — id=$p2\n";

// 3. Valor por Atendente
$p3 = insertRelatorio($mysqli, 'Valor por Atendente', 'summary', 'Potentials', [
    'modulo_base' => 'Potentials',
    'tipo'        => 'summary',
    'grupo'       => 'user_name',
    'agregacoes'  => [
        ['func'=>'COUNT','campo'=>'*','alias'=>'total'],
        ['func'=>'SUM','campo'=>'amount','alias'=>'valor_total'],
    ],
    'colunas'     => ['user_name','total','valor_total'],
    'condicoes_grupos' => [],
    'ordem' => 'valor_total', 'ordem_dir' => 'DESC', 'limite' => 50,
    'chart' => ['tipo'=>'bar','campo_label'=>'user_name','campos_dados'=>['valor_total'],
                'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>false,'posicao_legenda'=>'top'],
], $now);
echo "  [3] Valor por Atendente — id=$p3\n";

// 4. Evolução Mensal de Fechamentos
$p4 = insertRelatorio($mysqli, 'Fechamentos por Mês', 'summary', 'Potentials', [
    'modulo_base' => 'Potentials',
    'tipo'        => 'summary',
    'grupo'       => 'mes_fechamento',
    'agregacoes'  => [
        ['func'=>'COUNT','campo'=>'*','alias'=>'total'],
        ['func'=>'SUM','campo'=>'amount','alias'=>'valor_total'],
    ],
    'colunas'     => ['mes_fechamento','total','valor_total'],
    'condicoes_grupos' => [],
    'ordem' => 'mes_fechamento', 'ordem_dir' => 'ASC', 'limite' => 100,
    'chart' => ['tipo'=>'line','campo_label'=>'mes_fechamento','campos_dados'=>['valor_total'],
                'mostrar_grid'=>true,'mostrar_label'=>false,'mostrar_legenda'=>true,'posicao_legenda'=>'top'],
], $now);
echo "  [4] Fechamentos por Mês — id=$p4\n";

// 5. Lista de Oportunidades (detalhado)
$p5 = insertRelatorio($mysqli, 'Lista de Oportunidades', 'detail', 'Potentials', [
    'modulo_base' => 'Potentials',
    'tipo'        => 'detail',
    'grupo'       => null,
    'agregacoes'  => [],
    'colunas'     => ['potentialname','sales_stage','potentialtype','amount','account_name','atendente','closingdate'],
    'condicoes_grupos' => [],
    'ordem' => 'closingdate', 'ordem_dir' => 'DESC', 'limite' => 200,
    'chart' => ['tipo'=>'none'],
], $now);
echo "  [5] Lista de Oportunidades — id=$p5\n";

// Adicionar tab "Oportunidades" ao board principal (se existir)
$board = $mysqli->query("SELECT id FROM vtiger_painelbi_boards WHERE deleted=0 ORDER BY id ASC LIMIT 1")->fetch_assoc();
if ($board) {
    $boardId = (int)$board['id'];
    $maxSeq = $mysqli->query("SELECT COALESCE(MAX(sequencia),0) AS s FROM vtiger_painelbi_tabs WHERE board_id=$boardId AND deleted=0")->fetch_assoc()['s'];
    $newSeq = (int)$maxSeq + 1;

    $mysqli->query("INSERT INTO vtiger_painelbi_tabs (board_id,titulo,sequencia,deleted) VALUES ($boardId,'Oportunidades',$newSeq,0)");
    $tabOpp = $mysqli->insert_id;

    // Widgets: Pipeline (col6) + Por Tipo (col6) + Lista (col12)
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabOpp,$p1,6,0,0)");
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabOpp,$p2,6,1,0)");
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabOpp,$p3,6,2,0)");
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabOpp,$p4,6,3,0)");
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabOpp,$p5,12,4,0)");

    echo "\n  Tab 'Oportunidades' criada no board '$boardId' (tab_id=$tabOpp) com 5 widgets\n";
} else {
    echo "\n  [AVISO] Nenhum board encontrado — relatórios criados mas sem tab/widgets\n";
}

$mysqli->close();

echo "\n✅ 5 relatórios de Potentials criados (ids: $p1,$p2,$p3,$p4,$p5)\n";
echo "\nLimpe o cache:\n";
echo "  docker exec crmbot74-vtiger-1 bash -c \"rm -rf /var/www/html/cache/templates_c/* /var/www/html/cache/modules/*\"\n";
