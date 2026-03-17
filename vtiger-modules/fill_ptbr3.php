<?php
/**
 * Versão corrigida: só adiciona entradas de UMA linha do en_us.
 * Remove seção de fallback anterior antes de refazer.
 */

function getSingleLineEntries($file) {
    $entries = [];
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        // Linha válida: começa com 'KEY' => 'VALUE', (abre e fecha na mesma linha)
        if (preg_match("/^\s*'([^']+)'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'\s*,?\s*$/", $line, $m)) {
            $entries[$m[1]] = $line;
        }
        // Também aceita "KEY" => "VALUE" com aspas duplas
        if (preg_match('/^\s*"([^"]+)"\s*=>\s*"((?:[^"\\\\]|\\\\.)*)"\s*,?\s*$/', $line, $m)) {
            $entries[$m[1]] = $line;
        }
    }
    return $entries;
}

function getKeys($file) {
    $keys = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
        if (preg_match("/^\s*'([^']+)'\s*=>/", $line, $m)) $keys[$m[1]] = true;
        if (preg_match('/^\s*"([^"]+)"\s*=>/', $line, $m)) $keys[$m[1]] = true;
    }
    return $keys;
}

function removeFallbackSection($content) {
    // Remove tudo a partir do marcador de fallback
    $pos = strpos($content, '// --- Fallback en_us ---');
    if ($pos !== false) {
        // Volta até o \n anterior ao comentário
        $content = rtrim(substr($content, 0, $pos));
        // Garante que o array fecha corretamente
        if (!str_ends_with($content, ');')) {
            $content .= "\n);";
        }
    }
    return $content;
}

$enDir = '/var/www/html/languages/en_us';
$ptDir = '/var/www/html/languages/pt_br';
$total = 0;

foreach (glob("$enDir/*.php") as $enFile) {
    $base   = basename($enFile);
    $ptFile = "$ptDir/$base";
    if (!file_exists($ptFile)) continue;

    // Remove fallback anterior se existir
    $ptContent = file_get_contents($ptFile);
    $ptContent  = removeFallbackSection($ptContent);

    // Verifica sintaxe do pt_br limpo antes de prosseguir
    file_put_contents($ptFile, $ptContent);

    // Pega keys atuais do pt_br (sem o fallback)
    $ptKeys    = getKeys($ptFile);
    $enEntries = getSingleLineEntries($enFile);
    $enKeys    = getKeys($enFile);

    $missing = array_diff_key($enKeys, $ptKeys);
    // Só adiciona os que têm entrada de linha única no en_us
    $toAdd = array_intersect_key($missing, $enEntries);

    if (empty($toAdd)) continue;

    $block = "\n// --- Fallback en_us ---\n";
    foreach (array_keys($toAdd) as $key) {
        $line = trim($enEntries[$key]);
        // Garante que termina com vírgula
        if (!str_ends_with($line, ',')) $line .= ',';
        $block .= $line . "\n";
    }

    // Insere antes do fechamento );
    $pos = strrpos($ptContent, ');');
    if ($pos !== false) {
        $ptContent = substr($ptContent, 0, $pos) . $block . ");";
    } else {
        $ptContent .= $block;
    }

    file_put_contents($ptFile, $ptContent);

    $count = count($toAdd);
    $total += $count;
    echo "  $base: +$count\n";
}

echo "\nTotal: $total chaves adicionadas\n";
