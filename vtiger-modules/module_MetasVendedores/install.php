<?php
/**
 * MetasVendedores — Script de Instalação (v2 — mysqli direto, sem PearDatabase)
 * Executa UMA VEZ dentro do container vTiger para registrar o módulo.
 *
 * Comando:
 *   docker exec crmbot74-vtiger-1 php /var/www/html/modules/MetasVendedores/install.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ─── Conexão direta mysqli (não usa PearDatabase para evitar problemas de transação) ───
$host = 'vtiger-db';
$user = 'vtiger';
$pass = getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV';
$db   = 'vtiger';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    die("ERRO DE CONEXÃO: " . $mysqli->connect_error . "\n");
}
$mysqli->set_charset('utf8mb4');
echo "Conexão com banco: OK (vtiger-db)\n\n";

// ─── Helper ───────────────────────────────────────────────────────────────────
function xq(mysqli $c, string $sql, array $params = []): mysqli_result|bool {
    if (empty($params)) {
        $r = $c->query($sql);
        if ($r === false) die("ERRO SQL: " . $c->error . "\nSQL: $sql\n");
        return $r;
    }
    $stmt = $c->prepare($sql);
    if (!$stmt) die("ERRO PREPARE: " . $c->error . "\nSQL: $sql\n");
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) die("ERRO EXECUTE: " . $stmt->error . "\nSQL: $sql\n");
    $r = $stmt->get_result();
    $stmt->close();
    return $r !== false ? $r : true;
}

function xget(mysqli $c, string $sql, array $params = []): ?array {
    $r = xq($c, $sql, $params);
    if ($r === true || !$r) return null;
    return $r->fetch_assoc();
}

function xmax(mysqli $c, string $table, string $col): int {
    $row = xget($c, "SELECT COALESCE(MAX($col), 0) AS m FROM $table");
    return (int)($row['m'] ?? 0);
}

// ─── Verificar instalação existente ──────────────────────────────────────────
$existing = xget($c = $mysqli, "SELECT tabid FROM vtiger_tab WHERE name = 'MetasVendedores'");
if ($existing) {
    echo "[INFO] Já instalado (tabid=" . $existing['tabid'] . "). Use uninstall.php primeiro.\n";
    exit(0);
}

echo "=== Instalando MetasVendedores ===\n\n";

// ─── [1/7] vtiger_tab ────────────────────────────────────────────────────────
echo "[1/7] Registrando em vtiger_tab...\n";
$tabId  = xmax($c, 'vtiger_tab', 'tabid') + 1;
$tabSeq = xmax($c, 'vtiger_tab', 'tabsequence') + 10;

xq($c,
    "INSERT INTO vtiger_tab
     (tabid, name, presence, tabsequence, tablabel, customized,
      ownedby, isentitytype, trial, version, issyncable, allowduplicates)
     VALUES (?, 'MetasVendedores', 1, ?, 'Metas de Vendedores', 1,
      0, 0, 0, '1.0', 0, 1)",
    [(string)$tabId, (string)$tabSeq]
);

// Verificar se gravou
$check = xget($c, "SELECT tabid FROM vtiger_tab WHERE name = 'MetasVendedores'");
if (!$check) die("ERRO: INSERT em vtiger_tab falhou silenciosamente!\n");
echo "    OK — tabid=$tabId, tabsequence=$tabSeq (verificado no banco)\n";

// ─── [2/7] Tabelas ───────────────────────────────────────────────────────────
echo "[2/7] Criando tabela vtiger_metasvendedores...\n";
xq($c, "DROP TABLE IF EXISTS vtiger_metasvendedores");
xq($c, "CREATE TABLE vtiger_metasvendedores (
    id                    INT(19)       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titulo                VARCHAR(255)  NOT NULL DEFAULT '',
    secao                 VARCHAR(50)   NOT NULL DEFAULT 'oportunidades',
    equipe_id             INT(11)       DEFAULT NULL,
    equipe_nome           VARCHAR(255)  DEFAULT NULL,
    usuario_id            INT(11)       DEFAULT NULL,
    usuario_nome          VARCHAR(255)  DEFAULT NULL,
    periodo_inicio        DATE          DEFAULT NULL,
    periodo_fim           DATE          DEFAULT NULL,
    tipo_produto          VARCHAR(100)  DEFAULT NULL,
    sales_stage_alvo      VARCHAR(100)  DEFAULT 'Closed Won',
    meta_valor            DECIMAL(15,2) DEFAULT 0.00,
    meta_quantidade       INT(11)       DEFAULT 0,
    estagio_origem        VARCHAR(100)  DEFAULT NULL,
    estagio_destino       VARCHAR(100)  DEFAULT NULL,
    meta_taxa_conversao   DECIMAL(5,2)  DEFAULT NULL,
    meta_quantidade_funil INT(11)       DEFAULT 0,
    deleted               TINYINT(1)    DEFAULT 0,
    smownerid             INT(11)       DEFAULT 1,
    createdtime           DATETIME      DEFAULT NULL,
    modifiedtime          DATETIME      DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "    OK\n";

// ─── [3/7] Blocos ────────────────────────────────────────────────────────────
echo "[3/7] Registrando blocos...\n";
$b = xmax($c, 'vtiger_blocks', 'blockid');
$b1 = ++$b; $b2 = ++$b; $b3 = ++$b;

$bi = "INSERT INTO vtiger_blocks (blockid,tabid,blocklabel,sequence,show_title,visible,create_view,edit_view,detail_view,display_status,iscustom) VALUES (?,?,'%s',?,1,1,1,1,1,1,1)";
xq($c, sprintf($bi,'Informações da Meta'),     [(string)$b1,(string)$tabId,'1']);
xq($c, sprintf($bi,'Metas de Oportunidades'),  [(string)$b2,(string)$tabId,'2']);
xq($c, sprintf($bi,'Metas de Funil de Leads'), [(string)$b3,(string)$tabId,'3']);
echo "    OK — blockids=$b1,$b2,$b3\n";

// ─── [4/7] Campos ────────────────────────────────────────────────────────────
echo "[4/7] Registrando campos...\n";
$fid = xmax($c, 'vtiger_field', 'fieldid') + 1;

$fi = "INSERT INTO vtiger_field
    (fieldid,tabid,columnname,tablename,generatedtype,uitype,
     fieldname,fieldlabel,readonly,presence,defaultvalue,maximumlength,masseditable,
     sequence,block,displaytype,typeofdata,quickcreate,quickcreatesequence,
     info_type,helpinfo,summaryfield,headerfield,isunique)
    VALUES (?,?,'%s','vtiger_metasvendedores',1,%d,'%s','%s',0,0,'%s',255,0,%d,?,'1','%s',0,0,'BAS','',0,0,0)";

$fields = [
    [$fid++,$tabId,'titulo',          2,'titulo',                'Título',                  '',           1,$b1,'V~M'],
    [$fid++,$tabId,'secao',           2,'secao',                 'Seção',                   'oportunidades',2,$b1,'V~M'],
    [$fid++,$tabId,'equipe_nome',     2,'equipe_nome',           'Equipe',                  '',           3,$b1,'V~O'],
    [$fid++,$tabId,'usuario_nome',    2,'usuario_nome',          'Vendedor',                '',           4,$b1,'V~O'],
    [$fid++,$tabId,'periodo_inicio',  5,'periodo_inicio',        'Período Início',          '',           5,$b1,'D~M'],
    [$fid++,$tabId,'periodo_fim',     5,'periodo_fim',           'Período Fim',             '',           6,$b1,'D~M'],
    [$fid++,$tabId,'tipo_produto',    2,'tipo_produto',          'Tipo de Produto',         '',           1,$b2,'V~O'],
    [$fid++,$tabId,'sales_stage_alvo',2,'sales_stage_alvo',     'Estágio Alvo',            'Closed Won', 2,$b2,'V~O'],
    [$fid++,$tabId,'meta_valor',     71,'meta_valor',            'Meta Valor (R$)',         '0.00',       3,$b2,'N~O'],
    [$fid++,$tabId,'meta_quantidade', 7,'meta_quantidade',       'Meta Quantidade',         '0',          4,$b2,'I~O'],
    [$fid++,$tabId,'estagio_origem',  2,'estagio_origem',        'Estágio Origem',          '',           1,$b3,'V~O'],
    [$fid++,$tabId,'estagio_destino', 2,'estagio_destino',       'Estágio Destino',         '',           2,$b3,'V~O'],
    [$fid++,$tabId,'meta_taxa_conversao',7,'meta_taxa_conversao','Meta Taxa Conversão (%)', '',           3,$b3,'N~O'],
    [$fid++,$tabId,'meta_quantidade_funil',7,'meta_quantidade_funil','Meta Qtd (Funil)',    '0',          4,$b3,'I~O'],
];

foreach ($fields as $f) {
    [$id,$tid,$col,$uit,$fn,$fl,$def,$seq,$bid,$tod] = $f;
    $sql = sprintf($fi, $col, $uit, $fn, $fl, $def, $seq, $tod);
    xq($c, $sql, [(string)$id, (string)$tid, (string)$bid]);
}
echo "    OK — " . count($fields) . " campos\n";

// ─── [5/7] Permissões ────────────────────────────────────────────────────────
echo "[5/7] Configurando permissões...\n";
// vtiger_def_org_share: ruleid é auto_increment, editstatus=0
xq($c, "INSERT IGNORE INTO vtiger_def_org_share (tabid,permission,editstatus) VALUES (?,3,0)", [(string)$tabId]);

$profiles = xq($c, "SELECT profileid FROM vtiger_profile");
$fieldIds = [];
$fr = xq($c, "SELECT fieldid FROM vtiger_field WHERE tabid=?", [(string)$tabId]);
while ($row = $fr->fetch_assoc()) { $fieldIds[] = $row['fieldid']; }

while ($pr = $profiles->fetch_assoc()) {
    $pid = $pr['profileid'];
    // profile2tab: permissions=0 (acesso total)
    xq($c, "INSERT IGNORE INTO vtiger_profile2tab (profileid,tabid,permissions) VALUES (?,?,0)", [(string)$pid,(string)$tabId]);
    // profile2field: visible=1, readonly=0
    foreach ($fieldIds as $fid2) {
        xq($c, "INSERT IGNORE INTO vtiger_profile2field (profileid,tabid,fieldid,visible,readonly) VALUES (?,?,?,1,0)", [(string)$pid,(string)$tabId,(string)$fid2]);
    }
}
echo "    OK\n";

// ─── [6/7] Menu ──────────────────────────────────────────────────────────────
echo "[6/7] Adicionando ao menu...\n";

// vtiger_links (HEADERLINK — obrigatório para o módulo ser reconhecido)
$linkId  = xmax($c, 'vtiger_links', 'linkid') + 1;
$linkUrl = 'index.php?module=MetasVendedores&view=List';
xq($c,
    "INSERT INTO vtiger_links (linkid,tabid,linktype,linklabel,linkurl,linkicon,sequence,handler_path,handler_class,handler,parent_link)
     VALUES (?,0,?,?,?,?,99,'','','',0)",
    [(string)$linkId, 'HEADERLINK', 'Metas de Vendedores', $linkUrl, '']
);
echo "    HEADERLINK — linkid=$linkId\n";

// vtiger_tab: presence=0 (ativo) e parent='Sales' (obrigatório para o menu lateral)
xq($c, "UPDATE vtiger_tab SET presence=0, parent='Sales' WHERE tabid=?", [(string)$tabId]);
echo "    vtiger_tab: presence=0, parent=Sales\n";

// vtiger_app2tab: ESTA É A TABELA REAL do menu lateral no vTiger 8.4
// Sem esta entrada o módulo não aparece no menu independente de qualquer outra configuração
xq($c, "DELETE FROM vtiger_app2tab WHERE tabid=?", [(string)$tabId]);
$appSeq = xmax($c, 'vtiger_app2tab', 'sequence') + 1;
xq($c,
    "INSERT INTO vtiger_app2tab (tabid, appname, sequence, visible) VALUES (?, 'SALES', ?, 1)",
    [(string)$tabId, (string)$appSeq]
);
echo "    vtiger_app2tab: SALES, sequence=$appSeq, visible=1\n";

// vtiger_parenttabrel: agrupamento legado (necessário para consistência)
xq($c, "DELETE FROM vtiger_parenttabrel WHERE tabid=?", [(string)$tabId]);
$ptSeq = xmax($c, 'vtiger_parenttabrel', 'sequence') + 1;
xq($c,
    "INSERT INTO vtiger_parenttabrel (parenttabid, tabid, sequence) VALUES (3, ?, ?)",
    [(string)$tabId, (string)$ptSeq]
);
echo "    vtiger_parenttabrel: parenttabid=3 (Sales), sequence=$ptSeq\n";

echo "    OK — menu configurado\n";

// ─── [7/7] Regenerar tabdata e user_privileges ───────────────────────────────
echo "[7/7] Regenerando tabdata e user_privileges...\n";
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

$mysqli->close();

echo "\n✅ MetasVendedores instalado e VERIFICADO no banco!\n";
echo "   tabid=$tabId | blockids=$b1,$b2,$b3\n\n";
echo "Limpe o cache:\n";
echo "  docker exec crmbot74-vtiger-1 bash -c \"rm -rf /var/www/html/cache/templates_c/* /var/www/html/cache/modules/*\"\n";
echo "Acesse:\n";
echo "  http://localhost:8181/index.php?module=MetasVendedores&view=List\n";
