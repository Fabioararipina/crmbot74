<?php
/**
 * VTWebhookTask — Tarefa de Workflow para chamar URL HTTP externa
 * crmbot74 © 2026 — Essencial Saúde
 *
 * Instalação: copiar para modules/com_vtiger_workflow/tasks/VTWebhookTask.inc
 */

class VTWebhookTask extends VTTask {

    public $executeImmediately = true;

    public function getFieldNames() {
        return ['url', 'method', 'headers', 'body'];
    }

    public function doTask($entity) {
        $url     = $this->url;
        $method  = strtoupper($this->method ?: 'POST');
        $headers = $this->parseHeaders($this->headers ?: '');
        $body    = $this->resolveVariables($this->body ?: '{}', $entity);

        if (empty($url)) {
            return;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

        $curlHeaders = ['Content-Type: application/json'];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "$k: $v";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        // Log no error_log do Apache para diagnóstico
        $logLine = "[VTWebhookTask] $method $url → HTTP $httpCode";
        if ($error) $logLine .= " ERRO: $error";
        error_log($logLine);
    }

    /**
     * Substitui variáveis do tipo $(fieldname) pelo valor real do registro
     */
    private function resolveVariables($body, $entity) {
        return preg_replace_callback('/\$\(([^)]+)\)/', function($m) use ($entity) {
            $field = trim($m[1]);
            try {
                $val = $entity->get($field);
                return is_string($val) ? $val : json_encode($val);
            } catch (Exception $e) {
                return '';
            }
        }, $body);
    }

    private function parseHeaders($raw) {
        $headers = [];
        foreach (explode("\n", $raw) as $line) {
            $line = trim($line);
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $headers[trim($k)] = trim($v);
            }
        }
        return $headers;
    }
}
