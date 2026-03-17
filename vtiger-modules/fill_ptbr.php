<?php
/**
 * Preenche arquivos pt_br com chaves faltantes do en_us como fallback.
 * Executa dentro do container: php /tmp/fill_ptbr.php
 */

$langDir = '/var/www/html/languages';
$enDir   = "$langDir/en_us";
$ptDir   = "$langDir/pt_br";

$files = glob("$enDir/*.php");
$totalAdded = 0;

foreach ($files as $enFile) {
    $basename = basename($enFile);
    $ptFile   = "$ptDir/$basename";

    // Carrega strings en_us
    $languageStrings = [];
    include $enFile;
    $enStrings = $languageStrings;
    if (empty($enStrings)) continue;

    // Carrega strings pt_br existentes
    $languageStrings = [];
    if (file_exists($ptFile)) {
        include $ptFile;
    }
    $ptStrings = $languageStrings;

    // Encontra chaves faltantes
    $missing = array_diff_key($enStrings, $ptStrings);
    if (empty($missing)) continue;

    // Lê o conteúdo atual do pt_br
    $content = file_exists($ptFile) ? file_get_contents($ptFile) : "<?php\n\$languageStrings = array(\n);\n";

    // Remove o fechamento do array ); e do PHP ?>
    $content = rtrim($content);
    if (substr($content, -2) === '?>') {
        $content = rtrim(substr($content, 0, -2));
    }
    // Remove ); final do array se existir
    if (substr($content, -2) === ');') {
        $content = rtrim(substr($content, 0, -2));
    }

    // Adiciona as chaves faltantes dentro do array
    $additions = "\n// --- Fallback en_us (sem tradução pt_br) ---\n";
    foreach ($missing as $key => $value) {
        $key   = str_replace("'", "\\'", $key);
        $value = str_replace("'", "\\'", $value);
        $additions .= "\t'$key' => '$value',\n";
    }
    $additions .= ");";

    file_put_contents($ptFile, $content . $additions);

    $count = count($missing);
    $totalAdded += $count;
    echo "  $basename: +$count chaves\n";
}

echo "\nTotal adicionado: $totalAdded chaves\n";
