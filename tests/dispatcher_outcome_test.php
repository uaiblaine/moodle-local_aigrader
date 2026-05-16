<?php
// This file is part of Moodle - https://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for dispatcher::decide_outcome() — the "all-unsupported → needs_review"
 * branch in particular.
 *
 * Motivating bug (v1.0.1 pilot): a student submitted only a .pdf
 * (research-grade LaTeX report on MACE potentials for SiO2 simulation, GT
 * 10/10). The dispatcher produced a non-empty $parts array but every entry
 * was a FORMAT_UNSUPPORTED placeholder. The LLM dutifully evaluated the
 * placeholders and emitted a 0/10. The fix is to detect that exact shape
 * before calling the LLM and instead mark the submission for manual review.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

/**
 * Tests for the dispatcher's all-unsupported-formats decision branch.
 *
 * @covers \local_aigrader\extractor\dispatcher::decide_outcome
 * @covers \local_aigrader\extractor\extraction_result::needs_review
 * @covers \local_aigrader\extractor\extraction_result::is_needs_review
 */
final class dispatcher_outcome_test extends \advanced_testcase {
    /**
     * Empty submission (no files of any kind) → error, never needs_review.
     */
    public function test_empty_submission_is_error(): void {
        $out = dispatcher::decide_outcome(42, [], [], []);

        $this->assertFalse($out->is_ok());
        $this->assertFalse($out->is_needs_review());
        $this->assertNotNull($out->error);
        $this->assertStringContainsString('No supported content', $out->error);
    }

    /**
     * The headline regression: PDF-only submission. Every part is an
     * UNSUPPORTED placeholder, the only format is UNSUPPORTED. Must come back
     * as needs_review, NOT a low-grade success.
     */
    public function test_only_pdf_attachment_returns_needs_review(): void {
        $parts = [
            '=== research.pdf (UNSUPPORTED: pdf) ===',
            '[This file could not be processed by AI Grader Pro. ' .
            'The teacher will need to review it manually.]',
            '',
        ];
        $warnings = ['research.pdf unsupported: pdf'];
        $formats  = [extraction_result::FORMAT_UNSUPPORTED];

        $out = dispatcher::decide_outcome(99, $parts, $warnings, $formats);

        $this->assertTrue($out->is_needs_review(), 'PDF-only submission must be flagged for manual review');
        $this->assertFalse($out->is_ok(), 'PDF-only must not look like a normal extraction');
        $this->assertStringContainsString(
            'unparseable',
            $out->error,
            'message must clearly flag unparseable input'
        );
        $this->assertStringContainsString(
            'research.pdf',
            $out->error,
            'reason text must surface the offending file name'
        );
        $this->assertContains('research.pdf unsupported: pdf', $out->warnings);
    }

    /**
     * Multiple unsupported files (a .pdf and a .pptx). All of them surface in
     * the reason text, not just the first.
     */
    public function test_multiple_unsupported_files_all_listed(): void {
        $parts = [
            '=== a.pdf (UNSUPPORTED: pdf) ===', '[placeholder]', '',
            '=== b.pptx (UNSUPPORTED: pptx) ===', '[placeholder]', '',
        ];
        $warnings = [
            'a.pdf unsupported: pdf',
            'b.pptx unsupported: pptx',
        ];
        $formats = [
            extraction_result::FORMAT_UNSUPPORTED,
            extraction_result::FORMAT_UNSUPPORTED,
        ];

        $out = dispatcher::decide_outcome(101, $parts, $warnings, $formats);

        $this->assertTrue($out->is_needs_review());
        $this->assertStringContainsString('a.pdf', $out->error);
        $this->assertStringContainsString('b.pptx', $out->error);
    }

    /**
     * A submission with one supported file (.ipynb) + one unsupported file
     * (.pdf): grade with what we have, surface the .pdf as a warning, do NOT
     * route to manual review. The teacher might still want to review the PDF
     * but the AI proposal is fair on the .ipynb alone.
     */
    public function test_mixed_supported_and_unsupported_is_graded_with_warning(): void {
        $parts = [
            '=== notebook.ipynb ===',
            'cell 1 code etc',
            '',
            '=== ref.pdf (UNSUPPORTED: pdf) ===',
            '[placeholder]',
            '',
        ];
        $warnings = ['ref.pdf unsupported: pdf'];
        $formats  = [
            extraction_result::FORMAT_IPYNB,
            extraction_result::FORMAT_UNSUPPORTED,
        ];

        $out = dispatcher::decide_outcome(102, $parts, $warnings, $formats);

        $this->assertTrue($out->is_ok(), 'should still grade with the notebook content');
        $this->assertFalse($out->is_needs_review());
        $this->assertSame(
            extraction_result::FORMAT_MIXED,
            $out->format,
            'multi-format submission should report FORMAT_MIXED'
        );
        $this->assertContains(
            'ref.pdf unsupported: pdf',
            $out->warnings,
            'skipped file must still be visible in the warnings list'
        );
    }

    /**
     * A normal all-supported submission: single .ipynb → FORMAT_IPYNB, ok,
     * no warnings.
     */
    public function test_single_supported_file_returns_format_specific(): void {
        $parts    = ['=== notebook.ipynb ===', 'cell contents', ''];
        $warnings = [];
        $formats  = [extraction_result::FORMAT_IPYNB];

        $out = dispatcher::decide_outcome(103, $parts, $warnings, $formats);

        $this->assertTrue($out->is_ok());
        $this->assertSame(extraction_result::FORMAT_IPYNB, $out->format);
        $this->assertEmpty($out->warnings);
    }

    /**
     * Two supported files of the same type collapse to that single format.
     */
    public function test_two_same_format_files_keeps_specific_format(): void {
        $parts    = ['a', 'b', 'c', 'd'];
        $warnings = [];
        $formats  = [
            extraction_result::FORMAT_IPYNB,
            extraction_result::FORMAT_IPYNB,
        ];

        $out = dispatcher::decide_outcome(104, $parts, $warnings, $formats);

        $this->assertTrue($out->is_ok());
        $this->assertSame(extraction_result::FORMAT_IPYNB, $out->format);
    }

    /**
     * Two different supported formats → FORMAT_MIXED.
     */
    public function test_two_distinct_supported_formats_is_mixed(): void {
        $parts    = ['=== a.docx ===', 'doc text', '', '=== b.ipynb ===', 'nb text', ''];
        $warnings = [];
        $formats  = [
            extraction_result::FORMAT_DOCX,
            extraction_result::FORMAT_IPYNB,
        ];

        $out = dispatcher::decide_outcome(105, $parts, $warnings, $formats);

        $this->assertTrue($out->is_ok());
        $this->assertSame(extraction_result::FORMAT_MIXED, $out->format);
    }

    /**
     * extraction_result::needs_review() factory: $needs_review flag set,
     * is_needs_review() returns true, is_ok() returns false. Independent
     * smoke test for the value object.
     */
    public function test_needs_review_factory_sets_flag(): void {
        $r = extraction_result::needs_review('reason text', ['warning1', 'warning2']);

        $this->assertTrue($r->is_needs_review());
        $this->assertFalse($r->is_ok());
        $this->assertSame('reason text', $r->error);
        $this->assertSame(['warning1', 'warning2'], $r->warnings);
        $this->assertSame(extraction_result::FORMAT_UNSUPPORTED, $r->format);
    }
}
