<?php
/**
 * English language strings for AI Grader Pro.
 *
 * @package    local_aigrader
 * @copyright  2026 Hernán Díaz
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Grader Pro';

// Capability descriptions (shown in Site administration > Permissions).
$string['aigrader:use'] = 'Use AI-assisted grading on assignments';
$string['aigrader:configure'] = 'Configure AI Grader Pro on an assignment';
$string['aigrader:viewlog'] = 'View AI Grader Pro audit log';

// Admin settings page.
$string['setting_enabled'] = 'Enable plugin';
$string['setting_enabled_desc'] = 'Global on/off switch for AI Grader Pro. When disabled, teachers cannot trigger new AI grading on any assignment. Existing audit logs are preserved.';

$string['setting_rubric_autoimport'] = 'Auto-import from grading rubric';
$string['setting_rubric_autoimport_desc'] = 'When an assignment uses Moodle\'s rubric grading method, automatically pre-populate the AI Grader Pro evaluation criteria with the rubric content. Teachers can still edit the imported criteria before enabling AI grading.';

$string['setting_default_system_prompt'] = 'Default system prompt';
$string['setting_default_system_prompt_desc'] = 'Optional institution-wide instruction prepended to the system prompt of every grading request. Use this to enforce consistent tone or policy across all teachers. Example: "Provide constructive feedback in academic register, maximum 200 words." Leave empty to use only the plugin\'s default system prompt.';

// Assignment edit form (mod_assign).
$string['form_enabled'] = 'Enable AI-assisted grading for this assignment';
$string['form_enabled_help'] = 'When checked, teachers can trigger AI Grader Pro on this assignment\'s submissions. The AI proposes a grade and feedback; the teacher reviews and decides. Nothing is published to the student until the teacher approves.';

$string['form_criteria'] = 'Evaluation criteria';
$string['form_criteria_help'] = 'Plain-language description of how the AI should evaluate submissions to this assignment. Write the same instructions you would give to a teaching assistant. Mention specific criteria, their relative weight, and the tone of feedback you want. Example:

Evaluate this essay (800-1000 words) on digital education using these criteria:
- Thesis clarity (25%): is the position defensible?
- Quality of evidence (30%): are sources academic and well-cited?
- Structure (25%): introduction, development, conclusion
- Language (20%): academic register, spelling

Tone: constructive and specific, in Spanish.';

$string['form_criteria_imported_notice'] = 'Criteria pre-filled from the rubric configured under "Grade > Advanced grading". You may edit them before enabling AI grading.';

$string['form_model_override'] = 'Model override (optional)';
$string['form_model_override_help'] = 'If set, this assignment uses this specific model instead of the default configured in the AI provider. Useful when you want a more capable (or cheaper) model for a particular task. Leave empty to use the global default.';

$string['form_language_override'] = 'Feedback language (optional)';
$string['form_language_override_help'] = 'If set, AI feedback for this assignment will be in this language instead of the course language. Leave on "Auto" to use the course\'s language.';

$string['form_lang_auto'] = 'Auto (use course language)';

// Form errors.
$string['error_criteria_required'] = 'Evaluation criteria are required when AI-assisted grading is enabled. Describe how the AI should evaluate the submissions.';

// Rubric importer.
$string['rubric_export_header'] = 'Criteria (auto-imported from the assignment\'s advanced grading rubric):';

// Privacy.
$string['privacy:metadata'] = 'AI Grader Pro stores audit logs of AI-assisted grading actions, including prompts sent to the configured LLM provider, model responses, proposed grades and teacher edits. See plugin documentation for full details.';

// Adhoc tasks (shown in Site administration > Server > Tasks).
$string['task_grade_submission'] = 'AI Grader Pro: grade a submission';
$string['errortaskfailed'] = 'AI Grader Pro grading task failed: {$a}';

// Management page (/local/aigrader/manage.php).
$string['manage_pagetitle']         = 'AI Grader Pro · {$a}';
$string['manage_heading']           = 'AI Grader Pro: {$a}';
$string['manage_disabled']          = 'AI Grader Pro is not enabled on this assignment. Edit the assignment settings to enable it.';
$string['manage_no_submissions']    = 'No submitted assignments yet for this task.';
$string['manage_polling']           = 'A grading task is in progress. This page will refresh automatically.';
$string['manage_back_to_assignment']= '← Back to the assignment';
$string['msg_enqueued']             = 'AI grading task enqueued. It will run on the next cron tick.';

$string['th_student']   = 'Student';
$string['th_submitted'] = 'Submitted';
$string['th_status']    = 'AI status';
$string['th_grade']     = 'Proposed grade';
$string['th_action']    = 'Action';

$string['btn_grade_with_ai']   = 'Grade with AI';
$string['btn_regrade_with_ai'] = 'Re-grade with AI';
$string['btn_pending']         = 'Processing...';

$string['status_none']        = 'No AI grading yet';
$string['status_pending']     = 'Pending';
$string['status_proposed']    = 'AI proposed';
$string['status_reviewed']    = 'Teacher reviewed';
$string['status_published']   = 'Published';
$string['status_error']       = 'Error';
$string['status_unsupported'] = 'Unsupported format';

$string['errornotenabled']  = 'AI Grader Pro is not enabled on this assignment.';
$string['errornocriteria']  = 'No evaluation criteria are set for this assignment.';

// Review page (/local/aigrader/review.php).
$string['review_pagetitle']       = 'Review AI proposal · {$a}';
$string['review_heading']         = 'Review AI proposal: {$a->assign} — {$a->student}';
$string['review_submission_text'] = 'Student submission';
$string['review_proposed']        = 'Proposed grade and feedback (editable)';
$string['review_criterion_scores']= 'Criterion scores (from AI, for context)';
$string['review_proposed_at']     = 'Proposed at {$a}';
$string['review_proposed_by']     = 'by {$a->provider} ({$a->model})';

$string['field_finalgrade']         = 'Final grade (0-10)';
$string['field_strengths']          = 'Strengths';
$string['field_strengths_hint']     = 'One per line. These will be shown to the student as positive feedback.';
$string['field_improvements']       = 'Improvements';
$string['field_improvements_hint']  = 'One per line. Constructive suggestions shown to the student.';
$string['field_justification']      = 'Justification (visible to the student)';

$string['btn_review']         = 'Review →';
$string['btn_view_published'] = 'View ✓';
$string['confirm_reject']     = 'This will discard the AI proposal and you will grade manually. Continue?';

$string['msg_published']      = 'Grade approved and published to the gradebook.';
$string['msg_rejected']       = 'AI proposal rejected. Please grade manually using the standard assignment grader.';

$string['feedback_strengths']    = 'Strengths';
$string['feedback_improvements'] = 'Areas to improve';
$string['feedback_justification']= 'Summary';

$string['errornoproposal']      = 'No AI proposal is available for this submission.';
$string['errorparseproposal']   = 'The stored AI proposal could not be parsed. Try re-grading.';
$string['errorgradeoutofrange'] = 'The grade must be between 0 and 10 (received: {$a}).';

// Review page (/local/aigrader/review.php).
$string['review_pagetitle']       = 'Review AI proposal · {$a}';
$string['review_heading']         = 'Review AI proposal: {$a->assign} — {$a->student}';
$string['review_submission_text'] = 'Student submission';
$string['review_proposed']        = 'Proposed grade and feedback (editable)';
$string['review_criterion_scores']= 'Criterion scores (from AI, for context)';
$string['review_proposed_at']     = 'Proposed at {$a}';
$string['review_proposed_by']     = 'by {$a->provider} ({$a->model})';

$string['field_finalgrade']         = 'Final grade (0-10)';
$string['field_strengths']          = 'Strengths';
$string['field_strengths_hint']     = 'One per line. These will be shown to the student as positive feedback.';
$string['field_improvements']       = 'Improvements';
$string['field_improvements_hint']  = 'One per line. Constructive suggestions shown to the student.';
$string['field_justification']      = 'Justification (visible to the student)';

$string['btn_review']          = 'Review →';
$string['btn_view_published']  = 'View ✓';
$string['btn_approve_publish'] = 'Approve and publish';
$string['btn_reject']          = 'Reject (grade manually)';
$string['confirm_reject']      = 'This will discard the AI proposal and you will grade manually. Continue?';

$string['msg_published']      = 'Grade approved and published to the gradebook.';
$string['msg_rejected']       = 'AI proposal rejected. Please grade manually using the standard assignment grader.';

$string['feedback_strengths']    = 'Strengths';
$string['feedback_improvements'] = 'Areas to improve';
$string['feedback_justification']= 'Summary';

$string['errornoproposal']      = 'No AI proposal is available for this submission.';
$string['errorparseproposal']   = 'The stored AI proposal could not be parsed. Try re-grading.';
$string['errorgradeoutofrange'] = 'The grade must be between 0 and 10 (received: {$a}).';
