<?php

declare(strict_types=1);

namespace Hawarei\Sift\Tests\Unit;

use Hawarei\Sift\Support\TableParser;
use Hawarei\Sift\Tests\TestCase;

/**
 * General table parsing tests and data integrity edge cases.
 * Covers specification sections 29 and Phase 6 edge cases.
 */
class GeneralTableTest extends TestCase
{
    protected TableParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TableParser;
    }

    // -------------------------------------------------------------------------
    //  BOM Handling
    // -------------------------------------------------------------------------

    public function test_utf8_bom_stripped(): void
    {
        $bom = "\xEF\xBB\xBF";
        $text = "{$bom}Name\tScore\nHawarei\t95";
        $result = $this->parser->parse($text);

        $this->assertArrayHasKey('Name', $result[0]);
        $this->assertEquals('Hawarei', $result[0]['Name']);
    }

    // -------------------------------------------------------------------------
    //  Headerless Mode — Various Cases
    // -------------------------------------------------------------------------

    public function test_headerless_single_row(): void
    {
        $text = "Hawarei\tEgypt\t95";
        $result = $this->parser->parse($text, header: false);

        $this->assertCount(1, $result);
        $this->assertEquals(['Hawarei', 'Egypt', '95'], $result[0]);
    }

    public function test_headerless_with_empty_cells(): void
    {
        $text = "Hawarei\t\t95\n\tJordan\t";
        $result = $this->parser->parse($text, header: false);

        $this->assertCount(2, $result);
        $this->assertEquals(['Hawarei', null, '95'], $result[0]);
        $this->assertEquals([null, 'Jordan', null], $result[1]);
    }

    public function test_headerless_preserves_all_rows(): void
    {
        $text = "A\nB\nC";
        $result = $this->parser->parse($text, header: false);

        $this->assertCount(3, $result);
        $this->assertEquals(['A'], $result[0]);
        $this->assertEquals(['B'], $result[1]);
        $this->assertEquals(['C'], $result[2]);
    }

    // -------------------------------------------------------------------------
    //  Pipe Tables — Edge Cases
    // -------------------------------------------------------------------------

    public function test_pipe_table_with_empty_cells(): void
    {
        $text = "| Name | Score |\n|------|-------|\n| Hawarei |       |\n|      | 95    |";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertNull($result[0]['Score']);
        $this->assertNull($result[1]['Name']);
        $this->assertEquals('95', $result[1]['Score']);
    }

    public function test_pipe_table_without_separator(): void
    {
        $text = "| Name | Country |\n| Hawarei | Egypt   |";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
    }

    public function test_pipe_with_colon_aligned_separator(): void
    {
        $text = "| Name | Score |\n|:-----|------:|\n| Hawarei | 95    |";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('95', $result[0]['Score']);
    }

    // -------------------------------------------------------------------------
    //  Whitespace-Aligned Tables — Edge Cases
    // -------------------------------------------------------------------------

    public function test_whitespace_table_with_multi_word_values(): void
    {
        $text = "City            Country\nNew York        United States\nSan Francisco   United States";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('New York', $result[0]['City']);
        $this->assertEquals('United States', $result[0]['Country']);
    }

    // -------------------------------------------------------------------------
    //  Single Column
    // -------------------------------------------------------------------------

    public function test_single_column_headerless(): void
    {
        $text = "Hawarei\nSara\nAli";
        $result = $this->parser->parse($text, header: false);

        $this->assertCount(3, $result);
        $this->assertEquals(['Hawarei'], $result[0]);
        $this->assertEquals(['Sara'], $result[1]);
        $this->assertEquals(['Ali'], $result[2]);
    }

    // -------------------------------------------------------------------------
    //  Edge Cases — Data Integrity (Phase 6)
    // -------------------------------------------------------------------------

    public function test_duplicate_headers_never_overwrite(): void
    {
        $text = "A\tA\tA\tA\n1\t2\t3\t4";
        $result = $this->parser->parse($text);

        $row = $result[0];
        $this->assertEquals('1', $row['A']);
        $this->assertEquals('2', $row['A_2']);
        $this->assertEquals('3', $row['A_3']);
        $this->assertEquals('4', $row['A_4']);
        // Verify all four values are present
        $this->assertCount(4, $row);
    }

    public function test_empty_headers_never_discard_columns(): void
    {
        // All headers are empty
        $text = "\t\t\n1\t2\t3";
        $result = $this->parser->parse($text);

        $row = $result[0];
        $this->assertEquals('1', $row['_column_1']);
        $this->assertEquals('2', $row['_column_2']);
        $this->assertEquals('3', $row['_column_3']);
    }

    public function test_extra_cells_never_discarded(): void
    {
        $text = "A\n1\t2\t3";
        $result = $this->parser->parse($text);

        $row = $result[0];
        $this->assertEquals('1', $row['A']);
        $this->assertEquals('2', $row['_extra_1']);
        $this->assertEquals('3', $row['_extra_2']);
    }

    public function test_missing_cells_filled_deterministically(): void
    {
        $text = "A\tB\tC\tD\tE\n1";
        $result = $this->parser->parse($text);

        $row = $result[0];
        $this->assertEquals('1', $row['A']);
        $this->assertNull($row['B']);
        $this->assertNull($row['C']);
        $this->assertNull($row['D']);
        $this->assertNull($row['E']);
    }

    public function test_mixed_missing_and_extra_across_rows(): void
    {
        $text = "A\tB\tC\n1\n1\t2\t3\t4\t5";
        $result = $this->parser->parse($text);

        // Row 1: only A, missing B and C
        $this->assertEquals('1', $result[0]['A']);
        $this->assertNull($result[0]['B']);
        $this->assertNull($result[0]['C']);
        $this->assertArrayNotHasKey('_extra_1', $result[0]);

        // Row 2: has extras
        $this->assertEquals('1', $result[1]['A']);
        $this->assertEquals('2', $result[1]['B']);
        $this->assertEquals('3', $result[1]['C']);
        $this->assertEquals('4', $result[1]['_extra_1']);
        $this->assertEquals('5', $result[1]['_extra_2']);
    }

    // -------------------------------------------------------------------------
    //  Unicode
    // -------------------------------------------------------------------------

    public function test_arabic_headers_and_values(): void
    {
        $text = "الاسم\tالدولة\tالنتيجة\nمينا\tمصر\t95\nسارة\tالأردن\t88";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('مينا', $result[0]['الاسم']);
        $this->assertEquals('مصر', $result[0]['الدولة']);
        $this->assertEquals('95', $result[0]['النتيجة']);
        $this->assertEquals('سارة', $result[1]['الاسم']);
        $this->assertEquals('الأردن', $result[1]['الدولة']);
    }

    public function test_mixed_script_values(): void
    {
        $text = "Name\tCity\nمينا\tCairo\nSara\tعمّان";
        $result = $this->parser->parse($text);

        $this->assertEquals('مينا', $result[0]['Name']);
        $this->assertEquals('Cairo', $result[0]['City']);
        $this->assertEquals('Sara', $result[1]['Name']);
        $this->assertEquals('عمّان', $result[1]['City']);
    }

    public function test_emoji_in_values(): void
    {
        $text = "Status\tName\n✅\tHawarei\n❌\tSara";
        $result = $this->parser->parse($text);

        $this->assertEquals('✅', $result[0]['Status']);
        $this->assertEquals('❌', $result[1]['Status']);
    }

    // -------------------------------------------------------------------------
    //  Whitespace-Only Input Variations
    // -------------------------------------------------------------------------

    public function test_tabs_only_input(): void
    {
        $text = "\t\t\t";
        $result = $this->parser->parse($text);
        // A line of only tabs contains structural cells — but all empty → header-only with no data
        // In headerless mode, it should be one row of nulls
        $resultHeaderless = $this->parser->parse($text, header: false);
        $this->assertCount(1, $resultHeaderless);
        $this->assertEquals([null, null, null, null], $resultHeaderless[0]);
    }

    public function test_newlines_only_input(): void
    {
        $text = "\n\n\n";
        $result = $this->parser->parse($text);
        $this->assertTrue($result->isEmpty());
    }

    // -------------------------------------------------------------------------
    //  Trailing Newlines
    // -------------------------------------------------------------------------

    public function test_trailing_newline_does_not_create_extra_row(): void
    {
        $text = "Name\tScore\nHawarei\t95\n";
        $result = $this->parser->parse($text);
        $this->assertCount(1, $result);
    }

    public function test_multiple_trailing_newlines(): void
    {
        $text = "Name\nHawarei\n\n\n\n";
        $result = $this->parser->parse($text);
        $this->assertCount(1, $result);
    }

    // -------------------------------------------------------------------------
    //  Determinism Verification
    // -------------------------------------------------------------------------

    public function test_same_input_always_same_output(): void
    {
        $text = "A\tB\tC\n1\t2\t3\n4\t5\t6";

        $result1 = $this->parser->parse($text);
        $result2 = $this->parser->parse($text);
        $result3 = $this->parser->parse($text);

        $this->assertEquals($result1->toArray(), $result2->toArray());
        $this->assertEquals($result2->toArray(), $result3->toArray());
    }
}
