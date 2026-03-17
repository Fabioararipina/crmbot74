<?php
$c = new mysqli('vtiger-db', 'vtiger', (getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'), 'vtiger');
if ($c->connect_error) die("ERRO: " . $c->connect_error . "\n");
$c->set_charset('utf8mb4');

// parenttabid=3 = Sales = "Vendas" no menu traduzido
$vendas_id = 3;

// Sequencia atual no grupo Sales
$r = $c->query("SELECT MAX(sequence) AS m FROM vtiger_parenttabrel WHERE parenttabid=$vendas_id");
$seq = (int)($r->fetch_assoc()['m'] ?? 0) + 1;

// Remove de qualquer grupo anterior
$c->query("DELETE FROM vtiger_parenttabrel WHERE tabid=50");

// Insere no grupo Vendas
$stmt = $c->prepare("INSERT INTO vtiger_parenttabrel (parenttabid, tabid, sequence) VALUES (?,50,?)");
$stmt->bind_param('ii', $vendas_id, $seq);
echo $stmt->execute()
    ? "OK — MetasVendedores adicionado ao menu Vendas (sequence=$seq)\n"
    : "ERRO: " . $stmt->error . "\n";
$stmt->close();
$c->close();
