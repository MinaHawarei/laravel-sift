<?php

declare(strict_types=1);

namespace Hawarei\Sift\Tests\Unit;

use Hawarei\Sift\Facades\Sift;
use Hawarei\Sift\Support\TableParser;
use Hawarei\Sift\Tests\TestCase;

class TableParserTest extends TestCase
{
    protected TableParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TableParser;
    }

    // -------------------------------------------------------------------------
    //  Instantiation & Empty Input
    // -------------------------------------------------------------------------

    public function test_it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(TableParser::class, $this->parser);
    }

    public function test_empty_input_returns_empty_collection(): void
    {
        $result = $this->parser->parse('');
        $this->assertTrue($result->isEmpty());
    }

    public function test_whitespace_only_input_returns_empty_collection(): void
    {
        $result = $this->parser->parse("   \n  \n   ");
        $this->assertTrue($result->isEmpty());
    }

    // -------------------------------------------------------------------------
    //  Facade Access (Phase 2)
    // -------------------------------------------------------------------------

    public function test_facade_table_method_works(): void
    {
        $text = "Name\tCountry\nHawarei\tEgypt";
        $result = Sift::table($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
    }

    public function test_facade_headerless_mode(): void
    {
        $text = "Hawarei\tEgypt\nSara\tJordan";
        $result = Sift::table($text, header: false);

        $this->assertCount(2, $result);
        $this->assertEquals(['Hawarei', 'Egypt'], $result[0]);
        $this->assertEquals(['Sara', 'Jordan'], $result[1]);
    }

    public function test_service_direct_usage(): void
    {
        $sift = app(\Hawarei\Sift\Sift::class);
        $text = "Name\tScore\nHawarei\t95";
        $result = $sift->table($text);

        $this->assertCount(1, $result);
        $this->assertEquals(['Name' => 'Hawarei', 'Score' => '95'], $result[0]);
    }

    // -------------------------------------------------------------------------
    //  Basic Tab Tables (Phase 3)
    // -------------------------------------------------------------------------

    public function test_basic_tab_table_with_header(): void
    {
        $text = "Name\tCountry\tScore\nHawarei\tEgypt\t95\nSara\tJordan\t88";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals([
            'Name' => 'Hawarei',
            'Country' => 'Egypt',
            'Score' => '95',
        ], $result[0]);
        $this->assertEquals([
            'Name' => 'Sara',
            'Country' => 'Jordan',
            'Score' => '88',
        ], $result[1]);
    }

    public function test_headerless_tab_table(): void
    {
        $text = "Hawarei\tEgypt\t95\nSara\tJordan\t88";
        $result = $this->parser->parse($text, header: false);

        $this->assertCount(2, $result);
        $this->assertEquals(['Hawarei', 'Egypt', '95'], $result[0]);
        $this->assertEquals(['Sara', 'Jordan', '88'], $result[1]);
    }

    // -------------------------------------------------------------------------
    //  Line Ending Normalization
    // -------------------------------------------------------------------------

    public function test_crlf_normalization(): void
    {
        $text = "Name\tCountry\r\nHawarei\tEgypt\r\nSara\tJordan";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Sara', $result[1]['Name']);
    }

    public function test_standalone_cr_normalization(): void
    {
        $text = "Name\tCountry\rHawarei\tEgypt\rSara\tJordan";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Egypt', $result[0]['Country']);
    }

    public function test_mixed_line_endings(): void
    {
        $text = "Name\tCountry\r\nHawarei\tEgypt\nSara\tJordan\rAli\tIraq";
        $result = $this->parser->parse($text);

        $this->assertCount(3, $result);
    }

    // -------------------------------------------------------------------------
    //  Empty Lines
    // -------------------------------------------------------------------------

    public function test_blank_lines_are_skipped(): void
    {
        $text = "Name\tCountry\n\nHawarei\tEgypt\n\nSara\tJordan\n";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Sara', $result[1]['Name']);
    }

    // -------------------------------------------------------------------------
    //  Cell Normalization
    // -------------------------------------------------------------------------

    public function test_cell_whitespace_trimming(): void
    {
        $text = "Name\tCountry\n  Hawarei  \t  Egypt  ";
        $result = $this->parser->parse($text);

        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
    }

    public function test_nbsp_normalization(): void
    {
        $nbsp = "\xC2\xA0";
        $text = "Name\tCountry\n{$nbsp}Hawarei{$nbsp}\t{$nbsp}Egypt{$nbsp}";
        $result = $this->parser->parse($text);

        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
    }

    public function test_empty_cell_becomes_null(): void
    {
        $text = "Name\tCountry\tScore\nHawarei\t\t95";
        $result = $this->parser->parse($text);

        $this->assertNull($result[0]['Country']);
        $this->assertEquals('95', $result[0]['Score']);
    }

    public function test_values_remain_strings(): void
    {
        $text = "Value\n95\ntrue\n2026-09-26";
        $result = $this->parser->parse($text);

        $this->assertSame('95', $result[0]['Value']);
        $this->assertSame('true', $result[1]['Value']);
        $this->assertSame('2026-09-26', $result[2]['Value']);
    }

    // -------------------------------------------------------------------------
    //  Pipe Tables
    // -------------------------------------------------------------------------

    public function test_pipe_table_with_outer_pipes(): void
    {
        $text = "| Name | Country | Score |\n|------|---------|-------|\n| Hawarei | Egypt   | 95    |";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
        $this->assertEquals('95', $result[0]['Score']);
    }

    public function test_pipe_table_without_outer_pipes(): void
    {
        $text = "Name | Country | Score\nHawarei | Egypt   | 95";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
        $this->assertEquals('95', $result[0]['Score']);
    }

    public function test_markdown_separator_row_excluded(): void
    {
        $text = "| Name | Score |\n|------|-------|\n| Hawarei | 95    |";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        // The separator must not appear as a data row
        $this->assertEquals('Hawarei', $result[0]['Name']);
    }

    // -------------------------------------------------------------------------
    //  Whitespace-Aligned Tables
    // -------------------------------------------------------------------------

    public function test_whitespace_aligned_table(): void
    {
        $text = "Name        Country        Score\nHawarei        Egypt          95\nSara        Jordan         88";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
        $this->assertEquals('95', $result[0]['Score']);
    }

    public function test_single_space_not_treated_as_delimiter(): void
    {
        // Tab-delimited — single spaces within values must be preserved
        $text = "City\tState\nNew York\tNew York";
        $result = $this->parser->parse($text);

        $this->assertEquals('New York', $result[0]['City']);
        $this->assertEquals('New York', $result[0]['State']);
    }

    // -------------------------------------------------------------------------
    //  Missing Cells
    // -------------------------------------------------------------------------

    public function test_missing_trailing_cells_become_null(): void
    {
        $text = "Name\tCountry\tScore\nHawarei\tEgypt\nSara\tJordan\t88";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
        $this->assertNull($result[0]['Score']);
        $this->assertEquals('88', $result[1]['Score']);
    }

    // -------------------------------------------------------------------------
    //  Extra Cells
    // -------------------------------------------------------------------------

    public function test_extra_cells_are_preserved(): void
    {
        $text = "Name\tCountry\nHawarei\tEgypt\t95\tExtra";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Egypt', $result[0]['Country']);
        $this->assertEquals('95', $result[0]['_extra_1']);
        $this->assertEquals('Extra', $result[0]['_extra_2']);
    }

    // -------------------------------------------------------------------------
    //  Duplicate Headers
    // -------------------------------------------------------------------------

    public function test_duplicate_headers_get_suffixed(): void
    {
        $text = "Name\tName\tEmail\nHawarei\tHawarei 2\tm@example.com";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Hawarei 2', $result[0]['Name_2']);
        $this->assertEquals('m@example.com', $result[0]['Email']);
    }

    public function test_triple_duplicate_headers(): void
    {
        $text = "X\tX\tX\n1\t2\t3";
        $result = $this->parser->parse($text);

        $this->assertEquals('1', $result[0]['X']);
        $this->assertEquals('2', $result[0]['X_2']);
        $this->assertEquals('3', $result[0]['X_3']);
    }

    // -------------------------------------------------------------------------
    //  Empty Headers
    // -------------------------------------------------------------------------

    public function test_empty_header_gets_generated_key(): void
    {
        $text = "Name\t\tEmail\nHawarei\tvalue\tm@example.com";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('value', $result[0]['_column_2']);
        $this->assertEquals('m@example.com', $result[0]['Email']);
    }

    // -------------------------------------------------------------------------
    //  Leading Empty Columns
    // -------------------------------------------------------------------------

    public function test_leading_empty_columns_preserved(): void
    {
        $text = "\t\tStatus\tDomain Name\n\t\tOpen\texample.com";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        // Leading empty columns get _column_N keys
        $this->assertNull($result[0]['_column_1']);
        $this->assertNull($result[0]['_column_2']);
        $this->assertEquals('Open', $result[0]['Status']);
        $this->assertEquals('example.com', $result[0]['Domain Name']);
    }

    // -------------------------------------------------------------------------
    //  Single Row / Single Column
    // -------------------------------------------------------------------------

    public function test_single_column_table(): void
    {
        $text = "Name\nHawarei\nSara";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals(['Name' => 'Hawarei'], $result[0]);
        $this->assertEquals(['Name' => 'Sara'], $result[1]);
    }

    public function test_header_only_returns_empty_collection(): void
    {
        $text = "Name\tCountry\tScore";
        $result = $this->parser->parse($text);

        $this->assertTrue($result->isEmpty());
    }

    // -------------------------------------------------------------------------
    //  Unknown Headers
    // -------------------------------------------------------------------------

    public function test_unknown_headers_are_preserved(): void
    {
        $text = "Name\tCompletely New Column\tScore\nHawarei\tSomething\t95";
        $result = $this->parser->parse($text);

        $this->assertEquals('Something', $result[0]['Completely New Column']);
    }

    // -------------------------------------------------------------------------
    //  Unicode / Arabic
    // -------------------------------------------------------------------------

    public function test_arabic_text(): void
    {
        $text = "الاسم\tالدولة\tالنتيجة\nمينا\tمصر\t95";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('مينا', $result[0]['الاسم']);
        $this->assertEquals('مصر', $result[0]['الدولة']);
        $this->assertEquals('95', $result[0]['النتيجة']);
    }
}
