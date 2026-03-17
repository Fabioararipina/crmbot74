<?php
// Gera o PHP serializado correto para cada VTWebhookTask
// e atualiza diretamente no banco de dados

$pdo = new PDO('mysql:host=vtiger-db;port=3306;dbname=vtiger', 'vtiger', (getenv('VTIGER_DB_PASS') ?: 'COLOQUE_NO_ENV'));

function makeTask($taskId, $workflowId, $summary, $url, $body) {
    $obj = new stdClass();
    $obj->executeImmediately = true;
    $obj->workflowId = $workflowId;
    $obj->summary = $summary;
    $obj->active = true;
    $obj->url = $url;
    $obj->method = 'POST';
    $obj->headers = 'x-conector-key: ' . (getenv('CONECTOR_API_KEY') ?: 'COLOQUE_NO_ENV') . '';
    $obj->body = $body;
    $obj->id = $taskId;

    // Serializa como stdClass e substitui pelo nome correto da classe
    $serialized = serialize($obj);
    $serialized = str_replace('O:8:"stdClass"', 'O:13:"VTWebhookTask"', $serialized);
    return $serialized;
}

$tasks = [
    // [task_id, workflow_id, summary, url, body]
    [
        null, // será buscado
        25,
        'Sync lead para Prime Atende',
        'http://host.docker.internal:3010/vtiger/lead-criado',
        '{"leadId":"$(id)","nome":"$(firstname) $(lastname)","telefone":"$(phone)"}'
    ],
    [
        null,
        26,
        'Disparar follow-up Prime Atende',
        'http://host.docker.internal:3010/vtiger/disparo',
        '{"leadId":"$(id)","telefone":"$(phone)","template":"followup_3dias","sistema":"prime"}'
    ],
    [
        null,
        27,
        'Disparar reativacao via Bot74',
        'http://host.docker.internal:3010/vtiger/disparo',
        '{"leadId":"$(id)","telefone":"$(phone)","template":"reativacao_7dias","sistema":"bot74"}'
    ],
    [
        null,
        28,
        'Disparar proposta Prime Atende',
        'http://host.docker.internal:3010/vtiger/disparo',
        '{"leadId":"$(id)","telefone":"$(phone)","template":"envio_proposta","sistema":"prime"}'
    ],
];

foreach ($tasks as $row) {
    [$taskId, $workflowId, $summary, $url, $body] = $row;

    // Busca task_id pelo workflow_id
    $stmt = $pdo->prepare("SELECT task_id FROM com_vtiger_workflowtasks WHERE workflow_id = ?");
    $stmt->execute([$workflowId]);
    $taskId = $stmt->fetchColumn();

    if (!$taskId) {
        echo "AVISO: task_id não encontrado para workflow $workflowId\n";
        continue;
    }

    $serialized = makeTask((int)$taskId, $workflowId, $summary, $url, $body);

    $update = $pdo->prepare("UPDATE com_vtiger_workflowtasks SET task = ? WHERE task_id = ?");
    $update->execute([$serialized, $taskId]);

    echo "Workflow $workflowId / Task $taskId atualizado OK\n";
    echo "  Serializado: " . substr($serialized, 0, 80) . "...\n";
}

echo "\nVerificando desserialização:\n";
$stmt = $pdo->query("SELECT task_id, workflow_id, task FROM com_vtiger_workflowtasks WHERE workflow_id IN (25,26,27,28)");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $obj = unserialize($row['task']);
    if ($obj === false) {
        echo "Task {$row['task_id']} (wf {$row['workflow_id']}): ERRO NA DESSERIALIZAÇÃO\n";
    } else {
        echo "Task {$row['task_id']} (wf {$row['workflow_id']}): OK — url={$obj->url}\n";
    }
}
