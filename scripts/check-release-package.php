<?php

$root = dirname(__DIR__);
$allowedEnvTemplates = ['.env.example', '.env.production.example'];
$forbiddenSegments = ['node_modules', 'vendor', 'dist', 'build'];
$forbiddenBasenames = ['.phpunit.result.cache'];
$files = [];
exec('git ls-files 2>/dev/null', $files, $status);

if ($status !== 0 || $files === []) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
        if (str_starts_with($relative, '.git/')) {
            continue;
        }
        $files[] = $relative;
    }
}

$runtimeIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($runtimeIterator as $item) {
    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
    if (str_starts_with($relative, '.git/')) {
        continue;
    }
    if (in_array(basename($relative), $forbiddenBasenames, true)) {
        $files[] = $relative;
    }
}
$files = array_values(array_unique($files));

$failures = [];
foreach ($files as $file) {
    $parts = explode('/', str_replace('\\', '/', $file));
    if (array_intersect($parts, $forbiddenSegments)) {
        $failures[] = $file.': release package không được chứa dependency/build output.';
    }
    $basename = basename($file);
    if (in_array($basename, $forbiddenBasenames, true)) {
        $failures[] = $file.': release package không được chứa test/runtime cache.';
    }
    if (str_starts_with($basename, '.env') && ! in_array($basename, $allowedEnvTemplates, true)) {
        $failures[] = $file.': release package không được chứa file môi trường thật.';
    }
}

foreach (['composer.json', 'app', 'routes', 'docs/canonical'] as $required) {
    if (! file_exists($root.'/'.$required)) {
        $failures[] = $required.': thiếu release root contract.';
    }
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures).PHP_EOL);
    exit(1);
}

echo 'Release package guard passed: env, dependency output and root layout are clean.'.PHP_EOL;
