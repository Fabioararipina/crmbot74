<?php
// Fix 1: TaskRecord.php — $args[1] pode não existir
$file1 = '/var/www/html/modules/Settings/Workflows/models/TaskRecord.php';
$c = file_get_contents($file1);
$c = str_replace(
    '$taskId = $args[0];
		$workflowModel = $args[1];',
    '$taskId = $args[0];
		$workflowModel = $args[1] ?? null;',
    $c
);
file_put_contents($file1, $c);
echo "Fix 1 OK — TaskRecord.php\n";

// Fix 2 + 3 + 4: EditTask.php — variáveis não inicializadas e explode sem espaço
$file2 = '/var/www/html/modules/Settings/Workflows/views/EditTask.php';
$c = file_get_contents($file2);

// Inicializar $emailFieldoptions antes do foreach
$c = str_replace(
    '$emailFields = $recordStructureInstance->getAllEmailFields();
		foreach($emailFields as $metaKey => $emailField) {
			$emailFieldoptions .=',
    '$emailFields = $recordStructureInstance->getAllEmailFields();
		$emailFieldoptions = \'\';
		foreach($emailFields as $metaKey => $emailField) {
			$emailFieldoptions .=',
    $c
);

// Fix explode sem espaço no metaKey (line 152)
$c = str_replace(
    'list($relationFieldName, $rest) = explode(\' \', $metaKey);',
    'list($relationFieldName, $rest) = array_pad(explode(\' \', $metaKey, 2), 2, \'\');',
    $c
);

// Inicializar $allFieldoptions antes do foreach
$c = str_replace(
    '$structure = $recordStructureInstance->getStructure();
		foreach ($structure as $fields) {
            foreach ($fields as $field) {
                if ($field->get(\'workflow_pt_lineitem_field\')) {
                    $allFieldoptions .=',
    '$structure = $recordStructureInstance->getStructure();
		$allFieldoptions = \'\';
		foreach ($structure as $fields) {
            foreach ($fields as $field) {
                if ($field->get(\'workflow_pt_lineitem_field\')) {
                    $allFieldoptions .=',
    $c
);

file_put_contents($file2, $c);
echo "Fix 2+3+4 OK — EditTask.php\n";

// Verificar que as correções foram aplicadas
$c1 = file_get_contents($file1);
$c2 = file_get_contents($file2);
echo "TaskRecord args[1] ?? null: " . (strpos($c1, '$args[1] ?? null') !== false ? 'OK' : 'FALHOU') . "\n";
echo "emailFieldoptions init: " . (strpos($c2, "\$emailFieldoptions = ''") !== false ? 'OK' : 'FALHOU') . "\n";
echo "array_pad explode: " . (strpos($c2, 'array_pad(explode') !== false ? 'OK' : 'FALHOU') . "\n";
echo "allFieldoptions init: " . (strpos($c2, "\$allFieldoptions = ''") !== false ? 'OK' : 'FALHOU') . "\n";
