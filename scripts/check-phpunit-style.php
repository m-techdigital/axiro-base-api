<?php

declare(strict_types=1);

$root = dirname(__DIR__).'/tests';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$violations = [];

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $source = file_get_contents($file->getPathname()) ?: '';
    if (preg_match('/(^|\\s)(it|test|describe|expect)\\s*\\(/m', $source)) {
        $violations[] = str_replace(dirname(__DIR__).DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}

if ($violations !== []) {
    fwrite(STDERR, "Pest DSL is not allowed in this PHPUnit-only project:\n - ".implode("\n - ", $violations)."\n");
    exit(1);
}

fwrite(STDOUT, "PHPUnit-only test style: PASS\n");
