# Changelog

All notable changes to `local_aigrader` (AI Grader Pro) are documented
here. The format follows [Keep a Changelog](https://keepachangelog.com/),
versions follow Moodle's `YYYYMMDDXX` plugin-version convention with a
parallel semantic-style release name.

## [v1.0.15-beta] — 2026-05-17

### Fixed

- **`ai_proposed` and `published` no longer share the same colour.**
  Both states rendered with `bg-success` (green) in the counter chips
  and table badges since v1.0.6. The pilot teacher correctly flagged
  this as confusing: semantically the two states are opposites —
  "there is a proposal waiting for you to review and act on" vs
  "this row is done and locked in the gradebook" — and giving them
  the same visual cue meant the teacher had to read each chip label
  to tell them apart.

  `ai_proposed` now uses `bg-info` (cyan in Moove's resolved palette,
  `rgb(0, 129, 150)`) — "informational, a proposal awaits review".
  `published` keeps `bg-success` (`rgb(100, 164, 78)`) — "done, in
  gradebook". The colours apply in both the counter chips at the
  top of the manage page and in the per-row badges inside the
  table.

### Changed

- **`pending_ai` badge moved from `bg-info` to `bg-light text-dark`.**
  v1.0.14 had `pending_ai` in cyan, which would have clashed with
  the new `ai_proposed` cyan once v1.0.15 landed. `pending_ai` is a
  transient state (a few seconds during the in-flight LLM call) and
  the auto-refresh polling notice above the table already surfaces
  the activity — the badge can be subtle. Light gray with dark text
  reads as "this row is paused" without competing for attention.

## [v1.0.14-beta] — 2026-05-17

### Fixed

- **Manage page spacing was 0 in practice.** v1.0.8 added Bootstrap
  `gap-3` / `gap-2` / `row-gap-2` utility classes to the counter
  chips row, the "Con seleccionadas..." bulk bar and the "Mostrar
  por página" form, and v1.0.9 kept them through the table_sql
  refactor. Both rounds verified the classes were present in the
  HTML and assumed the spacing was applied. Pilot teacher review
  flagged the controls still looked "todo junto"; runtime check via
  `getComputedStyle(el).gap` returned `"normal"` (i.e. 0) for all
  three containers — the Moove theme ships a Bootstrap 5 build
  without the `gap-*` utility classes, so the rules degraded to "no
  gap, only the chip's own padding".

  This commit adds the plugin's own `styles.css` (Moodle auto-loads
  it) with explicit `gap` rules keyed by the existing wrapper class
  names: `.aigrader-counter { gap: 1rem; row-gap: 0.5rem; }`,
  `.aigrader-bulk-bar { gap: 1rem; }`,
  `.aigrader-perpage-form { gap: 0.5rem; }`. Runtime check after
  this fix returns `gap: 8px 16px` and `gap: 16px` respectively
  (the resolved pixel values), so the rules now actually take
  effect regardless of which theme the site is on.

### Added

- Small spacing rule for the "Acción" column buttons in
  `.aigrader-manage-table`: `margin-right: 0.4rem` between adjacent
  buttons / forms, reset to 0 on the last child. Previously the
  "Revisar" link had `me-1` (0.25rem) applied directly but the
  inline form holding "Calificar con IA" had no margin at all, so
  the two buttons read as one chunk. Same fix as above — the
  Bootstrap utility classes weren't being applied, plugin CSS now
  carries the responsibility.

## [v1.0.13-beta] — 2026-05-17

### Added

- **Microcopy under the "Entrega tal y como la vio la IA"
  disclosure** in `review.php`. The section showed a raw `<pre>` with
  the LLM's input text but no explanation of what it was for; pilot
  feedback described it as "wall of text without explanation". Three
  new pieces of content render inside the `<details>` block:
    1. A plain-language paragraph explaining what the section is and
       when the teacher would consult it. Kept deliberately short:
       earlier drafts mentioned head/tail truncation of long cells
       but the pilot review pushed back on the jargon and on the
       extra sentence in general, so the final copy only flags that
       "some formats are not processed (very large PDFs, images)".
    2. A one-line metadata note "{X.X} KB de texto extraídos." so the
       teacher can sanity-check at a glance whether the AI actually
       received a meaningful chunk of the submission.
    3. The extraction warnings (skipped files, truncations) now render
       under a labelled "Avisos sobre la extracción:" sub-heading as
       a bulleted list. Previously they emitted as anonymous muted
       divs after the `<pre>` and were easy to miss.

  All three render in the happy path (`$extraction->is_ok()`). For
  the `needs_review` / `error` path the microcopy still shows; the
  size and warnings header are suppressed because the underlying
  error message already contains the full reason (showing it twice
  would be redundant).

