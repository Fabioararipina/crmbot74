<?php
/**
 * Fix: vtiger_role2picklist para opportunity_type
 *
 * Após criar_funis.php reinserir os tipos com picklist_valueid 320-323,
 * a tabela vtiger_role2picklist ainda apontava para os IDs antigos (127,128,129).
 * Este script corrige isso para todas as roles.
 *
 * Rodar UMA VEZ e depois deletar o arquivo.
 * URL: https://crm.bot74.com.br/modules/MetasVendedores/fix_role2picklist_optype.php
 */

$c = new mysqli('vtiger-db', 'vtiger', (getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'), 'vtiger');
if ($c->connect_error) die("ERRO conexao: " . $c->connect_error . "\n");
$c->set_charset('utf8mb4');

echo "=== Fix vtiger_role2picklist — opportunity_type (picklistid=16) ===\n\n";

// 1. Verificar roles existentes
$roles = [];
$r = $c->query("SELECT DISTINCT roleid FROM vtiger_role2picklist");
while ($row = $r->fetch_assoc()) $roles[] = $row['roleid'];
echo "Roles encontradas: " . implode(', ', $roles) . "\n\n";

// 2. Remover entradas antigas (IDs que não existem mais em vtiger_opportunity_type)
$del = $c->query("DELETE FROM vtiger_role2picklist WHERE picklistid=16 AND picklistvalueid NOT IN (SELECT picklist_valueid FROM vtiger_opportunity_type)");
echo "Entradas antigas removidas: " . $c->affected_rows . "\n";

// 3. Buscar novos picklist_valueid
$novos = [];
$r = $c->query("SELECT picklist_valueid, opportunity_type FROM vtiger_opportunity_type ORDER BY sortorderid");
while ($row = $r->fetch_assoc()) $novos[] = $row;
echo "Novos tipos: " . count($novos) . "\n";
foreach ($novos as $n) echo "  picklist_valueid={$n['picklist_valueid']} → {$n['opportunity_type']}\n";

// 4. Inserir para cada role, ignorando duplicatas
$inserted = 0;
foreach ($roles as $roleid) {
    foreach ($novos as $i => $n) {
        $vid = (int)$n['picklist_valueid'];
        $stmt = $c->prepare("INSERT IGNORE INTO vtiger_role2picklist (roleid, picklistvalueid, picklistid, sortid) VALUES (?, ?, 16, ?)");
        $stmt->bind_param('sii', $roleid, $vid, $i);
        $stmt->execute();
        $inserted += $stmt->affected_rows;
        $stmt->close();
    }
}
echo "\nEntradas inseridas: $inserted\n";

// 5. Verificar resultado final
echo "\n=== Resultado final ===\n";
$r = $c->query("SELECT roleid, picklistvalueid, sortid FROM vtiger_role2picklist WHERE picklistid=16 ORDER BY roleid, sortid");
while ($row = $r->fetch_assoc()) {
    echo "  {$row['roleid']} → picklistvalueid={$row['picklistvalueid']} (sort={$row['sortid']})\n";
}

$c->close();
echo "\nConcluido. DELETE este arquivo do servidor.\n";
