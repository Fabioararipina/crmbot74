<?php
/*
 * crmbot74 — Override de Leads_Detail_View
 * Expõe métodos AJAX para os painéis do Prime Atende e Bot74.
 * Montado em: /var/www/html/modules/Leads/views/Detail.php
 */

require_once 'modules/Accounts/views/Detail.php';

class Leads_Detail_View extends Accounts_Detail_View {

    function __construct() {
        parent::__construct();
        $this->exposeMethod('showRecentComments');
        $this->exposeMethod('showChildComments');
        $this->exposeMethod('showPrimeAtendeWidget');
        $this->exposeMethod('showBot74Widget');
    }

    // ── Painel Prime Atende ────────────────────────────────────────────────────

    public function showPrimeAtendeWidget(Vtiger_Request $request) {
        $recordId    = $request->get('record');
        $recordModel = Vtiger_Record_Model::getInstanceById($recordId, 'Leads');
        $telefone    = $recordModel->get('phone');

        if (!$telefone) {
            echo $this->_renderBox('Prime Atende', '<p style="color:#999">Sem telefone cadastrado neste lead.</p>');
            return;
        }

        $data = $this->_consultarConector($telefone);

        if ($data === null) {
            echo $this->_renderBox('Prime Atende', '<p style="color:#c00">Conector indisponível (porta 3010).</p>');
            return;
        }

        $contato = $data['primeAtende']['contato'] ?? null;

        if (!$contato) {
            echo $this->_renderBox('Prime Atende', '<p style="color:#999">Contato não encontrado no Prime Atende.</p>');
            return;
        }

        $html  = '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $html .= $this->_tr('ID Prime', $contato['id'] ?? '-');
        $html .= $this->_tr('Nome',     $contato['name'] ?? '-');
        $html .= $this->_tr('Número',   $contato['number'] ?? $contato['phoneNumber'] ?? '-');
        $html .= '</table>';

        echo $this->_renderBox('Prime Atende', $html);
    }

    // ── Painel Bot74 ───────────────────────────────────────────────────────────

    public function showBot74Widget(Vtiger_Request $request) {
        $recordId    = $request->get('record');
        $recordModel = Vtiger_Record_Model::getInstanceById($recordId, 'Leads');
        $telefone    = $recordModel->get('phone');

        if (!$telefone) {
            echo $this->_renderBox('Bot74', '<p style="color:#999">Sem telefone cadastrado neste lead.</p>');
            return;
        }

        $data = $this->_consultarConector($telefone);

        if ($data === null) {
            echo $this->_renderBox('Bot74', '<p style="color:#c00">Conector indisponível (porta 3010).</p>');
            return;
        }

        $leads = $data['bot74']['leads'] ?? [];

        if (empty($leads)) {
            echo $this->_renderBox('Bot74', '<p style="color:#999">Lead não está em nenhuma campanha Bot74.</p>');
            return;
        }

        $html  = '<table style="width:100%;border-collapse:collapse;font-size:13px">';
        $html .= '<tr style="background:#f0f0f0">';
        $html .= '<th style="padding:5px 8px;text-align:left">Campanha</th>';
        $html .= '<th style="padding:5px 8px;text-align:left">Status</th>';
        $html .= '<th style="padding:5px 8px;text-align:left">Desde</th>';
        $html .= '</tr>';
        foreach ($leads as $lead) {
            $html .= '<tr>';
            $html .= '<td style="padding:5px 8px">' . htmlspecialchars($lead['campanhaNome'] ?? '-') . '</td>';
            $html .= '<td style="padding:5px 8px">' . htmlspecialchars($lead['status'] ?? '-') . '</td>';
            $html .= '<td style="padding:5px 8px">' . htmlspecialchars(substr($lead['createdAt'] ?? '-', 0, 10)) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        echo $this->_renderBox('Bot74', $html);
    }

    // ── Helpers privados ───────────────────────────────────────────────────────

    /** Chama GET /vtiger/status-lead no Conector e retorna array ou null em caso de falha. */
    private function _consultarConector($telefone) {
        $url = 'http://host.docker.internal:3010/vtiger/status-lead?telefone=' . urlencode($telefone);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['x-conector-key: ' . (getenv('CONECTOR_API_KEY') ?: 'COLOQUE_NO_ENV') . ''],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            return null;
        }
        return json_decode($response, true);
    }

    /** Envolve o conteúdo HTML num bloco com borda leve. */
    private function _renderBox($titulo, $conteudo) {
        return '<div style="padding:10px 0">' . $conteudo . '</div>';
    }

    /** Linha de tabela chave/valor. */
    private function _tr($chave, $valor) {
        return '<tr>'
            . '<td style="color:#888;padding:4px 8px;width:110px;white-space:nowrap">' . htmlspecialchars($chave) . '</td>'
            . '<td style="padding:4px 8px">' . htmlspecialchars($valor) . '</td>'
            . '</tr>';
    }
}
