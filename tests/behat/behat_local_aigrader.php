<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin-specific Behat step definitions for local_aigrader.
 *
 * Steps defined here can be referenced from any .feature file in
 * tests/behat/*.feature. The Behat data generator under
 * tests/generator/lib.php is the workhorse — these steps just translate
 * Gherkin sentences into calls on it.
 *
 * Naming convention follows the rest of Moodle (`behat_<component>`).
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Step definitions for AI Grader Pro Behat scenarios.
 */
class behat_local_aigrader extends behat_base {
    /**
     * Enable AI Grader Pro on an existing assignment, optionally with custom criteria.
     *
     * Example:
     *   Given AI Grader Pro is enabled on the "Essay 1" assignment
     *   And AI Grader Pro is enabled on the "Essay 1" assignment with criteria "Custom criteria"
     *
     * @Given /^AI Grader Pro is enabled on the "(?P<name>(?:[^"]|\\")*)" assignment$/
     * @param string $name Assignment name (matches assign.name).
     */
    public function ai_grader_pro_is_enabled_on_assignment(string $name): void {
        $this->enable_for_assignment_by_name($name);
    }

    /**
     * Enable AI Grader Pro on an assignment with custom criteria text.
     *
     * @Given /^AI Grader Pro is enabled on the "(?P<name>(?:[^"]|\\")*)" assignment with criteria "(?P<criteria>(?:[^"]|\\")*)"$/
     * @param string $name Assignment name.
     * @param string $criteria Free-text evaluation criteria.
     */
    public function ai_grader_pro_is_enabled_with_criteria(string $name, string $criteria): void {
        $this->enable_for_assignment_by_name($name, ['criteria_text' => $criteria]);
    }

    /**
     * Plant a submission proposal directly in the database.
     *
     * Skips the LLM call so scenarios are deterministic and fast.
     *
     * Example:
     *   Given the following local_aigrader submissions exist:
     *     | student   | assignment | status        | proposed_grade |
     *     | student1  | Essay 1    | ai_proposed   | 8.5            |
     *     | student2  | Essay 1    | published     | 7.0            |
     *
     * @Given /^the following local_aigrader submissions exist:$/
     * @param TableNode $table Data table.
     */
    public function the_following_local_aigrader_submissions_exist(TableNode $table): void {
        global $DB;

        $rows = $table->getColumnsHash();
        $generator = $this->get_plugin_generator();

        foreach ($rows as $row) {
            $student = $DB->get_record('user', ['username' => $row['student']], '*', MUST_EXIST);
            $assign = $DB->get_record('assign', ['name' => $row['assignment']], '*', MUST_EXIST);

            // Ensure the per-assignment config row exists — without it,
            // manage.php throws "AI Grader Pro is not enabled on this assignment".
            $this->enable_for_assignment_by_id($assign);

            // Ensure an assign_submission row exists for this student.
            $assignsub = $DB->get_record('assign_submission', [
                'assignment' => $assign->id,
                'userid'     => $student->id,
            ]);
            if (!$assignsub) {
                $now = time();
                $subid = $DB->insert_record('assign_submission', (object) [
                    'assignment'    => $assign->id,
                    'userid'        => (int) $student->id,
                    'timecreated'   => $now,
                    'timemodified'  => $now,
                    'status'        => 'submitted',
                    'groupid'       => 0,
                    'attemptnumber' => 0,
                    'latest'        => 1,
                ]);
                $assignsub = $DB->get_record('assign_submission', ['id' => $subid], '*', MUST_EXIST);
            }

            // Translate table row to generator overrides.
            $overrides = ['status' => $row['status']];
            if (!empty($row['proposed_grade'])) {
                $overrides['proposed_grade'] = (float) $row['proposed_grade'];
            }
            if (!empty($row['final_grade'])) {
                $overrides['final_grade'] = (float) $row['final_grade'];
            }
            $generator->create_submission_proposal($assignsub, $overrides);
        }
    }

    /**
     * Navigate straight to AI Grader Pro's manage page for an assignment.
     *
     * Skipping the cog menu makes scenarios robust against theme variations
     * (Boost / Moove render the settings menu differently). Equivalent to
     * the teacher clicking the "AI Grader Pro" link from the activity
     * settings, but goes directly via the canonical URL.
     *
     * Example:
     *   When I open the AI Grader Pro manage page for "Essay 1"
     *
     * @Given /^I open the AI Grader Pro manage page for "(?P<name>(?:[^"]|\\")*)"$/
     * @param string $name Assignment name.
     */
    public function i_open_ai_grader_manage_page_for(string $name): void {
        global $DB;
        $assign = $DB->get_record('assign', ['name' => $name], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('assign', $assign->id, $assign->course, false, MUST_EXIST);
        $url = new moodle_url('/local/aigrader/manage.php', ['cmid' => $cm->id]);
        $this->execute('behat_general::i_visit', [$url->out(false)]);
    }

    /**
     * Helper: enable plugin on an assignment looked up by name.
     *
     * @param string $name
     * @param array $overrides
     */
    private function enable_for_assignment_by_name(string $name, array $overrides = []): void {
        global $DB;
        $assign = $DB->get_record('assign', ['name' => $name], '*', MUST_EXIST);
        $this->enable_for_assignment_by_id($assign, $overrides);
    }

    /**
     * Helper: enable plugin on an existing assign record.
     *
     * @param stdClass $assign
     * @param array $overrides
     */
    private function enable_for_assignment_by_id(stdClass $assign, array $overrides = []): void {
        $this->get_plugin_generator()->enable_for_assignment($assign, $overrides);
    }

    /**
     * Helper: get the plugin's data generator.
     *
     * In a Behat context `phpunit_util` is not loaded — that class is the
     * PHPUnit-side runner only. The cross-runtime equivalent is
     * `testing_util` which is available under both PHPUnit and Behat.
     *
     * @return local_aigrader_generator
     */
    private function get_plugin_generator(): local_aigrader_generator {
        return testing_util::get_data_generator()->get_plugin_generator('local_aigrader');
    }
}
