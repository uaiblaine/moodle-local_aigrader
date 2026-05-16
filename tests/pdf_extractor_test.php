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
 * Tests for pdf_extractor — the wrapper around the bundled smalot/pdfparser.
 *
 * Test PDFs are generated on the fly via Moodle's TCPDF wrapper so the
 * fixture is always valid against the actual PHP version in CI and we
 * don't have to vendor a binary blob.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\extractor;

/**
 * Tests for the PDF extractor wrapping the bundled smalot/pdfparser.
 *
 * @covers \local_aigrader\extractor\pdf_extractor
 */
final class pdf_extractor_test extends \advanced_testcase {
    /**
     * Generate a small text PDF on the fly using Moodle's TCPDF wrapper.
     *
     * @param string[] $lines One PDF line per array element.
     * @return string Raw PDF bytes.
     */
    private function make_pdf(array $lines): string {
        global $CFG;
        require_once($CFG->libdir . '/pdflib.php');

        $p = new \pdf();
        $p->AddPage();
        $p->SetFont('Helvetica', '', 12);
        foreach ($lines as $line) {
            $p->Cell(0, 10, $line);
            $p->Ln();
        }
        return $p->Output('', 'S');
    }

    /**
     * Wrap raw bytes as a stored_file in the system context.
     *
     * @param string $bytes Raw file contents.
     * @param string $filename Filename to register.
     * @return \stored_file
     */
    private function as_stored_file(string $bytes, string $filename): \stored_file {
        $fs = \get_file_storage();
        $rec = [
            'contextid' => \context_system::instance()->id,
            'component' => 'local_aigrader',
            'filearea'  => 'unittest',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
        ];
        return $fs->create_file_from_string($rec, $bytes);
    }

    // -------------------------------------------------------------------.

    public function test_extracts_text_from_simple_pdf(): void {
        $this->resetAfterTest(true);

        $pdf = $this->make_pdf([
            'AI Grader Pro test report',
            'Train accuracy 95.0 percent on Fashion-MNIST.',
            'Validation accuracy 92.3 percent.',
            'The conclusion section follows.',
        ]);
        $file = $this->as_stored_file($pdf, 'test.pdf');

        $text = pdf_extractor::extract_file($file);

        $this->assertNotNull($text, 'expected non-null extraction for a well-formed PDF');
        $this->assertStringContainsString('AI Grader Pro test report', $text);
        $this->assertStringContainsString('Train accuracy 95.0', $text);
        $this->assertStringContainsString('Validation accuracy 92.3', $text);
    }

    /**
     * PDFs above MAX_FILESIZE_BYTES must be rejected without attempting to
     * parse — parsing an 11 MB PDF needed ~1 GB of PHP memory in the pilot,
     * which is unsafe on typical Moodle hosts.
     */
    public function test_rejects_files_larger_than_cap(): void {
        $this->resetAfterTest(true);

        // Build a file that's clearly larger than the cap by padding the
        // bytes after a real PDF header. We don't need it to be a valid
        // PDF — extract_file() should bail on size BEFORE attempting to
        // parse, so any blob over the cap will trigger the early return.
        $blob = "%PDF-1.4\n" . str_repeat('A', pdf_extractor::MAX_FILESIZE_BYTES + 1024);
        $file = $this->as_stored_file($blob, 'huge.pdf');

        $this->assertNull(
            pdf_extractor::extract_file($file),
            'oversized PDF must short-circuit to null without parsing'
        );
        // The extractor logs a debugging() message on the oversize path.
        $this->assertDebuggingCalled();
    }

    /**
     * A malformed PDF (just garbage bytes) must not crash the extractor; it
     * should return null so the dispatcher can mark the submission for
     * manual review.
     */
    public function test_malformed_pdf_returns_null_without_throwing(): void {
        $this->resetAfterTest(true);

        $file = $this->as_stored_file(
            "%PDF-1.4\n\xDE\xAD\xBE\xEFnot a real pdf",
            'broken.pdf'
        );

        $text = pdf_extractor::extract_file($file);

        $this->assertNull($text, 'malformed PDF must return null, not throw');
        // The catch path logs a debugging() entry. Asserting it consumes the
        // queue so resetAfterTest is happy.
        $this->assertDebuggingCalled();
    }

    /**
     * A "PDF" with no extractable text (in our test, well under the
     * MIN_USEFUL_CHARS threshold) must also return null so the caller
     * routes it to manual review. Simulates the image-only / scanned PDF
     * scenario.
     */
    public function test_near_empty_pdf_is_treated_as_unparseable(): void {
        $this->resetAfterTest(true);

        // A valid PDF whose body is so short that extracted text falls
        // below the MIN_USEFUL_CHARS threshold.
        $pdf  = $this->make_pdf(['x']);
        $file = $this->as_stored_file($pdf, 'tiny.pdf');

        $text = pdf_extractor::extract_file($file);

        $this->assertNull(
            $text,
            'PDFs whose extracted text is below the usefulness threshold must return null'
        );
    }

    public function test_empty_bytes_returns_null(): void {
        $this->resetAfterTest(true);
        $file = $this->as_stored_file('', 'empty.pdf');
        $this->assertNull(pdf_extractor::extract_file($file));
    }
}
