<?php

declare(strict_types=1);

namespace Hawarei\Sift\Tests\Unit;

use Hawarei\Sift\Support\TableParser;
use Hawarei\Sift\Tests\TestCase;

/**
 * Regression tests for behaviors commonly seen when copying tables
 * from browsers and spreadsheets (CRLF, leading empty columns, NBSP, etc.).
 */
class CopyPasteBehaviorTest extends TestCase
{
    protected TableParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TableParser;
    }

    // -------------------------------------------------------------------------
    //  Line ending normalization
    // -------------------------------------------------------------------------

    public function test_crlf_from_windows_browser_copy(): void
    {
        // Simulates text copied from a browser on Windows
        $text = "Status\tDomain Name\tService Number\r\nOpen\texample.com\t12345\r\nClosed\ttest.com\t67890";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Open', $result[0]['Status']);
        $this->assertEquals('example.com', $result[0]['Domain Name']);
        $this->assertEquals('12345', $result[0]['Service Number']);
        $this->assertEquals('Closed', $result[1]['Status']);
    }

    public function test_lf_from_linux_copy(): void
    {
        $text = "Status\tDomain Name\nOpen\texample.com\nClosed\ttest.com";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Open', $result[0]['Status']);
    }

    // -------------------------------------------------------------------------
    //  Tab-delimited splitting without whole-row trim
    //  CRITICAL: Never trim the whole line before splitting
    // -------------------------------------------------------------------------

    public function test_tab_splitting_preserves_structure(): void
    {
        // 4 columns: empty, empty, Status, Domain Name
        $text = "\t\tStatus\tDomain Name\n\t\tOpen\texample.com";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        // All 4 columns must be present
        $this->assertCount(4, $result[0]);
        $this->assertEquals('Open', $result[0]['Status']);
        $this->assertEquals('example.com', $result[0]['Domain Name']);
    }

    public function test_no_whole_row_trim_destroys_leading_tabs(): void
    {
        // If the parser incorrectly trims the whole row before splitting,
        // the leading empty columns would be lost.
        $text = "\tA\tB\n\t1\t2";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('_column_1', $result[0]);
        $this->assertNull($result[0]['_column_1']);
        $this->assertEquals('1', $result[0]['A']);
        $this->assertEquals('2', $result[0]['B']);
    }

    // -------------------------------------------------------------------------
    //  Leading empty columns / header offset
    // -------------------------------------------------------------------------

    public function test_leading_empty_columns_with_multiple_headers(): void
    {
        // Simulates browser copy where selection starts before the table
        $text = "\t\tStatus\tDomain Name\tService Number\n\t\tOpen\texample.com\t12345\n\t\tClosed\ttest.com\t67890";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        // Leading empty columns should be represented
        $this->assertNull($result[0]['_column_1']);
        $this->assertNull($result[0]['_column_2']);
        $this->assertEquals('Open', $result[0]['Status']);
        $this->assertEquals('example.com', $result[0]['Domain Name']);
        $this->assertEquals('12345', $result[0]['Service Number']);
    }

    // -------------------------------------------------------------------------
    //  NBSP cleaning
    // -------------------------------------------------------------------------

    public function test_nbsp_around_headers_normalized(): void
    {
        $nbsp = "\xC2\xA0";
        $text = "{$nbsp}Status{$nbsp}\t{$nbsp}Domain Name{$nbsp}\n{$nbsp}Open{$nbsp}\texample.com";
        $result = $this->parser->parse($text);

        $this->assertCount(1, $result);
        // Headers should be cleaned of NBSP
        $this->assertArrayHasKey('Status', $result[0]);
        $this->assertArrayHasKey('Domain Name', $result[0]);
        $this->assertEquals('Open', $result[0]['Status']);
    }

    public function test_nbsp_around_cell_values_normalized(): void
    {
        $nbsp = "\xC2\xA0";
        $text = "Name\tCity\nHawarei\t{$nbsp}Cairo{$nbsp}";
        $result = $this->parser->parse($text);

        $this->assertEquals('Cairo', $result[0]['City']);
    }

    // -------------------------------------------------------------------------
    //  Empty cells → null
    //  Trim, then if empty string → null
    // -------------------------------------------------------------------------

    public function test_empty_tab_cell_becomes_null(): void
    {
        $text = "A\tB\tC\n1\t\t3";
        $result = $this->parser->parse($text);

        $this->assertEquals('1', $result[0]['A']);
        $this->assertNull($result[0]['B']);
        $this->assertEquals('3', $result[0]['C']);
    }

    public function test_multiple_empty_cells(): void
    {
        $text = "A\tB\tC\tD\n\t\t\tonly";
        $result = $this->parser->parse($text);

        $this->assertNull($result[0]['A']);
        $this->assertNull($result[0]['B']);
        $this->assertNull($result[0]['C']);
        $this->assertEquals('only', $result[0]['D']);
    }

    public function test_nbsp_only_cell_becomes_null(): void
    {
        $nbsp = "\xC2\xA0";
        $text = "A\tB\n{$nbsp}\tvalue";
        $result = $this->parser->parse($text);

        $this->assertNull($result[0]['A']);
        $this->assertEquals('value', $result[0]['B']);
    }

    // -------------------------------------------------------------------------
    //  Missing trailing cells → null
    // -------------------------------------------------------------------------

    public function test_missing_trailing_cells_become_null(): void
    {
        $text = "A\tB\tC\tD\n1\t2";
        $result = $this->parser->parse($text);

        $this->assertEquals('1', $result[0]['A']);
        $this->assertEquals('2', $result[0]['B']);
        $this->assertNull($result[0]['C']);
        $this->assertNull($result[0]['D']);
    }

    public function test_row_with_only_first_cell(): void
    {
        $text = "Name\tCountry\tScore\nHawarei";
        $result = $this->parser->parse($text);

        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertNull($result[0]['Country']);
        $this->assertNull($result[0]['Score']);
    }

    // -------------------------------------------------------------------------
    //  Unknown headers remain available
    // -------------------------------------------------------------------------

    public function test_unknown_headers_preserved_with_values(): void
    {
        $text = "Status\tCustom Field\tDomain Name\nOpen\tSomeValue\texample.com";
        $result = $this->parser->parse($text);

        $this->assertEquals('SomeValue', $result[0]['Custom Field']);
    }

    // -------------------------------------------------------------------------
    //  Header whitespace normalization
    // -------------------------------------------------------------------------

    public function test_header_whitespace_normalized(): void
    {
        $text = "  Status  \t  Domain Name  \n  Open  \texample.com";
        $result = $this->parser->parse($text);

        $this->assertArrayHasKey('Status', $result[0]);
        $this->assertArrayHasKey('Domain Name', $result[0]);
    }

    public function test_header_internal_spaces_preserved(): void
    {
        $text = "Customer Name\tArea Code\nHawarei\t123";
        $result = $this->parser->parse($text);

        $this->assertArrayHasKey('Customer Name', $result[0]);
        $this->assertArrayHasKey('Area Code', $result[0]);
    }

    // -------------------------------------------------------------------------
    //  Data containing spaces is preserved
    // -------------------------------------------------------------------------

    public function test_cell_values_with_spaces_preserved(): void
    {
        $text = "Name\tAddress\nHawarei\tNew Cairo, Building 5";
        $result = $this->parser->parse($text);

        $this->assertEquals('New Cairo, Building 5', $result[0]['Address']);
    }

    // -------------------------------------------------------------------------
    //  Empty lines between data rows are skipped
    // -------------------------------------------------------------------------

    public function test_empty_lines_between_data_skipped(): void
    {
        $text = "Name\tScore\n\nHawarei\t95\n\n\nSara\t88\n";
        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('Hawarei', $result[0]['Name']);
        $this->assertEquals('Sara', $result[1]['Name']);
    }

    // -------------------------------------------------------------------------
    //  Real-world multi-column scenario
    //  Simulating a typical table with many columns
    // -------------------------------------------------------------------------

    public function test_realistic_multi_column_table(): void
    {
        $headers = "Service Order ID\tCustomer Order ID\tCustomer Name\tService Number\tStatus\tDomain Name";
        $row1 = "SO-001\tCO-001\tHawarei\t12345\tOpen\texample.com";
        $row2 = "SO-002\tCO-002\tSara\t67890\tClosed\ttest.com";
        $text = "{$headers}\n{$row1}\n{$row2}";

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        $this->assertEquals('SO-001', $result[0]['Service Order ID']);
        $this->assertEquals('CO-001', $result[0]['Customer Order ID']);
        $this->assertEquals('Hawarei', $result[0]['Customer Name']);
        $this->assertEquals('12345', $result[0]['Service Number']);
        $this->assertEquals('Open', $result[0]['Status']);
        $this->assertEquals('example.com', $result[0]['Domain Name']);
    }

    public function test_realistic_table_with_missing_and_empty_cells(): void
    {
        $headers = "Service Order ID\tCustomer Name\tService Number\tStatus\tEmail";
        $row1 = "SO-001\tHawarei\t12345\tOpen\tm@example.com";
        $row2 = "SO-002\t\t67890\tClosed";  // missing Customer Name, missing Email
        $text = "{$headers}\n{$row1}\n{$row2}";

        $result = $this->parser->parse($text);

        $this->assertCount(2, $result);
        // Row 1: all present
        $this->assertEquals('m@example.com', $result[0]['Email']);
        // Row 2: empty Customer Name, missing Email
        $this->assertNull($result[1]['Customer Name']);
        $this->assertEquals('Closed', $result[1]['Status']);
        $this->assertNull($result[1]['Email']);
    }
}
