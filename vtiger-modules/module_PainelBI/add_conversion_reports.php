<?php
/**
 * PainelBI — Adiciona relatórios de Taxa de Conversão
 *
 * Comando:
 *   docker exec crmbot74-vtiger-1 php /var/www/html/modules/PainelBI/add_conversion_reports.php
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

function insertRel(mysqli $c, string $titulo, string $tipo, string $modulo, array $config, string $now): int {
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

// Verificar se já existem relatórios de conversão
$check = $mysqli->query("SELECT COUNT(*) AS c FROM vtiger_painelbi_relatorios WHERE tipo='conversion' AND deleted=0");
$row = $check->fetch_assoc();
if ((int)$row['c'] > 0) {
    echo "[INFO] Já existem " . $row['c'] . " relatórios de conversão. Pulando.\n";
    $mysqli->close();
    exit(0);
}

echo "=== Adicionando relatórios de conversão ===\n\n";

// 1. Taxa de Conversão por Origem
$c1 = insertRel($mysqli, 'Taxa de Conversão por Origem', 'conversion', 'Leads', [
    'modulo_base' => 'Leads',
    'tipo'        => 'conversion',
    'grupo'       => 'leadsource',
    'condicoes_grupos' => [],
    'ordem_dir' => 'DESC', 'limite' => 100,
    'chart' => ['tipo'=>'bar','campo_label'=>'grupo','campos_dados'=>['total','convertidos'],
                'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>true,'posicao_legenda'=>'top'],
], $now);
echo "  [1] Taxa de Conversão por Origem — id=$c1\n";

// 2. Taxa de Conversão por Atendente
$c2 = insertRel($mysqli, 'Taxa de Conversão por Atendente', 'conversion', 'Leads', [
    'modulo_base' => 'Leads',
    'tipo'        => 'conversion',
    'grupo'       => 'user_name',
    'condicoes_grupos' => [],
    'ordem_dir' => 'DESC', 'limite' => 50,
    'chart' => ['tipo'=>'bar_h','campo_label'=>'grupo','campos_dados'=>['total','convertidos'],
                'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>true,'posicao_legenda'=>'top'],
], $now);
echo "  [2] Taxa de Conversão por Atendente — id=$c2\n";

// 3. Taxa de Conversão por Status
$c3 = insertRel($mysqli, 'Taxa de Conversão por Status', 'conversion', 'Leads', [
    'modulo_base' => 'Leads',
    'tipo'        => 'conversion',
    'grupo'       => 'leadstatus',
    'condicoes_grupos' => [],
    'ordem_dir' => 'DESC', 'limite' => 50,
    'chart' => ['tipo'=>'bar','campo_label'=>'grupo','campos_dados'=>['total','convertidos'],
                'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>true,'posicao_legenda'=>'top'],
], $now);
echo "  [3] Taxa de Conversão por Status — id=$c3\n";

// 4. Taxa de Conversão por Mês
$c4 = insertRel($mysqli, 'Taxa de Conversão por Mês', 'conversion', 'Leads', [
    'modulo_base' => 'Leads',
    'tipo'        => 'conversion',
    'grupo'       => 'mes_criacao',
    'condicoes_grupos' => [],
    'ordem_dir' => 'ASC', 'limite' => 100,
    'chart' => ['tipo'=>'line','campo_label'=>'grupo','campos_dados'=>['total','convertidos'],
                'mostrar_grid'=>true,'mostrar_label'=>false,'mostrar_legenda'=>true,'posicao_legenda'=>'top'],
], $now);
echo "  [4] Taxa de Conversão por Mês — id=$c4\n";

// Adicionar tab "Conversão" ao board principal
$board = $mysqli->query("SELECT id FROM vtiger_painelbi_boards WHERE deleted=0 ORDER BY id ASC LIMIT 1")->fetch_assoc();
if ($board) {
    $boardId = (int)$board['id'];
    $maxSeq = $mysqli->query("SELECT COALESCE(MAX(sequencia),0) AS s FROM vtiger_painelbi_tabs WHERE board_id=$boardId AND deleted=0")->fetch_assoc()['s'];
    $newSeq = (int)$maxSeq + 1;

    $mysqli->query("INSERT INTO vtiger_painelbi_tabs (board_id,titulo,sequencia,deleted) VALUES ($boardId,'Conversão',$newSeq,0)");
    $tabConv = $mysqli->insert_id;

    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabConv,$c1,6,0,0)");
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabConv,$c2,6,1,0)");
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabConv,$c3,6,2,0)");
    $mysqli->query("INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ($tabConv,$c4,6,3,0)");

    echo "\n  Tab 'Conversão' criada no board '$boardId' (tab_id=$tabConv) com 4 widgets\n";
} else {
    echo "\n  [AVISO] Nenhum board encontrado\n";
}

$mysqli->close();
echo "\n✅ 4 relatórios de conversão criados (ids: $c1,$c2,$c3,$c4)\n";
