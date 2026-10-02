<?php

declare(strict_types=1);

namespace Hawarei\Sift\Support;

use Illuminate\Support\Collection;

class TableParser
{
    /**
     * The minimum number of consecutive spaces to treat as a whitespace delimiter.
     */
    private const WHITESPACE_THRESHOLD = 2;

    /**
     * Characters to trim from individual cell values.
     * Includes normal whitespace and non-breaking space (U+00A0 = \xC2\xA0 in UTF-8).
     */
    private const TRIM_CHARS = " \t\n\r\0\x0B\xC2\xA0";

    /**
     * Parse tabular text into a Collection.
     *
     * @param  string  $text  The raw tabular text.
     * @param  bool  $header  Whether the first row is a header row.
     * @return Collection<int, mixed>
     */
    public function parse(string $text, bool $header = true): Collection
    {
        $text = $this->normalizeInput($text);

        if ($text === '' || trim($text, " \n\r\0\x0B\xC2\xA0") === '') {
            return collect();
        }

        $lines = $this->extractLines($text);

        if (empty($lines)) {
            return collect();
        }

        $delimiter = $this->detectDelimiter($lines);
        $rows = $this->parseRows($lines, $delimiter);

        if (empty($rows)) {
            return collect();
        }

        if ($header) {
            return $this->buildHeaderResult($rows);
        }

        return collect($rows);
    }

    /**
     * Normalize line endings and strip BOM.
     */
    private function normalizeInput(string $text): string
    {
        // Strip UTF-8 BOM
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }

