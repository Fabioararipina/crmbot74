<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

chdir('/var/www/html');
define('VTIGER_ROOT_DIRECTORY', '/var/www/html');

require_once 'vendor/autoload.php';
require_once 'include/database/PearDatabase.php';

// Testa carregamento dos models
echo "Testando models...\n";

require_once 'modules/MetasVendedores/models/Picklist.php';
echo "Picklist.php: OK\n";

require_once 'modules/MetasVendedores/models/Record.php';
echo "Record.php: OK\n";

require_once 'modules/MetasVendedores/models/Module.php';
echo "Module.php: OK\n";

echo "\nTestando picklists dinâmicos...\n";
$statuses = MetasVendedores_Picklist_Model::getLeadStatuses();
echo "Lead statuses: " . implode(', ', $statuses) . "\n";

$stages = MetasVendedores_Picklist_Model::getSalesStages();
echo "Sales stages: " . implode(', ', $stages) . "\n";

$types = MetasVendedores_Picklist_Model::getOpportunityTypes();
echo "Opp types: " . implode(', ', $types) . "\n";

$equipes = MetasVendedores_Picklist_Model::getEquipes();
echo "Equipes: " . count($equipes) . " encontradas\n";

echo "\nTestando Record...\n";
$metas = MetasVendedores_Record_Model::getAll();
echo "Metas cadastradas: " . count($metas) . "\n";

echo "\nTudo OK!\n";