### Lang strings

- ES + EN added: `review_seen_by_ai_help`, `review_seen_by_ai_size`,
  `review_seen_by_ai_warnings`.

## [v1.0.12-beta] — 2026-05-17

### Changed

- **Criterion-score labels in `review.php` are now humanised.** The
  per-criterion list under "Puntuación por criterio (de la IA,
  informativa)" used to render the raw slug names that the LLM emits
  in its structured JSON output:
    > - requisitos_imprescindibles: 8,00 / 10
    > - configuracion_y_justificacion_explicita_de_hiperparametros: 7,00 / 10
  These slugs are deliberately snake_case + ASCII-only — the LLM
  prompt instructs the model to emit machine-readable identifiers so
  the PHP parser doesn't trip on accents or whitespace. Good for the
  parser, ugly for the teacher.

  Now the slugs are transformed for display via
  `local_aigrader_humanize_criterion_slug()`:
    > - Requisitos imprescindibles: 8,00 / 10
    > - Configuracion y justificacion explicita de hiperparametros: 7,00 / 10
  Underscores become spaces, first letter uppercased, rest left as-is.

### Trade-off

- Accents are not recovered (the slug never had them in the first
  place — "Configuracion" not "Configuración"). The teacher reads it
  as "Configuracion y justificacion ..." and understands. Fully
  pretty labels would require either (a) maintaining a per-criterion
  dictionary keyed by slug, or (b) asking the LLM to return both a
  machine-safe slug and a display label in the JSON. Both options
  complicate the prompt and parser for marginal gain, especially
  given the criteria are defined by the teacher in their own
  language anyway.

## [v1.0.11-beta] — 2026-05-17

### Changed

- **Bulk action label** in the "Con seleccionadas..." dropdown:
  `'Publicar nota propuesta tal cual'` → `'Publicar nota propuesta'`
  (en: `'Publish proposed grade as-is'` → `'Publish proposed grade'`).
  The "tal cual" / "as-is" qualifier was redundant — every bulk
  publish action by definition publishes whatever the AI proposed
  unchanged (if the teacher wanted to edit they would use Revisar
  on the row). Removing it shortens the dropdown and makes the
  action read more naturally. The intermediate confirmation page
  warning still mentions "sin editar" so the implication is
  preserved where it matters most.
- **Per-row "Revisar →" button label** in the action column:
  `'Revisar →'` → `'Revisar'` (en: `'Review →'` → `'Review'`). The
  trailing arrow was decorative leftover from an early sketch; it
  didn't carry any "this navigates somewhere" semantic that the
  surrounding link styling and the button shape don't already
  communicate. Drops noise from the action column.

## [v1.0.10-beta] — 2026-05-17

### Changed

- **`review.php` meta-info line no longer shows the provider name.**
  Previously the footer rendered `"Propuesta hecha el … · por openai
  (meta-llama/llama-4-scout-17b-16e-instruct)"`. The `"openai"` was
  misleading: in practice every row was logged with `llm_provider =
  'openai'` because the plugin uses Moodle 4.5's `aiprovider_openai`
  to speak to whatever LLM endpoint the site has configured —
  typically Groq via its openai-compatible API, sometimes the real
  OpenAI, sometimes a local runtime. Showing `"openai"` added no
  signal and confused the pilot teacher into thinking proposals
  came from OpenAI when in fact they came from Groq's Llama-4 Scout.

  Now: `"Propuesta hecha el … · por
  meta-llama/llama-4-scout-17b-16e-instruct"`. The model slug uniquely
  identifies the LLM family + variant, which is the actually
  informative bit.

  The provider field stays untouched in `local_aigrader_log` for
  forensic queries and audit-log fidelity — only the user-facing
  string changed. Lang keys `review_proposed_by` (es + en) simplified
  from `'{$a->provider} ({$a->model})'` to `'{$a}'`.

