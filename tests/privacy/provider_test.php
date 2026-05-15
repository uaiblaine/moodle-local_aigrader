<?php
/**
 * PHPUnit tests for the AI Grader Pro privacy provider.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_aigrader\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_aigrader\privacy\provider
 */
final class provider_test extends \advanced_testcase {

    /** @var \stdClass */
    private $course;
    /** @var \stdClass */
    private $teacher;
    /** @var \stdClass */
    private $student;
    /** @var \stdClass */
    private $assigninstance;
    /** @var \context_module */
    private $context;
    /** @var int assign_submission.id */
    private $submissionid;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setup_fixture();
    }

    /**
     * Build a course + teacher + student + assign + AI Grader Pro state.
     */
    private function setup_fixture(): void {
        global $DB;

        $gen = $this->getDataGenerator();

        $this->course  = $gen->create_course();
        $this->teacher = $gen->create_user(['username' => 'aig_teacher']);
        $this->student = $gen->create_user(['username' => 'aig_student']);
        $gen->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $gen->enrol_user($this->student->id, $this->course->id, 'student');

        // Create the assign module instance.
        $this->assigninstance = $gen->create_module('assign', [
            'course' => $this->course->id,
            'name'   => 'Privacy test assignment',
            'intro'  => '<p>Write an essay.</p>',
        ]);
        $cm = get_coursemodule_from_instance('assign', $this->assigninstance->id, $this->course->id);
        $this->context = \context_module::instance($cm->id);

        $now = time();

        // Insert assign_submission directly so we can fix the id.
        $this->submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment'    => $this->assigninstance->id,
            'userid'        => $this->student->id,
            'timecreated'   => $now,
            'timemodified'  => $now,
            'timestarted'   => $now,
            'status'        => 'submitted',
            'groupid'       => 0,
            'attemptnumber' => 0,
            'latest'        => 1,
        ]);

        // AI Grader Pro per-assignment config (edited by the teacher).
        // NOTE: create_module() above fires our coursemodule_edit_post_actions
        // hook, which auto-inserts a default (disabled) row for the new assign.
        // Wipe that row and insert our own with controlled values so the rest
        // of the fixture is deterministic.
        $DB->delete_records('local_aigrader_assign', ['assignid' => $this->assigninstance->id]);
        $DB->insert_record('local_aigrader_assign', (object) [
            'assignid'      => $this->assigninstance->id,
            'enabled'       => 1,
            'criteria_text' => 'Evaluate clarity, structure and language',
            'source'        => 'manual',
            'usermodified'  => $this->teacher->id,
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);

        // AI proposal for the student's submission.
        $DB->insert_record('local_aigrader_submission', (object) [
            'submissionid'      => $this->submissionid,
            'assignid'          => $this->assigninstance->id,
            'courseid'          => $this->course->id,
            'studentid'         => $this->student->id,
            'status'            => 'ai_proposed',
            'proposed_grade'    => 7.5,
            'proposed_feedback' => '{"final_grade":7.5,"strengths":["clear thesis"],"improvements":["add sources"],"justification":"Good basic structure."}',
            'timecreated'       => $now,
            'timemodified'      => $now,
            'timeprocessed'     => $now,
        ]);

        // Log entry: action triggered by teacher on student's submission.
        $DB->insert_record('local_aigrader_log', (object) [
            'submissionid'      => $this->submissionid,
            'userid'            => $this->teacher->id,
            'studentid'         => $this->student->id,
            'courseid'          => $this->course->id,
            'action'            => 'grade',
            'llm_provider'      => 'openai',
            'llm_model'         => 'test-model',
            'prompt_hash'       => str_repeat('a', 64),
            'prompt_text'       => 'Test prompt including the student essay text.',
            'response_json'     => '{"final_grade":7.5}',
            'tokens_input'      => 500,
            'tokens_output'     => 200,
            'proposed_grade'    => 7.5,
            'submission_format' => 'onlinetext',
            'timecreated'       => $now,
        ]);
    }

    // -----------------------------------------------------------------
    // get_metadata
    // -----------------------------------------------------------------

    public function test_get_metadata_declares_all_storage_locations(): void {
        $collection = new collection('local_aigrader');
        $result     = provider::get_metadata($collection);
        $items      = $result->get_collection();

        $this->assertCount(4, $items, 'Expected 3 tables + 1 external location');

        $names = array_map(static fn($i) => $i->get_name(), $items);
        $this->assertContains('local_aigrader_assign', $names);
        $this->assertContains('local_aigrader_submission', $names);
        $this->assertContains('local_aigrader_log', $names);
        $this->assertContains('ai_subsystem', $names);
    }

    // -----------------------------------------------------------------
    // get_contexts_for_userid
    // -----------------------------------------------------------------

    public function test_get_contexts_for_student_finds_the_assignment(): void {
        $contextlist = provider::get_contexts_for_userid($this->student->id);
        // Use assertContainsEquals — Moodle's contextlist may return ids as
        // strings (PostgreSQL pgsql driver default) while $context->id is int.
        $this->assertContainsEquals($this->context->id, $contextlist->get_contextids());
    }

    public function test_get_contexts_for_teacher_finds_the_assignment(): void {
        $contextlist = provider::get_contexts_for_userid($this->teacher->id);
        $this->assertContainsEquals($this->context->id, $contextlist->get_contextids());
    }

    public function test_get_contexts_for_unrelated_user_is_empty(): void {
        $other = $this->getDataGenerator()->create_user();
        $contextlist = provider::get_contexts_for_userid($other->id);
        $this->assertEmpty($contextlist->get_contextids());
    }

    // -----------------------------------------------------------------
    // get_users_in_context
    // -----------------------------------------------------------------

    public function test_get_users_in_context_lists_student_and_teacher(): void {
        $userlist = new userlist($this->context, 'local_aigrader');
        provider::get_users_in_context($userlist);

        $ids = $userlist->get_userids();
        // Loose comparison: ids come back as strings from pgsql driver but
        // $student->id is int from create_user().
        $this->assertContainsEquals($this->student->id, $ids);
        $this->assertContainsEquals($this->teacher->id, $ids);
    }

    // -----------------------------------------------------------------
    // delete_data_for_all_users_in_context
    // -----------------------------------------------------------------

    public function test_delete_all_users_in_context_wipes_assignment(): void {
        global $DB;

        $this->assertEquals(1, $DB->count_records('local_aigrader_submission',
            ['assignid' => $this->assigninstance->id]));
        $this->assertEquals(1, $DB->count_records('local_aigrader_assign',
            ['assignid' => $this->assigninstance->id]));
        $this->assertEquals(1, $DB->count_records('local_aigrader_log',
            ['submissionid' => $this->submissionid]));

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(0, $DB->count_records('local_aigrader_submission',
            ['assignid' => $this->assigninstance->id]));
        $this->assertEquals(0, $DB->count_records('local_aigrader_assign',
            ['assignid' => $this->assigninstance->id]));
        $this->assertEquals(0, $DB->count_records('local_aigrader_log',
            ['submissionid' => $this->submissionid]));
    }

    // -----------------------------------------------------------------
    // delete_data_for_user
    // -----------------------------------------------------------------

    public function test_delete_for_student_hard_deletes_student_rows(): void {
        global $DB;

        $approved = new approved_contextlist($this->student, 'local_aigrader', [$this->context->id]);
        provider::delete_data_for_user($approved);

        // Student-owned rows are hard-deleted.
        $this->assertEquals(0, $DB->count_records('local_aigrader_submission',
            ['studentid' => $this->student->id]));
        $this->assertEquals(0, $DB->count_records('local_aigrader_log',
            ['studentid' => $this->student->id]));
    }

    public function test_delete_for_teacher_anonymises_keeps_audit_trail(): void {
        global $DB;

        // Before: row exists with userid=teacher.
        $this->assertEquals(1, $DB->count_records('local_aigrader_log',
            ['userid' => $this->teacher->id]));
        $this->assertEquals(1, $DB->count_records('local_aigrader_assign',
            ['usermodified' => $this->teacher->id]));

        // Pre-existing student row should remain intact after teacher deletion.
        $studentrowsbefore = $DB->count_records('local_aigrader_submission',
            ['studentid' => $this->student->id]);

        $approved = new approved_contextlist($this->teacher, 'local_aigrader', [$this->context->id]);
        provider::delete_data_for_user($approved);

        // Teacher's identity should be anonymised to 0 (not deleted).
        $this->assertEquals(0, $DB->count_records('local_aigrader_log',
            ['userid' => $this->teacher->id]));
        $this->assertEquals(1, $DB->count_records('local_aigrader_log',
            ['userid' => 0]),
            'Audit row should still exist with userid=0');

        $this->assertEquals(0, $DB->count_records('local_aigrader_assign',
            ['usermodified' => $this->teacher->id]));
        $this->assertEquals(1, $DB->count_records('local_aigrader_assign',
            ['usermodified' => 0]));

        // Student rows untouched.
        $this->assertEquals($studentrowsbefore, $DB->count_records('local_aigrader_submission',
            ['studentid' => $this->student->id]));
    }

    // -----------------------------------------------------------------
    // delete_data_for_users (batch)
    // -----------------------------------------------------------------

    public function test_delete_for_users_batch_handles_student_and_teacher(): void {
        global $DB;

        $userlist = new approved_userlist($this->context, 'local_aigrader',
            [$this->student->id, $this->teacher->id]);
        provider::delete_data_for_users($userlist);

        // Student rows gone.
        $this->assertEquals(0, $DB->count_records('local_aigrader_submission',
            ['studentid' => $this->student->id]));
        // Teacher rows anonymised.
        $this->assertEquals(0, $DB->count_records('local_aigrader_log',
            ['userid' => $this->teacher->id]));
    }

    // -----------------------------------------------------------------
    // export_user_data
    // -----------------------------------------------------------------

    public function test_export_for_student_writes_submission_data(): void {
        $writer = writer::with_context($this->context);
        $this->assertFalse($writer->has_any_data());

        $approved = new approved_contextlist($this->student, 'local_aigrader', [$this->context->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data(),
            'Privacy export should produce some data for the student');
    }

    public function test_export_for_teacher_writes_config_data(): void {
        $approved = new approved_contextlist($this->teacher, 'local_aigrader', [$this->context->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data(),
            'Privacy export should produce some data for the teacher');
    }
}
