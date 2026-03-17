<?php
/**
 * Traduz picklists para PT-BR usando hex2bin() para garantir UTF-8 correto,
 * independente do encoding do arquivo no Windows.
 * Rodar: docker exec crmbot74-vtiger-1 php /var/www/html/modules/MetasVendedores/traducao_picklist.php
 */
$c = new mysqli('vtiger-db', 'vtiger', (getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'), 'vtiger');
if ($c->connect_error) die("ERRO: " . $c->connect_error . "\n");
$c->set_charset('utf8mb4');

// Caracteres especiais como bytes UTF-8 (hex2bin garante encoding correto)
$cc = hex2bin('c3a7'); // c cedilha
$at = hex2bin('c3a3'); // a til
$aa = hex2bin('c3a1'); // a acento agudo
$oo = hex2bin('c3b3'); // o acento agudo

$updates = [
    ['vtiger_sales_stage', 'sales_stage', 'sales_stage_id', [
        1  => 'Prospec' . $cc . $at . 'o',
        2  => 'Qualifica' . $cc . $at . 'o',
        3  => 'An' . $aa . 'lise de Necessidades',
        4  => 'Proposta de Valor',
        5  => 'Identifica' . $cc . $at . 'o de Decisores',
        6  => 'An' . $aa . 'lise de Percep' . $cc . $at . 'o',
        7  => 'Proposta / Or' . $cc . 'amento',
        8  => 'Negocia' . $cc . $at . 'o',
        9  => 'Fechado - Ganho',
        10 => 'Fechado - Perdido',
    ]],
    ['vtiger_opportunity_type', 'opportunity_type', 'opptypeid', [
        2 => 'Neg' . $oo . 'cio Existente',
        3 => 'Novo Neg' . $oo . 'cio',
    ]],
];

foreach ($updates as [$tabela, $coluna, $pk, $map]) {
    echo "=== $tabela ===\n";
    foreach ($map as $id => $valor) {
        $stmt = $c->prepare("UPDATE `$tabela` SET `$coluna` = ? WHERE `$pk` = ?");
        $stmt->bind_param('si', $valor, $id);
        if ($stmt->execute()) {
            echo "  OK  id=$id -> $valor\n";
        } else {
            echo "  ERRO id=$id : " . $stmt->error . "\n";
        }
        $stmt->close();
    }
}

$c->close();
echo "\nConcluido.\n";
