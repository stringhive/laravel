<?php

declare(strict_types=1);

use Stringhive\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

// ---------------------------------------------------------------------------
// Filesystem helpers used by LangLoader and Command tests
// ---------------------------------------------------------------------------

function makeTempLangDir(): string
{
    $dir = sys_get_temp_dir().'/stringhive-test-'.uniqid();
    mkdir($dir, 0755, true);

    return $dir;
}

function removeTempDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
    }

    rmdir($dir);
}
