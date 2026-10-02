<?php

declare(strict_types=1);

namespace Hawarei\Sift;

use Illuminate\Support\Collection;
use Hawarei\Sift\Support\TableParser;

class Sift
{
    public function __construct(
        protected TableParser $tableParser,
    ) {}

    /**
     * Parse tabular text into a Collection of rows.
     *
     * @param  string  $text  The raw tabular text to parse.
     * @param  bool  $header  Whether the first row contains headers.
     * @return Collection<int, mixed>
     */
    public function table(string $text, bool $header = true): Collection
    {
        return $this->tableParser->parse($text, $header);
    }
}
