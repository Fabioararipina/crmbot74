<?php
$db = new mysqli('vtiger-db','vtiger','FyyU8aWNoMCHI908izpnmTHfIBU','vtiger');
$r = $db->query("SELECT DISTINCT sales_stage, COUNT(*) as c FROM vtiger_potential GROUP BY sales_stage ORDER BY c DESC");
while($row=$r->fetch_assoc()) echo $row['sales_stage'].' ('.$row['c'].')'.PHP_EOL;
// modtracker check
$r2 = $db->query("SELECT COUNT(*) as c FROM vtiger_modtracker_detail WHERE fieldname='sales_stage' LIMIT 1");
$row2 = $r2->fetch_assoc();
echo "modtracker sales_stage entries: ".$row2['c'].PHP_EOL;
