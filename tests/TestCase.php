<?php

declare(strict_types=1);

namespace Hawarei\Sift\Tests;

use Hawarei\Sift\Facades\Sift;
use Hawarei\Sift\SiftServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SiftServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Sift' => Sift::class,
        ];
    }
}
