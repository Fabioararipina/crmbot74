<?php
/**
 * PainelBI — Script de Instalação
 * Registra o módulo no vTiger e cria tabelas + relatórios pré-definidos.
 *
 * Comando:
 *   docker exec crmbot74-vtiger-1 php /var/www/html/modules/PainelBI/install.php
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

function xq(mysqli $c, string $sql, array $p = []): mysqli_result|bool {
    if (empty($p)) {
        $r = $c->query($sql);
        if ($r === false) die("ERRO SQL: " . $c->error . "\nSQL: $sql\n");
        return $r;
    }
    $stmt = $c->prepare($sql);
    if (!$stmt) die("ERRO PREPARE: " . $c->error . "\nSQL: $sql\n");
    $stmt->bind_param(str_repeat('s', count($p)), ...$p);
    if (!$stmt->execute()) die("ERRO EXECUTE: " . $stmt->error);
    $r = $stmt->get_result();
    $stmt->close();
    return $r !== false ? $r : true;
}
function xget(mysqli $c, string $sql, array $p = []): ?array {
    $r = xq($c, $sql, $p);
    return ($r === true || !$r) ? null : $r->fetch_assoc();
}
function xmax(mysqli $c, string $t, string $col): int {
    return (int)(xget($c, "SELECT COALESCE(MAX(`$col`),0) AS m FROM `$t`")['m'] ?? 0);
}

// Verificar instalação existente
if (xget($mysqli, "SELECT tabid FROM vtiger_tab WHERE name='PainelBI'")) {
    echo "[INFO] Já instalado. Use uninstall.php primeiro.\n";
    exit(0);
}

echo "=== Instalando PainelBI ===\n\n";

// ─── [1/9] vtiger_tab ────────────────────────────────────────────────────────
echo "[1/9] Registrando em vtiger_tab...\n";
$tabId  = xmax($mysqli, 'vtiger_tab', 'tabid') + 1;
$tabSeq = xmax($mysqli, 'vtiger_tab', 'tabsequence') + 10;
xq($mysqli,
    "INSERT INTO vtiger_tab (tabid,name,presence,tabsequence,tablabel,customized,ownedby,isentitytype,trial,version,issyncable,allowduplicates)
     VALUES (?,?,1,?,'Painel BI',1,0,0,0,'1.0',0,1)",
    [(string)$tabId, 'PainelBI', (string)$tabSeq]
);
$check = xget($mysqli, "SELECT tabid FROM vtiger_tab WHERE name='PainelBI'");
if (!$check) die("ERRO: INSERT em vtiger_tab falhou!\n");
echo "    OK — tabid=$tabId\n";

// ─── [2/9] Tabelas do módulo ──────────────────────────────────────────────────
echo "[2/9] Criando tabelas...\n";

// Boards (coleções de dashboards)
xq($mysqli, "DROP TABLE IF EXISTS vtiger_painelbi_boards");
xq($mysqli, "CREATE TABLE vtiger_painelbi_boards (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(255) NOT NULL DEFAULT 'Meu Board',
    compartilhado TINYINT(1) DEFAULT 0,
    smownerid   INT DEFAULT 1,
    createdtime DATETIME DEFAULT NULL,
    deleted     TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Tabs dentro de boards
xq($mysqli, "DROP TABLE IF EXISTS vtiger_painelbi_tabs");
xq($mysqli, "CREATE TABLE vtiger_painelbi_tabs (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    board_id  INT NOT NULL,
    titulo    VARCHAR(255) NOT NULL DEFAULT 'Principal',
    sequencia INT DEFAULT 0,
    deleted   TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Widgets (relatórios adicionados a tabs)
xq($mysqli, "DROP TABLE IF EXISTS vtiger_painelbi_widgets");
xq($mysqli, "CREATE TABLE vtiger_painelbi_widgets (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tab_id       INT NOT NULL,
    relatorio_id INT NOT NULL,
    largura      INT DEFAULT 6,
    sequencia    INT DEFAULT 0,
    deleted      TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Relatórios salvos
xq($mysqli, "DROP TABLE IF EXISTS vtiger_painelbi_relatorios");
xq($mysqli, "CREATE TABLE vtiger_painelbi_relatorios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    titulo        VARCHAR(255) NOT NULL DEFAULT '',
    descricao     TEXT,
    modulo_base   VARCHAR(50) NOT NULL DEFAULT 'Leads',
    tipo          VARCHAR(20) NOT NULL DEFAULT 'summary',
    config        LONGTEXT,
    compartilhado TINYINT(1) DEFAULT 1,
    smownerid     INT DEFAULT 1,
    createdtime   DATETIME DEFAULT NULL,
    modifiedtime  DATETIME DEFAULT NULL,
    deleted       TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "    OK — 4 tabelas criadas\n";

// ─── [3/9] Blocos e campos (mínimos para o framework vTiger) ─────────────────
echo "[3/9] Registrando bloco e campo mínimos...\n";
$blockId = xmax($mysqli, 'vtiger_blocks', 'blockid') + 1;
xq($mysqli,
    "INSERT INTO vtiger_blocks (blockid,tabid,blocklabel,sequence,show_title,visible,create_view,edit_view,detail_view,display_status,iscustom)
     VALUES (?,'$tabId','Informações',1,1,1,1,1,1,1,1)",
    [(string)$blockId]
);
$fieldId = xmax($mysqli, 'vtiger_field', 'fieldid') + 1;
xq($mysqli,
    "INSERT INTO vtiger_field (fieldid,tabid,columnname,tablename,generatedtype,uitype,fieldname,fieldlabel,readonly,presence,defaultvalue,maximumlength,masseditable,sequence,block,displaytype,typeofdata,quickcreate,quickcreatesequence,info_type,helpinfo,summaryfield,headerfield,isunique)
     VALUES (?,'$tabId','titulo','vtiger_painelbi_relatorios',1,2,'titulo','Título',0,0,'',255,0,1,?,'1','V~M',0,0,'BAS','',0,0,0)",
    [(string)$fieldId, (string)$blockId]
);
echo "    OK\n";

// ─── [4/9] Permissões ────────────────────────────────────────────────────────
echo "[4/9] Configurando permissões...\n";
xq($mysqli, "INSERT IGNORE INTO vtiger_def_org_share (tabid,permission,editstatus) VALUES ('$tabId',3,0)");
$profiles = xq($mysqli, "SELECT profileid FROM vtiger_profile");
while ($pr = $profiles->fetch_assoc()) {
    $pid = $pr['profileid'];
    xq($mysqli, "INSERT IGNORE INTO vtiger_profile2tab (profileid,tabid,permissions) VALUES ('$pid','$tabId',0)");
    xq($mysqli, "INSERT IGNORE INTO vtiger_profile2field (profileid,tabid,fieldid,visible,readonly) VALUES ('$pid','$tabId','$fieldId',1,0)");
}
echo "    OK\n";

// ─── [5/9] Menu ──────────────────────────────────────────────────────────────
echo "[5/9] Adicionando ao menu (SALES)...\n";
$linkId = xmax($mysqli, 'vtiger_links', 'linkid') + 1;
xq($mysqli,
    "INSERT INTO vtiger_links (linkid,tabid,linktype,linklabel,linkurl,linkicon,sequence,handler_path,handler_class,handler,parent_link)
     VALUES (?,0,'HEADERLINK','Painel BI','index.php?module=PainelBI&view=Dashboard','',99,'','','',0)",
    [(string)$linkId]
);
xq($mysqli, "UPDATE vtiger_tab SET presence=0, parent='Reports' WHERE tabid='$tabId'");
$appSeq = xmax($mysqli, 'vtiger_app2tab', 'sequence') + 1;
xq($mysqli, "DELETE FROM vtiger_app2tab WHERE tabid='$tabId'");
xq($mysqli, "INSERT INTO vtiger_app2tab (tabid,appname,sequence,visible) VALUES ('$tabId','TOOLS','$appSeq',1)");
// vtiger_parenttabrel: ignorado — vtiger_app2tab é suficiente no vTiger 8.4
xq($mysqli, "DELETE FROM vtiger_parenttabrel WHERE tabid='$tabId'");
echo "    OK\n";

// ─── [6/9] Relatórios pré-definidos ──────────────────────────────────────────
echo "[6/9] Criando relatórios pré-definidos...\n";
$now = date('Y-m-d H:i:s');

function insertRelatorio(mysqli $c, string $titulo, string $tipo, string $modulo, array $config, string $now): int {
    $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
    xq($c,
        "INSERT INTO vtiger_painelbi_relatorios (titulo,descricao,modulo_base,tipo,config,compartilhado,smownerid,createdtime,modifiedtime,deleted)
         VALUES (?,?,?,?,?,1,1,?,?,0)",
        [$titulo, '', $modulo, $tipo, $configJson, $now, $now]
    );
    return $c->insert_id;
}

// 1. Funil de Leads
$r1 = insertRelatorio($mysqli, 'Funil de Leads', 'summary', 'Leads', [
    'tipo'      => 'summary',
    'grupo'     => 'leadstatus',
    'agregacoes'=> [['func'=>'COUNT','campo'=>'*','alias'=>'total']],
    'colunas'   => ['leadstatus','total'],
    'condicoes_grupos' => [],
    'ordem'     => 'total', 'ordem_dir' => 'DESC', 'limite' => 100,
    'chart'     => ['tipo'=>'bar_h','campo_label'=>'leadstatus','campos_dados'=>['total'],
                    'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>false,'posicao_legenda'=>'top'],
], $now);

// 2. Evolução Temporal (últimos 30 dias)
$r2 = insertRelatorio($mysqli, 'Evolução Temporal (30 dias)', 'summary', 'Leads', [
    'tipo'      => 'summary',
    'grupo'     => 'data_criacao',
    'agregacoes'=> [['func'=>'COUNT','campo'=>'*','alias'=>'total']],
    'colunas'   => ['data_criacao','total'],
    'condicoes_grupos' => [[
        'condicoes' => [['campo'=>'createdtime','op'=>'in_period','valor'=>'last_30']]
    ]],
    'ordem' => 'data_criacao', 'ordem_dir' => 'ASC', 'limite' => 100,
    'chart' => ['tipo'=>'line','campo_label'=>'data_criacao','campos_dados'=>['total'],
                'mostrar_grid'=>true,'mostrar_label'=>false,'mostrar_legenda'=>true,'posicao_legenda'=>'top'],
], $now);

// 3. Performance por Atendente
$r3 = insertRelatorio($mysqli, 'Performance por Atendente', 'summary', 'Leads', [
    'tipo'      => 'summary',
    'grupo'     => 'user_name',
    'agregacoes'=> [['func'=>'COUNT','campo'=>'*','alias'=>'total']],
    'colunas'   => ['user_name','total'],
    'condicoes_grupos' => [],
    'ordem' => 'total', 'ordem_dir' => 'DESC', 'limite' => 50,
    'chart' => ['tipo'=>'bar','campo_label'=>'user_name','campos_dados'=>['total'],
                'mostrar_grid'=>true,'mostrar_label'=>true,'mostrar_legenda'=>false,'posicao_legenda'=>'top'],
], $now);

// 4. Leads por Origem
$r4 = insertRelatorio($mysqli, 'Leads por Origem', 'summary', 'Leads', [
    'tipo'      => 'summary',
    'grupo'     => 'leadsource',
    'agregacoes'=> [['func'=>'COUNT','campo'=>'*','alias'=>'total']],
    'colunas'   => ['leadsource','total'],
    'condicoes_grupos' => [],
    'ordem' => 'total', 'ordem_dir' => 'DESC', 'limite' => 50,
    'chart' => ['tipo'=>'doughnut','campo_label'=>'leadsource','campos_dados'=>['total'],
                'mostrar_grid'=>false,'mostrar_label'=>true,'mostrar_legenda'=>true,'posicao_legenda'=>'right'],
], $now);

// 5. Leads Recentes (detalhado)
$r5 = insertRelatorio($mysqli, 'Leads Recentes', 'detail', 'Leads', [
    'tipo'    => 'detail',
    'grupo'   => null,
    'agregacoes' => [],
    'colunas' => ['nome_completo','phone','leadstatus','leadsource','atendente','createdtime'],
    'condicoes_grupos' => [[
        'condicoes' => [['campo'=>'createdtime','op'=>'in_period','valor'=>'last_30']]
    ]],
    'ordem' => 'createdtime', 'ordem_dir' => 'DESC', 'limite' => 200,
    'chart' => ['tipo'=>'none'],
], $now);

// 6. Taxa de Conversão por Status
$r6 = insertRelatorio($mysqli, 'Leads por Status (Este Mês)', 'summary', 'Leads', [
    'tipo'      => 'summary',
    'grupo'     => 'leadstatus',
    'agregacoes'=> [['func'=>'COUNT','campo'=>'*','alias'=>'total']],
    'colunas'   => ['leadstatus','total'],
    'condicoes_grupos' => [[
        'condicoes' => [['campo'=>'createdtime','op'=>'in_period','valor'=>'this_month']]
    ]],
    'ordem' => 'total', 'ordem_dir' => 'DESC', 'limite' => 50,
    'chart' => ['tipo'=>'pie','campo_label'=>'leadstatus','campos_dados'=>['total'],
                'mostrar_grid'=>false,'mostrar_label'=>true,'mostrar_legenda'=>true,'posicao_legenda'=>'right'],
], $now);

echo "    OK — 6 relatórios criados (ids: $r1,$r2,$r3,$r4,$r5,$r6)\n";

// ─── [7/9] Board + Tabs + Widgets padrão ─────────────────────────────────────
echo "[7/9] Criando board + tabs + widgets padrão...\n";

// Board principal
xq($mysqli, "INSERT INTO vtiger_painelbi_boards (titulo,compartilhado,smownerid,createdtime,deleted) VALUES ('Essencial Saúde',1,1,'$now',0)");
$boardId = $mysqli->insert_id;

// Tab 1: Visão Geral
xq($mysqli, "INSERT INTO vtiger_painelbi_tabs (board_id,titulo,sequencia,deleted) VALUES ('$boardId','Visão Geral',0,0)");
$tab1 = $mysqli->insert_id;

// Tab 2: Atendentes
xq($mysqli, "INSERT INTO vtiger_painelbi_tabs (board_id,titulo,sequencia,deleted) VALUES ('$boardId','Atendentes',1,0)");
$tab2 = $mysqli->insert_id;

// Tab 3: Leads Recentes
xq($mysqli, "INSERT INTO vtiger_painelbi_tabs (board_id,titulo,sequencia,deleted) VALUES ('$boardId','Leads Recentes',2,0)");
$tab3 = $mysqli->insert_id;

// Widgets Tab 1 (Visão Geral)
xq($mysqli, "INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ('$tab1','$r1',6,0,0)"); // Funil (col 6)
xq($mysqli, "INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ('$tab1','$r2',6,1,0)"); // Evolução (col 6)
xq($mysqli, "INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ('$tab1','$r4',6,2,0)"); // Por Origem
xq($mysqli, "INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ('$tab1','$r6',6,3,0)"); // Este Mês

// Widgets Tab 2 (Atendentes)
xq($mysqli, "INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ('$tab2','$r3',12,0,0)"); // Atendentes full width

// Widgets Tab 3 (Leads Recentes)
xq($mysqli, "INSERT INTO vtiger_painelbi_widgets (tab_id,relatorio_id,largura,sequencia,deleted) VALUES ('$tab3','$r5',12,0,0)"); // Tabela detalhada full width

echo "    OK — boardId=$boardId, tabs=$tab1,$tab2,$tab3\n";

// ─── [8/9] Regenerar tabdata e user_privileges ───────────────────────────────
echo "[8/9] Regenerando tabdata e user_privileges...\n";
chdir('/var/www/html');
define('VTIGER_ROOT_DIRECTORY', '/var/www/html');
require_once 'vendor/autoload.php';
require_once 'include/utils/CommonUtils.php';
require_once 'include/database/PearDatabase.php';
require_once 'include/utils/VtlibUtils.php';
require_once 'modules/Users/CreateUserPrivilegeFile.php';
create_tab_data_file();
vtlib_RecreateUserPrivilegeFiles();
echo "    OK\n";

// ─── [9/9] tabdata.php manual (sequência) ─────────────────────────────────────
echo "[9/9] Atualizando tabdata...\n";
$tabdataFile = '/var/www/html/user_privileges/tabdata.php';
if (file_exists($tabdataFile)) {
    $content = file_get_contents($tabdataFile);
    if (strpos($content, "'$tabId'") === false && strpos($content, "\"$tabId\"") === false) {
        $content = str_replace('$tab_seq_array = array(', "\$tab_seq_array = array('$tabId'=>0,", $content);
        file_put_contents($tabdataFile, $content);
        echo "    tabdata.php atualizado\n";
    } else {
        echo "    tabdata.php já contém tabid=$tabId\n";
    }
}

$mysqli->close();

echo "\n\u2705 PainelBI instalado!\n";
echo "   tabid=$tabId | board=$boardId | relatórios: $r1,$r2,$r3,$r4,$r5,$r6\n\n";
echo "Limpe o cache:\n";
echo "  docker exec crmbot74-vtiger-1 bash -c \"rm -rf /var/www/html/cache/templates_c/* /var/www/html/cache/modules/*\"\n";
echo "Acesse:\n";
echo "  http://localhost:8181/index.php?module=PainelBI&view=Dashboard\n";
