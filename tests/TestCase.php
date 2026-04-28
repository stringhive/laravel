<?php

declare(strict_types=1);

namespace Stringhive\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Stringhive\StringhiveServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            StringhiveServiceProvider::class,
        ];
    }
}
