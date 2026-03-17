<?php
$c = new mysqli('vtiger-db', 'vtiger', (getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'), 'vtiger');
$c->set_charset('utf8mb4');

foreach (['vtiger_profile2tab','vtiger_profile2field','vtiger_def_org_share','vtiger_links'] as $t) {
    echo "=== $t ===\n";
    $r = $c->query("DESCRIBE $t");
    if (!$r) { echo "ERRO: " . $c->error . "\n"; continue; }
    while ($row = $r->fetch_assoc()) echo "  " . $row['Field'] . ' (' . $row['Type'] . ")\n";
}
