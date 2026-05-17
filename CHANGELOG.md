# Changelog

All notable changes to `local_aigrader` (AI Grader Pro) are documented
here. The format follows [Keep a Changelog](https://keepachangelog.com/),
versions follow Moodle's `YYYYMMDDXX` plugin-version convention with a
parallel semantic-style release name.

## [v1.0.5-beta] — 2026-05-17

### Added

- Bulk actions on the manage page (`/local/aigrader/manage.php`):
  checkbox per row plus a "Con seleccionadas..." dropdown lets the
  teacher apply one action to N rows in a single click. Solves the
  click-fest reported by the pilot teacher when reviewing 25+ rows
  whose AI proposals were correct out of the box.
- Four bulk actions:
  - **Publicar tal cual** — approves and publishes the AI proposed
    grades unchanged; goes through `\assign::save_grade()` so the
    standard event/feedback/gradebook pipeline runs (same path as the
    single-row "Aprobar y publicar" in review.php).
  - **Calificar con IA** — runs the LLM on rows that have never been
    graded (status NULL or `error`).
  - **Recalificar con IA** — runs the LLM on rows that already have a
    proposal or have errored before; overwrites the proposal.
  - **Marcar para revisión manual** — flips the AI status to
    `teacher_reviewed` so the row drops out of the AI-pending queue and
    the teacher grades it by hand in review.php.
- Hybrid sync/async execution: up to `dispatcher::SYNC_LIMIT` (5) rows
  the LLM-heavy actions run in the current request with `write_close()`
  + bumped `set_time_limit()`. Above 5 rows we enqueue adhoc
  `grade_submission` tasks and tell the teacher to come back when cron
  finishes. Below 5 the teacher waits ~3-5 s per row inline and gets
  immediate feedback; above 5 we trade live feedback for not locking
  the browser tab.
- Confirmation page for destructive actions (currently only
  `approve_publish`). Shows row counts, lists which rows will be
  skipped and why, and gates the action behind an explicit "Sí,
  publicar" submit. Non-destructive actions run directly.
- `local_aigrader\bulk\dispatcher` class with stateless `classify()`
  (action × status eligibility matrix) and `execute()` (orchestrator).
- 27 PHPUnit tests covering the full eligibility matrix
  (`bulk_dispatcher_test.php`) — every (action, status) cell is
  asserted, plus the destructive-actions contract that the UI relies
  on for the confirmation step.

### Changed

- `manage.php`: header gains a checkbox column; bulk action bar
  ("Con seleccionadas:" + dropdown + Apply) renders above the table.
  Per-row "Recalificar con IA" inline form is preserved unchanged;
  row checkboxes participate in the bulk form via the HTML5 `form="..."`
  attribute so neither nested forms nor invalid HTML is produced.
- Tiny inline JS for the "select all" header checkbox (no AMD module
  needed for five lines that only toggle checkboxes inside the bulk
  form).

### Notes

- The async path uses the existing `\local_aigrader\task\grade_submission`
  adhoc task class — no new task class added.
- Permissions: bulk actions reuse the existing `local/aigrader:use`
  capability check from `manage.php`; no new capabilities introduced.
- POST endpoints are CSRF-protected (`require_sesskey()`) and validate
  that every selected submission belongs to the cmid in the URL
  (defense-in-depth against tampered forms).

## [v1.0.4-beta] — 2026-05-16

### Changed

- README rewritten from the v0.1.0-alpha placeholder to a full
  user-facing description with features, install steps, configuration
  and usage flow.
- `local_aigrader_publish_grade()` no longer writes directly to
  `m_assign_grades` / `m_assignfeedback_comments` / via separate
  `grade_update()`. It now hands the grade off to
  `\assign::save_grade()`, which means the standard
  `\mod_assign\event\submission_graded` event fires, completion
  tracking and notification observers react, and feedback is
  dispatched to whichever feedback plugins the assignment has enabled.
- `task_reset::reset_grading_task()` keeps the direct DML on
  `task_adhoc` but the doc comment now explicitly justifies it
  (Moodle 4.5 has no public "reset adhoc task" API) and references
  the upstream gap.

### Added

- `LICENSE` file at the repo root, with explicit notice of bundled
  third-party libraries and their licenses.
- `thirdpartylibs.xml` cataloguing `smalot/pdfparser` and
  `symfony/polyfill-mbstring` with version, license and provenance, as
  Moodle Plugin Directory expects.
- `CHANGELOG.md` (this file).

### Removed

- 4 dev-only CLI scripts (`attach_file.php`, `build_prompt.php`,
  `extract.php`, `insert_test_submission.php`). They were debugging
  helpers that should never have shipped to production sites. The
  three operational scripts (`grade.php`, `enqueue.php`,
  `validate-privacy.php`) stay.

### Fixed

- All remaining PHPCS errors in tracked source files. Auto-fixed via
  `phpcbf` (85 fixes) plus manual cleanup of class docblocks and
  constant docblocks. Now: **0 errors** against the `moodle` rule
  set on tracked files.
- Inconsistent `@copyright` header in `db/access.php` ("Hernán" →
  "Hernán Díaz").
- `db/install.xml` `VERSION` attribute updated to match the current
  plugin version (was stuck at 2026051403).

## [v1.0.3-beta] — 2026-05-16

### Added

- PDF support via vendored `smalot/pdfparser` v2.12.5 +
  `symfony/polyfill-mbstring` v1.37.0 (~700 KB total, LGPL-3.0 and
  MIT respectively, bundled with their LICENSE files in
  `thirdparty/vendor/`).
- New `\local_aigrader\extractor\pdf_extractor` with two defensive
  caps:
  - `MAX_FILESIZE_BYTES = 5 MB` — parsing an 11 MB PDF needed
    ~1 GB of PHP memory in the v1.0.2 pilot probe; the cap protects
    shared Moodle hosts from OOM crashes.
  - `MIN_USEFUL_CHARS = 200` — image-only / scanned / damaged PDFs
    return null and fall through to the `needs_manual_review` path.
- `FORMAT_PDF` constant on `extraction_result`.
- 5 new PHPUnit tests for the PDF extractor (well-formed, oversized,
  malformed, near-empty, empty).

### Changed

- Dispatcher routes `.pdf` to the new extractor before the
  fall-through to "unsupported".
- `needs_review` reason message updated: "all submitted files are
  unparseable" replaces "all submitted files are in unsupported
  formats" (PDFs are technically supported now; only specific PDFs
  fall through).