        // Normalize \r\n to \n, then standalone \r to \n
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        return $text;
    }

    /**
     * Split text into non-empty lines, filtering out blank lines and Markdown separators.
     *
     * @return string[]
     */
    private function extractLines(string $text): array
    {
        $lines = explode("\n", $text);
        $result = [];

        foreach ($lines as $line) {
            // Skip completely empty lines (but preserve lines with only tabs/delimiters)
            if (trim($line, self::TRIM_CHARS) === '' && ! str_contains($line, "\t")) {
                continue;
            }

            // Skip Markdown separator rows (e.g. |---|---|)
            if ($this->isMarkdownSeparator($line)) {
                continue;
            }

            $result[] = $line;
        }

        return $result;
    }

    /**
     * Determine if a line is a Markdown-style separator row.
     */
    private function isMarkdownSeparator(string $line): bool
    {
        $trimmed = trim($line);

        // Must contain at least one dash
        if (! str_contains($trimmed, '-')) {
            return false;
        }

        // A separator row consists of pipes, dashes, colons, and spaces only
        return (bool) preg_match('/^\|?[\s\-:|]+\|?$/', $trimmed);
    }

    /**
     * Detect the delimiter type from the input lines.
     *
     * Priority: tab > pipe > whitespace
     *
     * @param  string[]  $lines
     * @return string 'tab', 'pipe', or 'whitespace'
     */
    private function detectDelimiter(array $lines): string
    {
        $tabCount = 0;
        $pipeCount = 0;

        foreach ($lines as $line) {
            if (str_contains($line, "\t")) {
                $tabCount++;
            }
            if (str_contains($line, '|')) {
                $pipeCount++;
            }
        }

        // Tab takes priority — if any meaningful line has tabs
        if ($tabCount > 0) {
            return 'tab';
        }

        // Pipe — if any meaningful line has pipes
        if ($pipeCount > 0) {
            return 'pipe';
        }

        return 'whitespace';
    }

    /**
     * Parse lines into arrays of cell values.
     *
     * @param  string[]  $lines
     * @return array<int, array<int, string|null>>
     */
    private function parseRows(array $lines, string $delimiter): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $cells = match ($delimiter) {
                'tab' => $this->splitByTab($line),
                'pipe' => $this->splitByPipe($line),
                'whitespace' => $this->splitByWhitespace($line),
                default => throw new \InvalidArgumentException("Unknown delimiter: {$delimiter}"),
            };

            $normalizedCells = array_map([$this, 'normalizeCell'], $cells);
            $rows[] = $normalizedCells;
        }

        return $rows;
    }

    /**
     * Split a line by tabs.
     *
     * Critical: Do NOT trim the whole line before splitting.
     * Only rtrim newline characters to preserve meaningful tab structure.
     *
     * @return string[]
     */
    private function splitByTab(string $line): array
    {
        return explode("\t", $line);
    }

    /**
     * Split a line by pipe characters.
     *
     * Handles both `| col1 | col2 |` and `col1 | col2` formats.
     *
     * @return string[]
     */
    private function splitByPipe(string $line): array
    {
        $trimmed = trim($line);

        // Remove leading/trailing pipes if present
        if (str_starts_with($trimmed, '|')) {
            $trimmed = substr($trimmed, 1);
        }
        if (str_ends_with($trimmed, '|')) {
            $trimmed = substr($trimmed, 0, -1);
        }

        return explode('|', $trimmed);
    }

    /**
     * Split a line by whitespace runs (2+ consecutive spaces).
     *
     * @return string[]
     */
    private function splitByWhitespace(string $line): array
    {
        $pattern = '/\s{'.self::WHITESPACE_THRESHOLD.',}/';
        $parts = preg_split($pattern, trim($line));

        if ($parts === false || $parts === []) {
            return [$line];
        }

        return $parts;
    }

    /**
     * Normalize a single cell value.
     *
     * - Trims whitespace including NBSP
     * - Converts empty strings to null
     */
    private function normalizeCell(string $cell): ?string
    {
        $value = trim($cell, self::TRIM_CHARS);

        return $value === '' ? null : $value;
    }

    /**
     * Build the result collection with header-based associative arrays.
     *
     * Handles:
     * - Leading empty columns (header offset)
     * - Duplicate headers → suffixed with _2, _3, etc.
     * - Empty headers → _column_N
     * - Missing cells → null
     * - Extra cells → _extra_N
     *
     * @param  array<int, array<int, string|null>>  $rows
     * @return Collection<int, mixed>
     */
    private function buildHeaderResult(array $rows): Collection
    {
        if (count($rows) < 1) {
            return collect();
        }

        $headerRow = array_shift($rows);
        $headers = $this->normalizeHeaders($headerRow);

        $result = [];
        foreach ($rows as $row) {
            $record = [];

            // Map known headers
            foreach ($headers as $i => $headerName) {
                $record[$headerName] = $row[$i] ?? null;
            }

            // Handle extra cells beyond header count
            $headerCount = count($headers);
            $rowCount = count($row);
            if ($rowCount > $headerCount) {
                $extraIndex = 1;
                for ($i = $headerCount; $i < $rowCount; $i++) {
                    $record['_extra_'.$extraIndex] = $row[$i];
                    $extraIndex++;
                }
            }

            $result[] = $record;
        }

        return collect($result);
    }

    /**
     * Normalize header names.
     *
     * - Empty headers become _column_N (1-indexed by position in the header row)
     * - Duplicate headers get suffixed: Name, Name_2, Name_3, etc.
     * - Whitespace (including NBSP) is trimmed
     * - Internal spacing and casing are preserved
     *
     * @param  array<int, string|null>  $headerRow
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headerRow): array
    {
        $headers = [];
        $seen = [];

        foreach ($headerRow as $index => $cell) {
            $name = $cell !== null ? trim($cell, self::TRIM_CHARS) : '';

            if ($name === '') {
                $name = '_column_'.($index + 1);
            }

            // Handle duplicates
            if (isset($seen[$name])) {
                $seen[$name]++;
                $name = $name.'_'.$seen[$name];
            } else {
                $seen[$name] = 1;
            }

            $headers[$index] = $name;
        }

        return $headers;
    }
}
