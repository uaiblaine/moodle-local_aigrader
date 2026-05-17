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
 * Tests for the bulk dispatcher's classify() — the matrix of
 *
 *   action × current AI-grader status → eligible or skipped(reason).
 *
 * These tests focus on classify() only; execute() is a side-effecting
 * orchestrator (calls manager::grade_submission, writes DB, enqueues tasks)
 * which is best tested via higher-level behat scenarios against a real
 * fixture course. The eligibility matrix is the actual business logic — and
 * the part that is easy to get subtly wrong without a regression test.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\bulk;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for \local_aigrader\bulk\dispatcher::classify().
 *
 * @covers \local_aigrader\bulk\dispatcher
 */
class bulk_dispatcher_test extends \basic_testcase {

    /**
     * Tiny helper: build the minimal row shape that classify() reads.
     */
    private function row(?string $status, $proposedgrade = null): object {
        return (object) [
            'submissionid'   => 42,
            'studentid'      => 7,
            'aigrader_id'    => 1,
            'ai_status'      => $status,
            'proposed_grade' => $proposedgrade,
        ];
    }

    // ===================================================================.
    // ACTION_APPROVE_PUBLISH — needs a usable proposal.
    // ===================================================================.

    public function test_approve_publish_runs_on_ai_proposed(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('ai_proposed', 7.5))
        );
    }

    public function test_approve_publish_runs_on_teacher_reviewed(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('teacher_reviewed', 7.5))
        );
    }

    public function test_approve_publish_skips_already_published(): void {
        $this->assertSame(
            'skip:already_published',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('published', 8.0))
        );
    }

    public function test_approve_publish_skips_pending(): void {
        $this->assertSame(
            'skip:in_flight',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('pending_ai'))
        );
    }

    public function test_approve_publish_skips_unsupported_format(): void {
        $this->assertSame(
            'skip:unsupported',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('unsupported_format'))
        );
    }

    public function test_approve_publish_skips_error(): void {
        $this->assertSame(
            'skip:no_proposal',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row('error'))
        );
    }

    public function test_approve_publish_skips_null_status(): void {
        $this->assertSame(
            'skip:no_proposal',
            dispatcher::classify(dispatcher::ACTION_APPROVE_PUBLISH, $this->row(null))
        );
    }

    // ===================================================================.
    // ACTION_GRADE_AI — only runs on rows that haven't been graded yet.
    // ===================================================================.

    public function test_grade_ai_runs_on_null_status(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row(null))
        );
    }

    public function test_grade_ai_runs_on_error(): void {
        // Errors are recoverable: re-run is the natural retry path.
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('error'))
        );
    }

    public function test_grade_ai_skips_when_proposal_exists(): void {
        $this->assertSame(
            'skip:already_proposed',
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('ai_proposed', 7.0))
        );
    }

    public function test_grade_ai_skips_pending(): void {
        $this->assertSame(
            'skip:in_flight',
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('pending_ai'))
        );
    }

    public function test_grade_ai_skips_unsupported(): void {
        $this->assertSame(
            'skip:unsupported',
            dispatcher::classify(dispatcher::ACTION_GRADE_AI, $this->row('unsupported_format'))
        );
    }

    // ===================================================================.
    // ACTION_REGRADE_AI — runs on anything that ran before.
    // ===================================================================.

    public function test_regrade_ai_runs_on_ai_proposed(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_REGRADE_AI, $this->row('ai_proposed', 7.0))
        );
    }

    public function test_regrade_ai_runs_on_published(): void {
        // Published rows can be re-graded too; the gradebook value stays
        // until the teacher publishes the new proposal explicitly.
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_REGRADE_AI, $this->row('published', 8.0))
        );
    }

    public function test_regrade_ai_runs_on_error(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_REGRADE_AI, $this->row('error'))
        );
    }

    public function test_regrade_ai_skips_unsupported(): void {
        // Re-running can't fix a too-big PDF — the teacher needs to upload
        // a parseable version first. Surface the skip reason explicitly.
        $this->assertSame(
            'skip:unsupported',
            dispatcher::classify(dispatcher::ACTION_REGRADE_AI, $this->row('unsupported_format'))
        );
    }

    public function test_regrade_ai_skips_null_status(): void {
        // Re-grade implies "grade again"; if there is no first grade, fall
        // back to ACTION_GRADE_AI. We do not silently elevate.
        $this->assertSame(
            'skip:no_proposal',
            dispatcher::classify(dispatcher::ACTION_REGRADE_AI, $this->row(null))
        );
    }

    // ===================================================================.
    // ACTION_MARK_MANUAL — clears the AI proposal status.
    // ===================================================================.

    public function test_mark_manual_runs_on_ai_proposed(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_MARK_MANUAL, $this->row('ai_proposed', 7.0))
        );
    }

    public function test_mark_manual_runs_on_unsupported(): void {
        // Useful when the format issue is unfixable and the teacher wants
        // to grade by hand without bumping the row to a published state.
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_MARK_MANUAL, $this->row('unsupported_format'))
        );
    }

    public function test_mark_manual_runs_on_error(): void {
        $this->assertSame(
            dispatcher::RESULT_OK,
            dispatcher::classify(dispatcher::ACTION_MARK_MANUAL, $this->row('error'))
        );
    }

    public function test_mark_manual_skips_already_published(): void {
        // Unpublishing is a separate action (not implemented yet).
        $this->assertSame(
            'skip:already_published',
            dispatcher::classify(dispatcher::ACTION_MARK_MANUAL, $this->row('published', 8.0))
        );
    }

    public function test_mark_manual_skips_null_status(): void {
        $this->assertSame(
            'skip:no_change',
            dispatcher::classify(dispatcher::ACTION_MARK_MANUAL, $this->row(null))
        );
    }

    // ===================================================================.
    // Bad inputs.
    // ===================================================================.

    public function test_unknown_action_is_classified_as_unknown(): void {
        $this->assertSame(
            'skip:unknown_action',
            dispatcher::classify('not_a_real_action', $this->row('ai_proposed', 7.0))
        );
    }

    // ===================================================================.
    // Destructive actions list contract — UI relies on this membership.
    // ===================================================================.

    public function test_approve_publish_is_destructive(): void {
        $this->assertContains(
            dispatcher::ACTION_APPROVE_PUBLISH,
            dispatcher::DESTRUCTIVE_ACTIONS,
            'approve_publish must require confirmation — it writes to the gradebook.'
        );
    }

    public function test_grade_ai_is_not_destructive(): void {
        $this->assertNotContains(
            dispatcher::ACTION_GRADE_AI,
            dispatcher::DESTRUCTIVE_ACTIONS,
            'grade_ai does not write final grades to the gradebook (only proposes).'
        );
    }

    public function test_mark_manual_is_not_destructive(): void {
        $this->assertNotContains(
            dispatcher::ACTION_MARK_MANUAL,
            dispatcher::DESTRUCTIVE_ACTIONS,
            'mark_manual only flips a status flag; nothing reaches the gradebook.'
        );
    }

    public function test_all_actions_are_known_strings(): void {
        // Guards against typos that would silently break the UI's i18n.
        $expected = ['approve_publish', 'grade_ai', 'regrade_ai', 'mark_manual'];
        $this->assertSame($expected, dispatcher::ALL_ACTIONS);
    }
}
