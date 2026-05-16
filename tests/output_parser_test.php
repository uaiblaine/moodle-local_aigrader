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
 * Tests for the LLM JSON response parser.
 *
 * In particular, the happy-path test would have caught the v0.10.2 regression
 * where phpcs cleanup renamed parsed_proposal::success() parameters but the
 * named arguments in output_parser::parse() were not updated to match,
 * causing a "Unknown named parameter" TypeError on every successful grading
 * call.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader;

/**
 * Tests for the LLM-response JSON parser.
 *
 * @covers \local_aigrader\output_parser
 */
final class output_parser_test extends \advanced_testcase {
    /**
     * Build a realistic JSON response, parse it, and assert every field
     * round-trips correctly. The constructor-mismatch regression would fail
     * here with a TypeError before the assertions even run.
     */
    public function test_happy_path_constructs_proposal(): void {
        $json = json_encode([
            'criterion_scores' => [
                'thesis_clarity'   => 7,
                'evidence_quality' => 6.5,
                'structure'        => 8,
            ],
            'final_grade'       => 7.2,
            'strengths'         => ['Buena introducción', 'Argumentación sólida'],
            'improvements'      => ['Faltan citas APA', 'Conclusión débil'],
            'justification'     => 'El ensayo cubre bien el tema pero carece de evidencia primaria.',
            'feedback_language' => 'es',
        ]);

        $p = output_parser::parse($json);

        $this->assertTrue($p->success, 'parser should succeed on a well-formed response');
        $this->assertNull($p->error);
        $this->assertEqualsWithDelta(7.2, $p->grade, 0.001);
        $this->assertSame(7.0, $p->criterion_scores['thesis_clarity']);
        $this->assertSame(6.5, $p->criterion_scores['evidence_quality']);
        $this->assertCount(2, $p->strengths);
        $this->assertCount(2, $p->improvements);
        $this->assertSame('es', $p->language);
        $this->assertNotEmpty($p->cleaned_json);
        $this->assertNotEmpty($p->raw_response);
    }

    /**
     * LLMs often wrap their JSON output in ```json fences. The parser should
     * strip them transparently.
     */
    public function test_strips_markdown_code_fences(): void {
        $payload = json_encode([
            'criterion_scores' => ['x' => 5],
            'final_grade'      => 5.0,
            'strengths'        => [],
            'improvements'     => [],
            'justification'    => 'placeholder',
        ]);
        $fenced = "```json\n" . $payload . "\n```";

        $p = output_parser::parse($fenced);

        $this->assertTrue($p->success);
        $this->assertEqualsWithDelta(5.0, $p->grade, 0.001);
    }

    /**
     * Some LLMs add preamble text before the JSON. The parser should locate
     * the first balanced JSON object regardless.
     */
    public function test_tolerates_preamble_text(): void {
        $payload = json_encode([
            'criterion_scores' => ['x' => 6],
            'final_grade'      => 6.0,
            'strengths'        => [],
            'improvements'     => [],
            'justification'    => 'ok',
        ]);
        $withpreamble = "Aquí tienes la evaluación solicitada:\n\n" . $payload;

        $p = output_parser::parse($withpreamble);

        $this->assertTrue($p->success, 'parser should locate JSON after preamble: ' . ($p->error ?? ''));
        $this->assertEqualsWithDelta(6.0, $p->grade, 0.001);
    }

    /**
     * If the model returns grades on 0-100 (a common confusion), normalise.
     */
    public function test_normalises_zero_to_hundred_scale(): void {
        $payload = json_encode([
            'criterion_scores' => ['x' => 80],
            'final_grade'      => 72,
            'strengths'        => [],
            'improvements'     => [],
            'justification'    => 'ok',
        ]);

        $p = output_parser::parse($payload);

        $this->assertTrue($p->success);
        $this->assertEqualsWithDelta(7.2, $p->grade, 0.001);
        $this->assertEqualsWithDelta(8.0, $p->criterion_scores['x'], 0.001);
    }

    public function test_empty_response_is_error(): void {
        $p = output_parser::parse('');
        $this->assertFalse($p->success);
        $this->assertStringContainsString('empty', strtolower((string) $p->error));
    }

    public function test_invalid_json_is_error(): void {
        $p = output_parser::parse('{ this is not json');
        $this->assertFalse($p->success);
    }

    public function test_missing_required_field_is_error(): void {
        $payload = json_encode([
            'final_grade'   => 7.0,
            // criterion_scores omitted intentionally.
            'strengths'     => [],
            'improvements'  => [],
            'justification' => 'x',
        ]);

        $p = output_parser::parse($payload);

        $this->assertFalse($p->success);
        $this->assertStringContainsString('criterion_scores', (string) $p->error);
    }

    /**
     * The successful proposal must serialise back to a parseable JSON shape
     * that downstream code (review.php, audit log) can rely on.
     */
    public function test_as_json_round_trip(): void {
        $payload = json_encode([
            'criterion_scores' => ['a' => 7, 'b' => 8],
            'final_grade'      => 7.5,
            'strengths'        => ['s1'],
            'improvements'     => ['i1'],
            'justification'    => 'because',
            'feedback_language' => 'en',
        ]);

        $p = output_parser::parse($payload);
        $this->assertTrue($p->success);

        $roundtrip = json_decode($p->as_json(), true);
        $this->assertSame(7.5, $roundtrip['final_grade']);
        $this->assertSame('en', $roundtrip['feedback_language']);
        // assertEquals (not assertSame) because json_encode emits floats with
        // no fractional part as "7" not "7.0", so json_decode reads them
        // back as ints. That's harmless for downstream consumers but breaks
        // strict type comparison.
        $this->assertEquals(['a' => 7, 'b' => 8], $roundtrip['criterion_scores']);
    }
}
