<?php

declare(strict_types=1);

namespace Hawarei\Sift\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Support\Collection<int, mixed> table(string $text, bool $header = true)
 *
 * @see \Hawarei\Sift\Sift
 */
class Sift extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hawarei\Sift\Sift::class;
    }
}
