<?php

$root = dirname(__DIR__);
$manifest = json_decode(file_get_contents($root.'/docs/release/recovery-baseline.json'), true, flags: JSON_THROW_ON_ERROR);
$failures = [];
foreach ($manifest['critical_files'] as $file) {
    if (! file_exists($root.'/'.$file)) {
        $failures[] = 'Thiếu recovery owner: '.$file;
    }
}
foreach (['database/database.sqlite', '.phpunit.result.cache'] as $file) {
    if (file_exists($root.'/'.$file)) {
        $failures[] = 'Runtime artifact không được nằm trong source package: '.$file;
    }
}
$media = glob($root.'/storage/framework/testing/disks/public/marketplace/products/*') ?: [];
foreach ($media as $file) {
    if (is_file($file) && basename($file) !== '.gitignore') {
        $failures[] = 'Generated test media không được nằm trong source package: '.substr($file, strlen($root) + 1);
    }
}
$hash = hash_file('sha256', $root.'/resources/contracts/marketplace-contract.json');
if ($hash !== $manifest['contract_sha256']) {
    $failures[] = 'Contract hash lệch recovery baseline: '.$hash;
}
if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}
echo 'Recovery baseline guard passed: API capabilities and source package are intact.'.PHP_EOL;
