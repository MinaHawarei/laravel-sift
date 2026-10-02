<?php

declare(strict_types=1);

namespace Hawarei\Sift;

use Illuminate\Support\ServiceProvider;
use Hawarei\Sift\Support\TableParser;

class SiftServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TableParser::class, function () {
            return new TableParser;
        });

        $this->app->singleton(Sift::class, function ($app) {
            return new Sift($app->make(TableParser::class));
        });
    }
}
