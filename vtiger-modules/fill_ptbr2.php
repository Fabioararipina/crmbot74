<?php
// Lê chaves de um arquivo de linguagem vTiger como texto (sem include)
function getKeys($file) {
    $keys = [];
    $content = file_get_contents($file);
    // Extrai linhas com 'KEY' =>
    preg_match_all("/^\s*'([^']+)'\s*=>/m", $content, $matches);
    foreach ($matches[1] as $key) {
        $keys[$key] = true;
    }
    return $keys;
}

// Extrai todas as linhas de entrada do array de um arquivo
function getEntryLines($file) {
    $entries = [];
    $content = file_get_contents($file);
    // Cada linha que tem 'KEY' => 'VALUE' (possivelmente multilinha, mas geralmente 1 linha)
    preg_match_all("/^\s*'([^']+)'\s*=>.*$/m", $content, $matches, PREG_SET_ORDER);
    foreach ($matches as $m) {
        $entries[$m[1]] = trim($m[0]);
    }
    return $entries;
}

$enDir = '/var/www/html/languages/en_us';
$ptDir = '/var/www/html/languages/pt_br';
$total = 0;

foreach (glob("$enDir/*.php") as $enFile) {
    $base   = basename($enFile);
    $ptFile = "$ptDir/$base";
    if (!file_exists($ptFile)) continue;

    $enKeys     = getKeys($enFile);
    $ptKeys     = getKeys($ptFile);
    $enEntries  = getEntryLines($enFile);

    $missing = array_diff_key($enKeys, $ptKeys);
    if (empty($missing)) continue;

    // Monta bloco a adicionar
    $block = "\n// --- Fallback en_us ---\n";
    foreach (array_keys($missing) as $key) {
        if (isset($enEntries[$key])) {
            $block .= $enEntries[$key] . "\n";
        }
    }

    // Insere antes do fechamento ); do array
    $ptContent = file_get_contents($ptFile);
    $pos = strrpos($ptContent, ');');
    if ($pos !== false) {
        $ptContent = substr($ptContent, 0, $pos) . $block . ");";
    } else {
        $ptContent .= $block;
    }
    file_put_contents($ptFile, $ptContent);

    $count = count($missing);
    $total += $count;
    echo "  $base: +$count\n";
}

echo "\nTotal: $total chaves adicionadas\n";
