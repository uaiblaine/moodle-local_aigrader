# AI Grader Pro (`local_aigrader`)

AI-assisted grading for Moodle 4.5+ assignments, with the teacher always in
the loop. The plugin proposes a grade and structured feedback using a Large
Language Model accessed through Moodle's AI Subsystem; the teacher reviews,
edits if needed, and decides whether to publish. Nothing reaches the
gradebook without an explicit teacher click.

[![Tests](https://img.shields.io/badge/PHPUnit-66%20tests%20passing-brightgreen)](#tests)
[![Code style](https://img.shields.io/badge/phpcs-0%20errors-brightgreen)](#code-quality)
[![License](https://img.shields.io/badge/license-GPL--3.0--or--later-blue.svg)](LICENSE)

---

## What it does

- Teachers write evaluation criteria in plain language on the assignment
  edit page ("Eres profesor de la microcredencial de IA. Evalúa esta
  práctica final…"). No rigid rubric grid required — the LLM is good at
  reading nuanced prose.
- When a student submits, the plugin extracts text from their files
  (`.txt`, `.md`, `.docx`, `.ipynb`, `.zip`, `.pdf` up to 5 MB, plus
  source-code files in 20+ languages), builds a prompt combining the
  teacher's criteria with the extracted content, and calls the
  configured LLM provider via Moodle's AI Subsystem.
- The proposal — a grade, per-criterion scores, strengths, areas for
  improvement, and a narrative justification — lands in the teacher's
  panel.
- The teacher reviews the proposal, modifies anything they want, and
  either **Approves & publishes** (grade + feedback go to the gradebook,
  the student sees them, the `mod_assign\event\submission_graded` event
  fires for completion / notifications / observers), or **Rejects**
  (grades manually in Moodle's standard UI).
- Every action is recorded in an append-only audit log with the
  teacher's id, the prompt hash, the model used, token counts and
  edits — designed to satisfy the EU AI Act's audit requirements for
  high-risk educational AI systems.

### Human-in-the-loop guarantee

By design, the AI never writes a grade to the gradebook directly. The
plugin stores its proposal in its own table (`local_aigrader_submission`)
with status `ai_proposed`; the row only becomes `published` after the
teacher clicks **Aprobar y publicar**. The `final_grader` column always
contains the teacher's `user.id`, never a system id.

## Features

- 🧠 **Multi-LLM** via Moodle's AI Subsystem — OpenAI, Azure OpenAI,
  Groq, any provider that implements the AI provider contract.
- 📄 **File-format coverage**: online text, plain text, Markdown, Word
  (`.docx`), Jupyter notebooks (`.ipynb`), PDF (text-based, up to 5 MB),
  ZIP archives (recursed), and 20+ source-code languages.
- 🗜️ **Notebook truncation**: Jupyter outputs longer than 30 lines /
  1500 chars per cell get head+tail truncation with a marker. A Fashion-
  MNIST notebook with 50 epochs × 1875 batches still fits comfortably
  in a 30 K-TPM LLM budget.
- 🛡️ **Manual-review fallback**: submissions whose attached files are
  entirely in unsupported formats (e.g. only a PDF that is too large or
  image-only) are flagged as `needs_manual_review` with a clear banner.
  No fake 0/10 grades are produced.
- 🚦 **Classified error banner**: when an LLM call fails (rate limit,
  payload too large, auth failure, network error, parse error), the
  teacher sees a localised banner with the cause, a suggested action,
  and a per-student "Retry now" button. No log-diving required.
- 🌐 **i18n**: English and Spanish out of the box, with symmetric key
  coverage. Other languages welcome via PR.
- 🛡️ **Privacy provider** implementing GDPR Art. 15 (data export),
  Art. 17 (deletion) and the AI Act Annex III audit trail.
- ✅ **Tested**: 66 PHPUnit tests + 2 Behat scenarios covering the most
  fragile integration point (the hook into `mod_assign`'s edit form).

## Requirements

- Moodle **4.5 LTS** or later (`$plugin->requires = 2024100700`).
- PHP **8.2** or later.
- An LLM provider configured through *Site administration → AI →
  Providers*. The plugin uses the `generate_text` action of the AI
  Subsystem so any provider exposing it works (OpenAI, Azure OpenAI,
  Groq via the OpenAI-compatible endpoint, etc.).
- ~~Composer~~ — not required at runtime. All third-party libraries
  used by the plugin (`smalot/pdfparser`, `symfony/polyfill-mbstring`)
  are vendored under `thirdparty/vendor/` with their license files. See
  [`thirdpartylibs.xml`](thirdpartylibs.xml).

## Installation

### From the Moodle Plugins Directory (recommended)

1. Site administration → Plugins → Install plugins.
2. Search for "AI Grader Pro", click Install.
3. Confirm the upgrade prompt.

### Manual

```bash
cd /path/to/moodle/local
git clone https://github.com/HernanDiaz/moodle-local_aigrader.git aigrader
# or unzip the release ZIP into local/aigrader/
```

Then visit `/admin/index.php` as a site administrator to apply the
upgrade.

## Configuration

### 1. Pick an LLM provider

Site administration → AI → Providers → enable a provider, paste the API
key, set the default model. The plugin uses whatever the AI Subsystem
returns; no provider lock-in.

### 2. Per-assignment setup

Open any assignment → edit settings → expand **AI Grader Pro**:

- Tick **Enable AI Grader Pro on this assignment**.
- Write your **Evaluation criteria** in plain prose. Be specific.
  Example:

  ```
  Eres profesor de la microcredencial de IA.
  REQUISITOS IMPRESCINDIBLES (si falla cualquiera, max 5/10):
  1. División train/val/test estrictamente disjunta.
  2. Código compilable y ejecutado con métricas razonables.
  3. El test no se usa durante el entrenamiento.

  ASPECTOS DE CALIDAD (suman sobre los requisitos):
  - Reproducibilidad (1.0)
  - Early stopping (0.75)
  - ...
  ```

- Save the assignment.

### 3. Triggering grading

When a student submits, the plugin enqueues an adhoc task that calls
the LLM on the next cron tick (≤60 s on a healthy site). The teacher
can also trigger grading manually from the **AI Grader Pro** tab on the
assignment.

## Usage flow

1. Student submits files (or online text) as usual.
2. Plugin enqueues `\local_aigrader\task\grade_submission`.
3. Cron runs it: extract → build prompt → call LLM → parse response →
   store proposal with `status = 'ai_proposed'`.
4. Teacher visits the **AI Grader Pro** tab, sees the list of proposals.
5. Teacher clicks **Revisar →**, sees the LLM's grade, strengths,
   improvements, justification. All editable.
6. Teacher clicks **Aprobar y publicar** — grade lands in the gradebook
   via `\assign::save_grade()`, `submission_graded` event fires,
   student sees the result.

## Capabilities

| Capability                 | Default                       | What it allows                                          |
|----------------------------|-------------------------------|---------------------------------------------------------|
| `local/aigrader:use`       | editingteacher, manager       | Use AI grading on an assignment submission              |
| `local/aigrader:configure` | editingteacher, manager       | Configure criteria, model, language for an assignment   |
| `local/aigrader:viewlog`   | manager                       | View the full audit log (system-wide)                   |

## Privacy

The plugin transfers the student's submitted content (extracted as
plain text, including code) and the teacher's evaluation criteria to
the LLM provider configured in Moodle's AI Subsystem. The provider may
process this in a jurisdiction other than the EU depending on the
institution's choice; the site administrator is responsible for signing
a DPA with the chosen provider.

The plugin's Privacy provider implements:

- `get_contexts_for_userid`
- `export_user_data`
- `delete_data_for_all_users_in_context`
- `delete_data_for_user`
- `delete_data_for_users`
- `get_users_in_context`

All audit logs (`local_aigrader_log`) retain the prompt text and model
response so the teacher can review what the AI saw and how it responded
— required for the AI Act audit trail. Personal data in the logs is
covered by Moodle's standard user-data deletion flows.

## Tests

```bash
# PHPUnit (in a development Moodle install with PHPUnit initialised)
vendor/bin/phpunit --testsuite local_aigrader_testsuite

# Behat (with a Selenium grid running)
vendor/bin/behat --tags @local_aigrader
```

Current status: 66 PHPUnit tests + 2 Behat scenarios, all passing.

## Code quality

```bash
vendor/bin/phpcs --standard=moodle local/aigrader/
```

Current status: 0 errors against the `moodle` and `moodle-extra` rule
sets. Warnings are acknowledged and documented per-file where they
exist (mostly comment-separator cosmetics and long lines in lang
strings).

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the release history. Highlights:

- **v1.0.4-beta** — Plugin Directory submission readiness: LICENSE,
  `thirdpartylibs.xml`, dev CLI scripts removed, phpcs cleanup, README
  rewrite, `\assign::save_grade()` instead of direct DML on
  `assign_grades`.
- **v1.0.3-beta** — PDF support via vendored `smalot/pdfparser`.
- **v1.0.2-beta** — Locale-safe grade input (fixes blank grade field on
  non-English sites), `needs_manual_review` for unprocessable
  submissions.
- **v1.0.1-beta** — Classified error banner, ipynb output truncation,
  retry in-place, i18n dedupe.
- **v1.0.0-beta** — First pilot-ready release with full Privacy
  provider and Behat coverage.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).

Third-party libraries bundled under `thirdparty/vendor/` retain their
own licenses (LGPL-3.0 for `smalot/pdfparser`, MIT for
`symfony/polyfill-mbstring`); both are one-way compatible with
GPL-3.0+.

## Support

- Issues: https://github.com/HernanDiaz/moodle-local_aigrader/issues
- The plugin is maintained by the original author. Commercial support
  contracts, custom rubric design, and managed-LLM-endpoint hosting
  are available — contact the maintainer through the repo.
