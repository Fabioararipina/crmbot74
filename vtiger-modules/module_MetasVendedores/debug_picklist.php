<?php
$c = new mysqli('vtiger-db', 'vtiger', (getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'), 'vtiger');
$c->set_charset('utf8mb4');

echo "=== vtiger_sales_stage (todos) ===\n";
$r = $c->query("SELECT sales_stage_id, sales_stage FROM vtiger_sales_stage ORDER BY sortorderid");
while ($row = $r->fetch_assoc()) echo "  {$row['sales_stage_id']}: {$row['sales_stage']}\n";

echo "\n=== vtiger_opportunity_type (todos) ===\n";
$r = $c->query("SELECT opptypeid, opportunity_type FROM vtiger_opportunity_type ORDER BY sortorderid");
while ($row = $r->fetch_assoc()) echo "  {$row['opptypeid']}: {$row['opportunity_type']}\n";

echo "\n=== vtiger_leadstatus (todos) ===\n";
$r = $c->query("SELECT leadstatusid, leadstatus FROM vtiger_leadstatus ORDER BY sortorderid");
while ($row = $r->fetch_assoc()) echo "  {$row['leadstatusid']}: {$row['leadstatus']}\n";

$c->close();