### Measured impact

- Soto Rodríguez (ipynb + 776 KB PDF): grade 8.40 → 8.80 (closer to
  teacher's 9.0). Tokens 6.7 K → 10.1 K.
- Suárez Recio (12 MB PDF): still `needs_review` (over the 5 MB
  safety cap). Future v1.1.0 can extend to ~50 MB via Ghostscript
  streaming.

## [v1.0.2-beta] — 2026-05-16

### Fixed

- **Locale-safe grade input.** `review.php` was using `format_float()`
  to render the AI's proposed grade into the `value=""` attribute of
  an `<input type="number">`. On non-English locales (Spanish, French,
  German…) `format_float()` emits "9,20" with a comma decimal
  separator, which HTML5 rejects, leaving the field blank. Teachers
  on Spanish Moodle saw every AI proposal as an empty grade field.
  Switched to `number_format($v, 2, '.', '')` at the call site.
- **False 0/10 on unprocessable submissions.** When a student
  submitted only a `.pdf` (a format the plugin couldn't read in
  v1.0.2 and earlier), the dispatcher emitted an `[unsupported]`
  placeholder and the LLM graded the placeholder as 0/10. Now the
  dispatcher detects "every uploaded file is in an unsupported
  format" and routes the submission to a new `needs_manual_review`
  state with a clear orange banner, **without** calling the LLM
  (zero tokens consumed on the failure path).

### Added

- New `extraction_result::needs_review()` factory + `is_needs_review()`
  query.
- `grading_result::mark_needs_review()`.
- `manager::mark_submission_needs_review()`.
- 8 new PHPUnit tests for the dispatcher's `decide_outcome()` helper.

## [v1.0.1-beta] — 2026-05-15

### Added

- **Classified error banner** at the top of the AI Grader Pro
  management page when LLM grading fails. Six classified error kinds
  (`payload_too_large`, `unauthorized`, `rate_limited`,
  `provider_error`, `network_error`, `parse_error`) plus an unknown
  catch-all, each with localised headline + body + suggested action
  + per-student "Retry now" button + collapsible raw error for
  support.
- `/local/aigrader/retry.php` endpoint with CSRF + capability check.
- `\local_aigrader\task_reset` helper that resets a failed adhoc task
  in place instead of duplicating it.
- 25 new PHPUnit tests (17 for the classifier including the actual
  Groq 413 string as a regression case, 8 for the parser).

### Changed

- `ipynb_extractor` now caps each cell output at 30 lines / 1500
  chars with head+tail truncation. A whole-notebook 40 K char cap
  acts as a safety net.
- Review page: lists attached submission files with download links;
  the "submission as seen by the AI" panel uses native `<details>`
  + `<summary>` instead of Bootstrap collapse (Moodle 4.5 doesn't
  load the Bootstrap collapse AMD module on `incourse` pagelayout).
- 25 duplicate i18n keys per language file removed (English and
  Spanish sets now exactly symmetric, 140 keys each).

### Fixed

- Output parser regression from v0.10.2's phpcs cleanup:
  `parsed_proposal::success()` parameters were renamed from
  `criterion_scores` → `criterionscores` but `output_parser::parse()`
  was still passing named arguments with the old underscore names,
  throwing "Unknown named parameter" on every successful grading.
  Caught now by the new happy-path parser tests.

## [v1.0.0-beta] — 2026-05-14

### Added

- First pilot-ready release.
- Full Privacy provider (GDPR Art. 15 + Art. 17 + AI Act audit).
- 11 PHPUnit tests for the privacy provider.
- 2 Behat scenarios for the assignment-edit form hook (the most
  fragile integration point).
- 0 PHPCS errors against `moodle` + `moodle-extra` rule sets.

[v1.0.4-beta]: https://github.com/HernanDiaz/moodle-local_aigrader/releases/tag/v1.0.4-beta
[v1.0.3-beta]: https://github.com/HernanDiaz/moodle-local_aigrader/releases/tag/v1.0.3-beta
[v1.0.2-beta]: https://github.com/HernanDiaz/moodle-local_aigrader/releases/tag/v1.0.2-beta
[v1.0.1-beta]: https://github.com/HernanDiaz/moodle-local_aigrader/releases/tag/v1.0.1-beta
[v1.0.0-beta]: https://github.com/HernanDiaz/moodle-local_aigrader/releases/tag/v1.0.0-beta
