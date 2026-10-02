# Laravel Sift

> Parse messy, human-copied tabular text into predictable Laravel Collections.

Sift turns messy, human-copied tabular text into predictable Laravel Collections. It safely handles copy-paste artifacts from browsers and spreadsheets (like tabs, spaces, pipes, newlines, and non-breaking spaces), letting your application focus on data interpretation rather than text cleanup.

## Installation

```bash
composer require hawarei/sift
```

## Basic Usage

The primary API is `Sift::table()`. Pass it a string containing tabular text:

```php
use Hawarei\Sift\Facades\Sift;

$text = "Name\tCountry\tScore\nHawarei\tEgypt\t95\nSara\tJordan\t88";

$result = Sift::table($text);
```

By default, Sift assumes the first row contains headers. It returns a Laravel Collection where each row is an associative array keyed by the header names:

```php
// $result:
collect([
    [
        'Name' => 'Hawarei',
        'Country' => 'Egypt',
        'Score' => '95',
    ],
    [
        'Name' => 'Sara',
        'Country' => 'Jordan',
        'Score' => '88',
    ],
]);
```

### Headerless Mode

If your table doesn't have headers, pass `header: false` as the second argument. The result will contain indexed arrays:

```php
$result = Sift::table($text, header: false);

// collect([
//     ['Hawarei', 'Egypt', '95'],
//     ['Sara', 'Jordan', '88'],
// ]);
```

## Supported Delimiters

Sift uses deterministic structural detection to figure out how columns are separated. It prioritizes delimiters in this order:

1. **Tab (`\t`)**: The most common format when copying from Excel, Google Sheets, or HTML tables.
2. **Pipe (`|`)**: Common in Markdown tables.
3. **Whitespace**: If no tabs or pipes are found, columns separated by 2 or more consecutive spaces are parsed correctly.

## Edge Case Handling

Sift is designed to be forgiving of malformed rows without silently destroying data:

- **Empty Cells**: Automatically converted to `null`.
- **Missing Cells**: If a row has fewer columns than the header, the missing values are set to `null`.
- **Extra Cells**: If a row has more columns than the header, extra values are preserved with keys like `_extra_1`, `_extra_2`, etc.
- **Duplicate Headers**: Sift prevents data overwriting by suffixing duplicates: `Name`, `Name_2`, `Name_3`, etc.
- **Empty Headers**: Handled gracefully using generated keys like `_column_1`, `_column_2`.
- **Leading Empty Columns**: Correctly aligns if the first few columns in the header are empty (often happens when selecting text in a browser).
- **Whitespace & Line Endings**: Normalizes CRLF to LF, and safely trims non-breaking spaces (`U+00A0`) without destroying tab structure.
- **Unicode Support**: Fully supports UTF-8, Arabic text, and emojis.

## Limitations

- **No Semantic Guessing**: Sift does not try to guess what your columns mean. Unknown headers are preserved exactly as they are.
- **No Type Casting**: All cell values remain strings (or `null`). If you need integers, booleans, or Dates, your application should cast them after parsing.
- **No I/O**: Sift does not fetch webpages, extract tables from HTML, or query databases. You must provide the raw text.

## Testing

```bash
composer test
```
