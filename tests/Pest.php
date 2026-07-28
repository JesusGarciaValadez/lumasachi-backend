<?php

declare(strict_types=1);

use Tests\TestCase;

$tiaPagesDirectory = getenv('TIA_VITE_PAGES_DIR');

if (is_string($tiaPagesDirectory) && $tiaPagesDirectory !== '') {
    $tiaPagesPath = str_starts_with($tiaPagesDirectory, DIRECTORY_SEPARATOR)
        ? $tiaPagesDirectory
        : dirname(__DIR__) . DIRECTORY_SEPARATOR . $tiaPagesDirectory;

    if (!is_dir($tiaPagesPath)) {
        putenv('TIA_VITE_PAGES_DIR=');
    }
}

$pagesDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js';
$upperCasePagesDirectory = $pagesDirectory . DIRECTORY_SEPARATOR . 'Pages';
$lowerCasePagesDirectory = $pagesDirectory . DIRECTORY_SEPARATOR . 'pages';
$hasCaseInsensitivePagesMount = is_dir($upperCasePagesDirectory)
    && is_dir($lowerCasePagesDirectory)
    && fileinode($upperCasePagesDirectory) === fileinode($lowerCasePagesDirectory);

if (!$hasCaseInsensitivePagesMount) {
    pest()
        ->tia()
        ->always()
        ->locally();
}

pest()
    ->extend(TestCase::class)
    ->in('Unit', 'Feature');
