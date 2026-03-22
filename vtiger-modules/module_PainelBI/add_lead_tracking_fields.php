<?php
/**
 * add_lead_tracking_fields.php
 *
 * Cria campos de rastreamento lead→contato:
 *   - Leads:    cf_lead_origem_id  (oculto, preenchido com o leadid)
 *   - Contatos: cf_origem_lead_id  (oculto, recebe o leadid na conversão)
 *
 * Executar: docker exec crmbot74-vtiger-1 php /var/www/html/modules/PainelBI/add_lead_tracking_fields.php
 */

$db = new mysqli('vtiger-db', 'vtiger', 'FyyU8aWNoMCHI908izpnmTHfIBU', 'vtiger');
$db->set_charset('utf8');

if ($db->connect_error) {
    die("Erro conexao: " . $db->connect_error . "\n");
}

echo "=== RASTREAMENTO LEAD -> CONTATO ===\n\n";

// ── 1. Pegar tabids ────────────────────────────────────────────────────────
$r = $db->query("SELECT tabid, name FROM vtiger_tab WHERE name IN ('Leads','Contacts')");
$tabids = [];
while ($row = $r->fetch_assoc()) {
    $tabids[$row['name']] = (int)$row['tabid'];
}
echo "TabID Leads: {$tabids['Leads']} | TabID Contacts: {$tabids['Contacts']}\n";