## [v1.0.9-beta] — 2026-05-17

### Changed

- **Manage page now uses `\table_sql` for paginated, sortable rendering.**
  Previously (v1.0.5 - v1.0.8) `manage.php` loaded every submission for
  the assign in a single un-paginated `html_table`. That works at the
  microcredencial scale (12-30 alumnos) but degrades at 200+ rows and
  was not the Moodle Plugin Directory expectation — every native
  grading page in core uses `\flexible_table` / `\table_sql`. Pilot
  user asked the right question ("en el moodle normal cuando te
  salen las prácticas tienes paginación no?") and the answer was
  "yes, and we should match it".

  The refactor:
  - New class `\local_aigrader\output\manage_table` extends
    `\table_sql`. Defines six columns (checkbox, student, submitted_at,
    status, grade, action). Four of them are sortable: student
    (last/first name), submitted (timemodified), status (raw
    ai_status), grade (proposed_grade). Sortable mapping is done via
    `get_sql_sort()` override so the column display names don't have
    to match SQL aliases.
  - Per-column renderers live as `col_*` methods on the class.
    `local_aigrader_render_status()` and `local_aigrader_info_icon()`
    moved from manage.php global functions into static methods on
    `manage_table` for clean encapsulation.
  - `manage.php` no longer fetches the full row list. Two queries
    instead:
      1. A cheap GROUP BY (~10ms on any cohort size) that returns the
         per-status raw counts → fills the chip totals AND the
         `pending_ai` watcher for the auto-refresh.
      2. The paginated row query, run by `\table_sql::out()` itself.
    Plus a third query when `counts['problems'] > 0` for the error
    banner (which still needs the full set of error rows, not the
    visible page only).

### Added

- **"Mostrar X por página" selector** above the table, with options
  10 / 25 / 50 / 100 / "Todas". 25 is the default. Submits via plain
  GET (changes the `?perpage=` URL param and the table re-renders).
  Mirrors the equivalent control on mod_assign's grading view.
- **Pagination controls** (top + bottom of the table) provided for
  free by `\table_sql`. Style matches the rest of Moodle.
- **Column sorting** via clickable headers (alumno / entregado /
  estado IA / nota propuesta). The current sort column shows an
  ascending/descending arrow.

### Notes / trade-offs

- **Select-all-across-pages is intentionally NOT implemented.** Matches
  mod_assign's behaviour: the standard "With selected..." dropdown on
  Moodle's grading view also only acts on visible rows. A teacher who
  needs to bulk-act on a 200-row cohort bumps "Mostrar X por página"
  to "Todas" first. Familiar Moodle pattern, no new mental model.
- **Filter persists with pagination**: clicking a counter chip sets
  `?filter=<bucket>` and the table SQL applies the filter at the
  WHERE clause level (not array_filter in PHP) so it works correctly
  with LIMIT/OFFSET.
- **Counter chips show cohort-wide totals** even when a filter is
  active or pagination hides rows. Source of truth is the GROUP BY
  counter query, separate from the table SQL.

### Tests

- 85 tests / 199 assertions still green. No new tests added: the
  refactor moves rendering into a `\table_sql` subclass whose
  per-column output is best validated by the existing Behat / live
  walkthrough rather than phpunit.

### Verification (live, prof_demo on course 3, cmid=5)

- `?perpage=10` → 2 pages, 10 + 3 rows. Pagination bar shows `1 2 »`.
- `?perpage=10&page=1` → page 2 shows Rodríguez Pérez, Soto
  Rodríguez, Suárez Recio (last 3 rows).
- `?tsort=grade&tdir=4` → grades sorted ascending (7.40 → 8.53).
- `?filter=problems` → only Suárez Recio (Formato no soportado).
- All previously-working features (counter chips, bulk dropdown,
  per-row Revisar / Calificar con IA, info icon ⓘ, confirm() on
  published re-grade) still render and behave correctly.

## [v1.0.8-beta] — 2026-05-17

### Added

- **Inline confirmation on per-row "Calificar con IA" for
  already-published rows.** Pilot feedback noted that the button was
  rendered on Publicada rows too, which is correct (a teacher might
  legitimately want to re-grade after a student re-submission), but
  the button gave no warning before consuming provider tokens and
  flipping the row's badge back from "Publicada" to "Propuesta IA".
  The per-row submit now carries a native `confirm()` handler when
  the row is in `published` state, showing the localised message
  `confirm_regrade_published`:
    > "Esta entrega ya está publicada. ¿Recalificar con IA? La
    >  nota actual del cuaderno de calificaciones no cambiará,
    >  pero el estado volverá a «Propuesta IA» hasta que vuelvas
    >  a aprobar."
  Cancel keeps the row as-is. The bulk path's intermediate
  confirmation page is the equivalent safety gate for cohort-level
  re-grading.

### Changed

- **More breathing room in the counter chips and bulk bar.** The
  v1.0.6 status counter and "Con seleccionadas..." bar used
  Bootstrap `gap-2` (0.5rem) which the pilot teacher reported as
  "todo junto" — visually the chips read as one continuous strip
  and the bulk bar's label/select/button looked glued together.
  Bumped both to `gap-3` (1rem), added `row-gap-2` on the
  flex-wrap'd counter so the vertical break on narrow screens also
  gets a gap, and bumped the counter's bottom margin to `mb-4`
  (1.5rem) so it doesn't crowd the bulk bar below.

## [v1.0.7-beta] — 2026-05-17

### Fixed

- Two missing accents in user-visible Spanish strings on the review
  form: `Puntuacion por criterio` → `Puntuación por criterio` and
  `Justificacion (visible para el alumno)` → `Justificación (visible
  para el alumno)`. Spotted during the v1.0.6 in-vivo walkthrough.

### Changed

- Internationalised the extractor dispatcher. The skip-reason strings
  that surface in the teacher UI as the inline detail next to the
  "Formato no soportado" badge were hardcoded English regardless of
  the teacher's language preference. They now route through
  `get_string()` and have Spanish + English variants:
    - The needs-review preamble (`extract_needs_review_preamble`)
    - The skipped-list separator (`extract_skipped_list`)
    - The seven per-file reasons (`extract_reason_*` for docx
      malformed, ipynb parse error, pdf too large, pdf no text, zip
      empty, no extension, unknown extension)
    - The truncation warning (`extract_truncation_warning`)
    - The "no soportado" / "unsupported" marker
      (`extract_skip_marker`)
  Effect: a teacher with `lang=es` who triggers grading on a
  PDF-only submission now sees:
    > Formato no soportado ⓘ
    > "Todos los archivos enviados son ilegibles. Formatos
    >  soportados: .txt, .md, .docx, .ipynb, .pdf (≤5 MB, con texto
    >  extraíble), .zip y archivos de código. Saltados:
    >  MACE_project-JorgeSuarezRecio.pdf no soportado: pdf demasiado
    >  grande (11.1 MB; máximo 5.0 MB — ver README del plugin)."
- The English text inside the prompt itself (file headers, the
  "[This file could not be processed...]" placeholder) is kept in
  English on purpose — the LLM benefits from English instruction
  markers in its instruction-following training and never reads the
  teacher's UI strings.

- **Long skip detail collapsed into a hover-tooltip info icon.**
  Previously a row with `unsupported_format` rendered the full
  multi-line reason ("All submitted files are unparseable. Supported:
  .txt, .md...") inline next to the badge, breaking the grid layout
  to 2-3 lines. Now the badge stays one line and the detail moves
  into a small ⓘ (U+24D8) icon with the message in its HTML `title`
  attribute. Browser-native tooltip on hover → works in every theme
  without depending on Bootstrap popover/tooltip JS being
  initialised. Same treatment applied to `error` status badges
  (using the existing `error_classifier::summarize_raw` summary as
  the tooltip body so provider marketing tails like Groq's "Upgrade
  to Dev Tier..." URL are stripped).
- New `local_aigrader_info_icon()` helper in manage.php encapsulates
  the icon rendering (cursor: help, tabindex=0 for keyboard
  accessibility, aria-label = full detail for screen readers).

### Notes

- The `decide_outcome()` warning matcher accepts BOTH the
  localised marker (`get_string('extract_skip_marker', ...)`) and
  the legacy English string `'unsupported'` so warnings emitted by
  older code paths or stored in pre-1.0.7 rows are still picked up.
- Existing rows in `local_aigrader_submission` keep their stored
  English `error_message` until the teacher re-triggers grading on
  them (clicking "Calificar con IA" re-runs the dispatcher and
  overwrites the row with the localised reason). There is no
  retroactive migration — pre-1.0.7 message text is left in place.

### Tests

- All 85 tests / 199 assertions green
  (`local/aigrader/tests/` test suite).
- Dispatcher outcome tests (`dispatcher_outcome_test.php`) still
  pass because Moodle's phpunit env runs in English by default;
  asserting on `'unparseable'` matches the English form of
  `extract_needs_review_preamble`, and the legacy marker fallback
  catches the `'research.pdf unsupported: pdf'` fixture warnings.

## [v1.0.6-beta] — 2026-05-17

### Changed

- Bulk dropdown simplified to **two** actions instead of four. Pilot
  feedback ("ahora es demasiado confuso") flagged that the v1.0.5
  matrix (publish / grade / regrade / mark-manual) was noise without
  added power — the teacher kept asking what was the difference between
  grade and regrade, and mark-manual produced no visible effect.
  Remaining actions:
  - **Publicar nota propuesta tal cual** (destructive, confirmation
    page)
  - **Calificar con IA** (unified first-grade + re-grade)
- Per-row button is now always labelled "Calificar con IA"
  (previously "Calificar con IA" or "Recalificar con IA" depending on
  current state). The dispatcher figures out the right semantics per
  row internally.
- `dispatcher::ACTION_GRADE_AI` eligibility expanded to accept any
  state EXCEPT `pending_ai` (don't double-queue) and
  `unsupported_format` (LLM can't recover from an unparseable file).
  Re-grading a `published` row builds a fresh proposal but does NOT
  touch the gradebook until the teacher publishes the new proposal.

### Removed

- `dispatcher::ACTION_REGRADE_AI` — merged into `ACTION_GRADE_AI`.
- `dispatcher::ACTION_MARK_MANUAL` — single-row "Rechazar (calificar
  manualmente)" button on review.php remains intact for individual
  decisions; the bulk equivalent was dropped.
- Related `bulk_action_*`, `bulk_warning_*`, `bulk_confirm_button_*`
  and `bulk_skip_*` lang strings cleaned up.
- `btn_regrade_with_ai` lang string (no longer used by the per-row
  button).

### Added

- **Status counter + clickable filter chips** above the table:
  `13 entregas · 10 con propuesta IA · 1 revisadas · 1 publicadas ·
  1 con problemas · 0 sin calificar IA`. Each chip filters the table
  to that bucket; clicking the active chip clears the filter.
  Implemented via `?filter=<bucket>` URL param, persisted on reload.
  Buckets are user-facing (not raw statuses): `problems` collapses
  `error` + `unsupported_format`, `none` collapses NULL + `pending_ai`.
- "Mostrar todas" link rendered when a filter is active.
- Empty-state notification when no rows match the filter.

### Tests

- `tests/bulk_dispatcher_test.php` reduced from 27 → 19 tests:
  - 12 cover the new (action × status) matrix for the 2 remaining
    actions (vs the original 4 actions × 7 states).
  - 1 new regression test (`test_removed_actions_are_classified_as_unknown`)
    guards against silent acceptance of `regrade_ai` or `mark_manual` —
    a stale browser tab or bookmarked URL submitting one of these
    values must NOT silently take the grade_ai path.
  - `test_action_list_is_minimal` pins the dropdown size to 2 actions
    so any future addition is a deliberate test-change.

### Notes

- `PARAM_ALPHAEXT` (not `PARAM_ALPHA`) for the `?filter=` param: the
  bucket keys contain underscores (e.g. `ai_proposed`) and
  `PARAM_ALPHA` would silently strip them, making the filter look
  broken to the teacher.
- Explicit `text-white` on `.bg-primary` / `.bg-success` chips: some
  Moodle themes (notably Moove) don't carry Bootstrap 5's default
  rule that puts white text on dark badge backgrounds, which left the
  count invisible. Spell it out so the chip stays readable
  independent of theme.

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
