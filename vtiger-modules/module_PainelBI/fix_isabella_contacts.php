<?php
/**
 * fix_isabella_contacts.php
 * Tenta vincular contatos de Isabella a leads via telefone ou nome.
 * Uso: php fix_isabella_contacts.php           (dry-run, só mostra)
 *      php fix_isabella_contacts.php --aplicar  (aplica no banco)
 */

$aplicar = in_array('--aplicar', $argv ?? []);
$db = new mysqli('vtiger-db', 'vtiger', 'FyyU8aWNoMCHI908izpnmTHfIBU', 'vtiger');
$db->set_charset('utf8');

echo $aplicar ? "=== MODO APLICAR ===\n\n" : "=== DRY-RUN (use --aplicar para salvar) ===\n\n";

// Buscar contatos de Isabella sem cf_origem_lead_id
$r = $db->query("
    SELECT cd.contactid, cd.firstname, cd.lastname, cd.phone
    FROM vtiger_potential p
    JOIN vtiger_crmentity ep ON ep.crmid = p.potentialid AND ep.deleted = 0
    JOIN vtiger_users u ON u.id = ep.smownerid AND u.user_name = 'isabella'
    JOIN vtiger_contactdetails cd ON cd.contactid = p.contact_id
    JOIN vtiger_contactscf cscf ON cscf.contactid = cd.contactid
    WHERE (cscf.cf_origem_lead_id IS NULL OR cscf.cf_origem_lead_id = '')
    GROUP BY cd.contactid
");

$sem_lead = [];
while ($row = $r->fetch_assoc()) {
    $sem_lead[] = $row;
}
echo "Contatos sem rastreamento: " . count($sem_lead) . "\n\n";

$por_phone = 0;
$por_nome  = 0;
$sem_match = 0;
$updates   = [];

foreach ($sem_lead as $c) {
    $matched_lead = null;
    $metodo = '';

    // Tentativa 1: ultimos 8 digitos do telefone
    if (!empty($c['phone'])) {
        $phone_clean = preg_replace('/[^0-9]/', '', $c['phone']);
        $phone8 = substr($phone_clean, -8);
        if (strlen($phone8) >= 7) {
            $safe = $db->real_escape_string($phone8);
            $rr = $db->query("
                SELECT ld.leadid FROM vtiger_leadaddress la
                JOIN vtiger_leaddetails ld ON ld.leadid = la.leadaddressid
                JOIN vtiger_crmentity el ON el.crmid = ld.leadid AND el.setype = 'Leads'
                WHERE la.phone != ''
                AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(la.phone,'+55',''),'+',''),' ',''),'-',''), 8) = '$safe'
                LIMIT 1
            ");
            if ($row2 = $rr->fetch_assoc()) {
                $matched_lead = $row2['leadid'];
                $metodo = 'telefone';
                $por_phone++;
            }
        }
    }

    // Tentativa 2: firstname + lastname
    if (!$matched_lead) {
        $fn = $db->real_escape_string(trim($c['firstname']));
        $ln = $db->real_escape_string(trim($c['lastname']));
        if ($fn && $ln) {
            $rr = $db->query("
                SELECT ld.leadid FROM vtiger_leaddetails ld
                JOIN vtiger_crmentity el ON el.crmid = ld.leadid AND el.setype = 'Leads'
                WHERE ld.firstname = '$fn' AND ld.lastname = '$ln'
                LIMIT 1
            ");
            if ($row2 = $rr->fetch_assoc()) {
                $matched_lead = $row2['leadid'];
                $metodo = 'nome';
                $por_nome++;
            }
        }
    }

    // Tentativa 3: apenas lastname (sobrenome composto)
    if (!$matched_lead) {
        $ln = $db->real_escape_string(trim($c['lastname']));
        if ($ln) {
            $rr = $db->query("
                SELECT ld.leadid FROM vtiger_leaddetails ld
                JOIN vtiger_crmentity el ON el.crmid = ld.leadid AND el.setype = 'Leads'
                WHERE ld.lastname = '$ln'
                LIMIT 1
            ");
            if ($row2 = $rr->fetch_assoc()) {
                $matched_lead = $row2['leadid'];
                $metodo = 'sobrenome';
                $por_nome++;
            }
        }
    }

    if ($matched_lead) {
        $updates[] = ['contactid' => $c['contactid'], 'leadid' => $matched_lead];
        echo "MATCH [{$metodo}]: {$c['firstname']} {$c['lastname']} -> lead {$matched_lead}\n";
    } else {
        $sem_match++;
        echo "SEM MATCH: {$c['firstname']} {$c['lastname']} | phone: {$c['phone']}\n";
    }
}

echo "\n=== RESUMO ===\n";
echo "Por telefone: $por_phone\n";
echo "Por nome:     $por_nome\n";
echo "Sem match:    $sem_match\n";
echo "Total match:  " . count($updates) . "\n";

if ($aplicar && count($updates) > 0) {
    echo "\nAplicando updates...\n";
    $ok = 0;
    foreach ($updates as $upd) {
        $db->query("UPDATE vtiger_contactscf SET cf_origem_lead_id = '{$upd['leadid']}' WHERE contactid = {$upd['contactid']}");
        if ($db->affected_rows > 0) $ok++;
    }
    echo "Atualizados: $ok contatos\n";
} elseif ($aplicar) {
    echo "\nNenhum update para aplicar.\n";
} else {
    echo "\nRode com --aplicar para salvar as alteracoes.\n";
}
