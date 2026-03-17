<?php
$c = new mysqli('vtiger-db','vtiger',(getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'),'vtiger');
$c->set_charset('utf8');

// Exact query vTiger uses to build navigation (from Vtiger_Menu_Helper_Model)
$sql = "SELECT vtiger_tab.tabid, vtiger_tab.name, vtiger_tab.presence,
        vtiger_parenttabrel.parenttabid, vtiger_parenttabrel.sequence
        FROM vtiger_tab
        INNER JOIN vtiger_parenttabrel ON vtiger_tab.tabid = vtiger_parenttabrel.tabid
        WHERE vtiger_parenttabrel.parenttabid = 3
        ORDER BY vtiger_parenttabrel.sequence";
$r = $c->query($sql);
echo "=== Modules in Vendas (parenttabid=3) ===\n";
while ($row = $r->fetch_assoc()) {
    echo "tabid={$row['tabid']} name={$row['name']} presence={$row['presence']} seq={$row['sequence']}\n";
}

// Also check vtiger_tab directly for MetasVendedores
$r2 = $c->query("SELECT * FROM vtiger_tab WHERE tabid=50");
echo "\n=== vtiger_tab row for tabid=50 ===\n";
$row = $r2->fetch_assoc();
foreach ($row as $k=>$v) echo "$k=$v\n";
