<?php
/**
 * Cria os estagios dos 4 funis da Essencial Saude em vtiger_sales_stage
 * e os 4 tipos de funil em vtiger_opportunity_type.
 *
 * Removidos da lista:
 *   - "Novo Lead"    (no modulo Oportunidades ja e contato, nao lead)
 *   - "Sem Retorno"  (coberto pelos terminais nativos: Fechado-Perdido / Fechado-Ganho)
 *
 * Rodar: docker exec crmbot74-vtiger-1 php /var/www/html/modules/MetasVendedores/criar_funis.php
 */
$c = new mysqli('vtiger-db', 'vtiger', (getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'), 'vtiger');
if ($c->connect_error) die("ERRO conexao: " . $c->connect_error . "\n");
$c->set_charset('utf8mb4');

// Bytes UTF-8 (hex2bin evita problema de encoding no Windows)
$cc = hex2bin('c3a7'); // c cedilha
$at = hex2bin('c3a3'); // a til
$aa = hex2bin('c3a1'); // a acento agudo
$ii = hex2bin('c3ad'); // i acento agudo
$oo = hex2bin('c3b3'); // o acento agudo

// ─────────────────────────────────────────────────────────────────────────────
// 14 ESTAGIOS — 4 funis consolidados (sem "Novo Lead" e sem "Sem Retorno")
// [C]=Cadeiras  [K]=Cartao  [G]=Clinica Geral  [M]=Dr.Mauro
// ─────────────────────────────────────────────────────────────────────────────
$stages = [
    [1,  'Contato Realizado',                          300],  // C K M
    [2,  'Qualificado',                                301],  // G
    [3,  'Avalia' . $cc . $at . 'o Agendada',         302],  // C
    [4,  'Agendado',                                   303],  // G
    [5,  'Avalia' . $cc . $at . 'o Realizada',        304],  // C M
    [6,  'Proposta Apresentada',                       305],  // K
    [7,  'Plano Apresentado',                          306],  // M
    [8,  'Negocia' . $cc . $at . 'o',                 307],  // C K M
    [9,  'Atendido',                                   308],  // G
    [10, 'Em Execu' . $cc . $at . 'o',                309],  // M
    [11, 'Ganho',                                      310],  // C K M
    [12, 'Conclu' . $ii . 'do',                       311],  // M
    [13, 'N' . $at . 'o Compareceu',                  312],  // C G
    [14, 'Perdido',                                    313],  // C K G M
];

// ─────────────────────────────────────────────────────────────────────────────
// 4 TIPOS DE OPORTUNIDADE — um por funil
// ─────────────────────────────────────────────────────────────────────────────
$types = [
    [1, 'Cadeiras - Odonto Popular',              320],
    [2, 'Cart' . $at . 'o Essencial Clube',       321],
    [3, 'Cl' . $ii . 'nica Geral',               322],
    [4, 'Dr. Mauro - High Ticket',                323],
];

// ─── Aplicar: vtiger_sales_stage ─────────────────────────────────────────────
echo "=== vtiger_sales_stage ===\n";
$c->query("DELETE FROM vtiger_sales_stage");
echo "  Removidos estagios anteriores.\n";

foreach ($stages as [$sort, $nome, $vid]) {
    $stmt = $c->prepare(
        "INSERT INTO vtiger_sales_stage (sales_stage, presence, picklist_valueid, sortorderid) VALUES (?, 1, ?, ?)"
    );
    $stmt->bind_param('sii', $nome, $vid, $sort);
    echo $stmt->execute() ? "  OK  #$sort -> $nome\n" : "  ERRO: " . $stmt->error . "\n";
    $stmt->close();
}

// ─── Aplicar: vtiger_opportunity_type ────────────────────────────────────────
echo "\n=== vtiger_opportunity_type ===\n";
$c->query("DELETE FROM vtiger_opportunity_type");
echo "  Removidos tipos anteriores.\n";

foreach ($types as [$sort, $nome, $vid]) {
    $stmt = $c->prepare(
        "INSERT INTO vtiger_opportunity_type (opportunity_type, presence, picklist_valueid, sortorderid) VALUES (?, 1, ?, ?)"
    );
    $stmt->bind_param('sii', $nome, $vid, $sort);
    echo $stmt->execute() ? "  OK  #$sort -> $nome\n" : "  ERRO: " . $stmt->error . "\n";
    $stmt->close();
}

$c->close();
echo "\nConcluido. Limpe o cache.\n";