// ── 2. Verificar se campos já existem ──────────────────────────────────────
$r = $db->query("SELECT fieldid, tabid, fieldname, columnname FROM vtiger_field
    WHERE fieldname IN ('cf_lead_origem_id','cf_origem_lead_id')");
$existentes = [];
while ($row = $r->fetch_assoc()) {
    $existentes[$row['fieldname']] = $row;
}

// ── 3. Criar campo no módulo LEADS ─────────────────────────────────────────
$leadFieldId = null;
if (isset($existentes['cf_lead_origem_id'])) {
    $leadFieldId = (int)$existentes['cf_lead_origem_id']['fieldid'];
    echo "[LEADS] Campo cf_lead_origem_id ja existe (fieldid={$leadFieldId})\n";
} else {
    // Verificar/criar coluna na tabela vtiger_leadscf
    $r = $db->query("SHOW COLUMNS FROM vtiger_leadscf LIKE 'cf_lead_origem_id'");
    if ($r->num_rows === 0) {
        $db->query("ALTER TABLE vtiger_leadscf ADD COLUMN cf_lead_origem_id VARCHAR(255) DEFAULT NULL");
        echo "[LEADS] Coluna cf_lead_origem_id criada em vtiger_leadscf\n";
    }

    // Pegar próximo fieldid disponível
    $r = $db->query("SELECT MAX(fieldid) AS max_id FROM vtiger_field");
    $row = $r->fetch_assoc();
    $nextFieldId = (int)$row['max_id'] + 1;

    // Pegar blockid do bloco "Lead Information" do módulo Leads
    $r = $db->query("SELECT blockid FROM vtiger_blocks WHERE tabid={$tabids['Leads']} ORDER BY sequence ASC LIMIT 1");
    $blockRow = $r->fetch_assoc();
    $leadsBlockId = (int)$blockRow['blockid'];

    // Pegar max sequence do bloco
    $r = $db->query("SELECT MAX(sequence) AS max_seq FROM vtiger_field WHERE tabid={$tabids['Leads']} AND block={$leadsBlockId}");
    $seqRow = $r->fetch_assoc();
    $nextSeq = (int)$seqRow['max_seq'] + 1;

    $db->query("INSERT INTO vtiger_field
        (tabid, columnname, tablename, generatedtype, uitype, fieldname, fieldlabel,
         readonly, presence, selected, maximumlength, sequence, block, displaytype,
         typeofdata, quickcreate, quicksequence, info_type, massupdate, helpinfo, summaryfield)
        VALUES
        ({$tabids['Leads']}, 'cf_lead_origem_id', 'vtiger_leadscf', 0, 1, 'cf_lead_origem_id', 'ID Lead Origem',
         0, 2, 0, 255, {$nextSeq}, {$leadsBlockId}, 3,
         'V~O', 0, 0, 'BAS', 0, '', 0)");

    $leadFieldId = $db->insert_id;
    echo "[LEADS] Campo cf_lead_origem_id criado (fieldid={$leadFieldId})\n";
}

// ── 4. Criar campo no módulo CONTACTS ──────────────────────────────────────
$contactFieldId = null;
if (isset($existentes['cf_origem_lead_id'])) {
    $contactFieldId = (int)$existentes['cf_origem_lead_id']['fieldid'];
    echo "[CONTACTS] Campo cf_origem_lead_id ja existe (fieldid={$contactFieldId})\n";
} else {
    // Verificar/criar coluna na tabela vtiger_contactscf
    $r = $db->query("SHOW COLUMNS FROM vtiger_contactscf LIKE 'cf_origem_lead_id'");
    if ($r->num_rows === 0) {
        $db->query("ALTER TABLE vtiger_contactscf ADD COLUMN cf_origem_lead_id VARCHAR(255) DEFAULT NULL");
        echo "[CONTACTS] Coluna cf_origem_lead_id criada em vtiger_contactscf\n";
    }

    $r = $db->query("SELECT MAX(fieldid) AS max_id FROM vtiger_field");
    $row = $r->fetch_assoc();
    $nextFieldId2 = (int)$row['max_id'] + 1;

    $r = $db->query("SELECT blockid FROM vtiger_blocks WHERE tabid={$tabids['Contacts']} ORDER BY sequence ASC LIMIT 1");
    $blockRow2 = $r->fetch_assoc();
    $contactsBlockId = (int)$blockRow2['blockid'];

    $r = $db->query("SELECT MAX(sequence) AS max_seq FROM vtiger_field WHERE tabid={$tabids['Contacts']} AND block={$contactsBlockId}");
    $seqRow2 = $r->fetch_assoc();
    $nextSeq2 = (int)$seqRow2['max_seq'] + 1;

    $db->query("INSERT INTO vtiger_field
        (tabid, columnname, tablename, generatedtype, uitype, fieldname, fieldlabel,
         readonly, presence, selected, maximumlength, sequence, block, displaytype,
         typeofdata, quickcreate, quicksequence, info_type, massupdate, helpinfo, summaryfield)
        VALUES
        ({$tabids['Contacts']}, 'cf_origem_lead_id', 'vtiger_contactscf', 0, 1, 'cf_origem_lead_id', 'ID Lead Origem',
         0, 2, 0, 255, {$nextSeq2}, {$contactsBlockId}, 3,
         'V~O', 0, 0, 'BAS', 0, '', 0)");

    $contactFieldId = $db->insert_id;
    echo "[CONTACTS] Campo cf_origem_lead_id criado (fieldid={$contactFieldId})\n";
}

// ── 5. Configurar mapeamento vtiger_convertleadmapping ─────────────────────
$r = $db->query("SELECT id FROM vtiger_convertleadmapping WHERE leadfieldid={$leadFieldId}");
if ($r->num_rows > 0) {
    $db->query("UPDATE vtiger_convertleadmapping SET contactfieldid={$contactFieldId} WHERE leadfieldid={$leadFieldId}");
    echo "[MAPPING] Mapeamento atualizado: lead.{$leadFieldId} -> contact.{$contactFieldId}\n";
} else {
    $db->query("INSERT INTO vtiger_convertleadmapping (leadfieldid, contactfieldid, accountfieldid, potentialfieldid)
        VALUES ({$leadFieldId}, {$contactFieldId}, NULL, NULL)");
    echo "[MAPPING] Mapeamento criado: lead.{$leadFieldId} -> contact.{$contactFieldId}\n";
}

// ── 6. Preencher TODOS os leads existentes com seu próprio ID ──────────────
echo "\n[LEADS] Preenchendo todos os leads existentes com cf_lead_origem_id...\n";

// Garantir que todos os leads tenham entrada na vtiger_leadscf
$db->query("INSERT IGNORE INTO vtiger_leadscf (leadid)
    SELECT ld.leadid FROM vtiger_leaddetails ld");

// Preencher o campo com o ID do lead
$db->query("UPDATE vtiger_leadscf lscf
    JOIN vtiger_leaddetails ld ON ld.leadid = lscf.leadid
    SET lscf.cf_lead_origem_id = ld.leadid
    WHERE lscf.cf_lead_origem_id IS NULL OR lscf.cf_lead_origem_id = ''");

$count = $db->affected_rows;
echo "[LEADS] {$count} leads preenchidos com seu ID\n";

// ── 7. Preencher retroativamente contatos com lead correspondente (phone) ──
echo "\n[RETRO] Preenchendo contatos que tem lead correspondente por telefone...\n";

// Garantir entradas na vtiger_contactscf
$db->query("INSERT IGNORE INTO vtiger_contactscf (contactid)
    SELECT cd.contactid FROM vtiger_contactdetails cd");

// Match: ultimos 11 digitos do telefone
$db->query("UPDATE vtiger_contactscf cscf
    JOIN vtiger_contactdetails cd ON cd.contactid = cscf.contactid
    JOIN vtiger_crmentity ec ON ec.crmid = cd.contactid AND ec.deleted = 0
    JOIN vtiger_leadaddress la ON
        la.phone != '' AND cd.phone != '' AND
        RIGHT(REPLACE(REPLACE(REPLACE(la.phone,'+55',''),' ',''),'-',''), 11) =
        RIGHT(REPLACE(REPLACE(REPLACE(cd.phone,'+55',''),' ',''),'-',''), 11)
    JOIN vtiger_leaddetails ld ON ld.leadid = la.leadaddressid
    JOIN vtiger_crmentity el ON el.crmid = ld.leadid AND el.setype = 'Leads'
    SET cscf.cf_origem_lead_id = ld.leadid
    WHERE (cscf.cf_origem_lead_id IS NULL OR cscf.cf_origem_lead_id = '')");

$retroCount = $db->affected_rows;
echo "[RETRO] {$retroCount} contatos preenchidos retroativamente\n";

// ── 8. Preencher contatos de leads com converted=1 (link direto) ───────────
echo "\n[RETRO] Preenchendo contatos via converted=1...\n";

// Para leads com converted=1, o contato correspondente tem o mesmo phone
$db->query("UPDATE vtiger_contactscf cscf
    JOIN vtiger_contactdetails cd ON cd.contactid = cscf.contactid
    JOIN vtiger_crmentity ec ON ec.crmid = cd.contactid AND ec.deleted = 0
    JOIN vtiger_leadaddress la ON
        la.phone != '' AND cd.phone != '' AND
        RIGHT(REPLACE(REPLACE(REPLACE(la.phone,'+55',''),' ',''),'-',''), 11) =
        RIGHT(REPLACE(REPLACE(REPLACE(cd.phone,'+55',''),' ',''),'-',''), 11)
    JOIN vtiger_leaddetails ld ON ld.leadid = la.leadaddressid AND ld.converted = 1
    JOIN vtiger_crmentity el ON el.crmid = ld.leadid AND el.setype = 'Leads'
    SET cscf.cf_origem_lead_id = ld.leadid
    WHERE (cscf.cf_origem_lead_id IS NULL OR cscf.cf_origem_lead_id = '')");

$convCount = $db->affected_rows;
echo "[RETRO] {$convCount} contatos adicionais via converted=1\n";

// ── 9. Relatório final ─────────────────────────────────────────────────────
echo "\n=== RESUMO ===\n";

$r = $db->query("SELECT COUNT(*) AS c FROM vtiger_leadscf WHERE cf_lead_origem_id IS NOT NULL AND cf_lead_origem_id != ''");
$row = $r->fetch_assoc();
echo "Leads com ID preenchido: {$row['c']}\n";

$r = $db->query("SELECT COUNT(*) AS c FROM vtiger_contactscf WHERE cf_origem_lead_id IS NOT NULL AND cf_origem_lead_id != ''");
$row = $r->fetch_assoc();
echo "Contatos rastreados (vieram de lead): {$row['c']}\n";

$r = $db->query("SELECT u.user_name, COUNT(DISTINCT p.potentialid) AS oportunidades_de_leads
    FROM vtiger_potential p
    JOIN vtiger_crmentity ep ON ep.crmid = p.potentialid AND ep.deleted = 0
    JOIN vtiger_users u ON u.id = ep.smownerid
    JOIN vtiger_contactdetails cd ON cd.contactid = p.contact_id
    JOIN vtiger_contactscf cscf ON cscf.contactid = cd.contactid
    WHERE cscf.cf_origem_lead_id IS NOT NULL AND cscf.cf_origem_lead_id != ''
    GROUP BY u.user_name ORDER BY oportunidades_de_leads DESC");
echo "\nOportunidades rastreadas por atendente:\n";
while ($row = $r->fetch_assoc()) {
    echo "  {$row['user_name']}: {$row['oportunidades_de_leads']}\n";
}

echo "\nDone. Campos criados e dados preenchidos.\n";
echo "Limpe o cache: rm -rf /var/www/html/cache/modules/ /var/www/html/cache/Smarty/templates_c/*\n";
