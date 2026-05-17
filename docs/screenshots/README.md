# Plugin Directory screenshots

Five canonical screenshots for the Moodle Plugin Directory listing
form at <https://moodle.org/plugins/manage.php>. The Directory accepts
up to ~6 screenshots per listing and orders them as uploaded; these
five tell the plugin's story end-to-end (overview → review flow →
safety net → setup → transparency) with intentional progression.

## How to capture

1. Apply the demo cohort rename (only needed once on a fresh demo
   site) via `cli/seed_test_course.php` followed by the rename
   script from the dev process — this turns the original pilot
   names into the fictional cohort (María García López, Carlos
   Martínez Ruiz, etc.) that is safe to publish.
2. Log in as `prof_demo` and use the Práctica Final course
   (`MICRO-IA-DEMO` or `IA-MC-PY` cmid=5 in our reference dev DB).
3. For each screenshot below: open the URL, follow the
   "framing" instructions, save the PNG to this folder with the
   filename specified.

PNG width should be **1568 px** (Moodle Plugin Directory's
recommended viewport for desktop screenshots). 16:9 or 16:10 aspect
ratio works well — the Directory thumbnails crop centred.

## Screenshot 1 — Hero / manage page overview

**File**: `01-manage-overview.png`

**URL**: `/local/aigrader/manage.php?cmid=<cmid>` (with at least 6-8
submissions of mixed status — `ai_proposed`, `published`,
`teacher_reviewed`, and one `unsupported_format` so the colour
palette renders fully).

**Framing**: scroll the page to the very top so the heading, the
counter chips, the bulk-action dropdown, the "Mostrar por página"
selector and the first 5-6 table rows are all visible. The
fictional cohort names should be readable in the Alumno column.

**What it shows**:
- The plugin's main hub for the teacher.
- Cohort summary (chip counters) at a glance.
- Bulk action ergonomics ("Con seleccionadas…" + Aplicar).
- Per-row status badges in 4 colours (cyan AI proposed,
  purple Teacher reviewed, green Published, yellow Unsupported
  format).
- Per-row action column: Revisar (green) + Calificar con IA
  (outline-secondary).

## Screenshot 2 — Review form (HITL gate)

**File**: `02-review-form.png`

**URL**: `/local/aigrader/review.php?submissionid=<id>` for any
submission in `ai_proposed` state.

**Framing**: scroll a couple of ticks down so the visible viewport
shows the tail of "Puntuación por criterio (de la IA, informativa)"
plus the "Nota y feedback propuestos (editables)" heading, the
Nota final field pre-filled with the AI's proposal, the Aciertos
textarea with bullets, and the start of the Mejorables textarea.

**What it shows**:
- The human-in-the-loop editing surface.
- AI's per-criterion scores (already humanised — first-letter
  capitalised, no underscores).
- Form fields pre-filled with the LLM's proposal, fully editable.

## Screenshot 3 — Bulk action confirmation

**File**: `03-bulk-confirm.png`

**URL**: triggered from `manage.php` — select 2-3 rows in mixed
states (so the skip summary has something to show), choose
"Publicar nota propuesta" in the dropdown, click Aplicar. The
browser will navigate to `/local/aigrader/bulk.php` with the
confirmation card.

**Framing**: scroll so the confirmation card is centred in the
viewport. Visible should be the action title ("Publicar nota
propuesta"), the warning paragraph, the "N entregas se procesarán."
line, the "Se saltarán:" amber block with the skip reasons, and
the "Sí, publicar" / "Cancelar" buttons.

**What it shows**:
- Safety pattern for destructive bulk actions.
- Honest skip-row reporting (no silent drops).
- Localised microcopy.

## Screenshot 4 — Per-assignment configuration

**File**: `04-assignment-config.png`

**URL**: `/course/modedit.php?update=<cmid>` for an assignment
where AI Grader Pro is already enabled.

**Framing**: expand the "AI Grader Pro" collapsible fieldset
(scroll near the bottom of the form, click the chevron) then
align the viewport so the visible block shows: the "Habilitar
calificación asistida por IA en esta tarea" checkbox at the top,
the "Criterios de evaluación" textarea with a real rubric example,
"Modelo (opcional)" empty, "Idioma del feedback (opcional)" select,
and the Save buttons.

**What it shows**:
- How the teacher sets up the plugin on an assignment.
- The natural-language rubric prompt (the prompt IS the
  configuration; no rigid rubric grid is required).
- Optional overrides (model, feedback language).

## Screenshot 5 — Transparency: submission as seen by the AI

**File**: `05-seen-by-ai.png`

**URL**: same as Screenshot 2 — `/local/aigrader/review.php?submissionid=<id>`.

**Framing**: expand the "Entrega tal y como la vio la IA"
disclosure (click on the summary). Scroll so the visible viewport
shows: the help paragraph, the "32.1 KB de texto extraídos." size
line, the `<pre>` block with the extracted text, and the start of
"Puntuación por criterio (de la IA, informativa)" below.

**What it shows**:
- The transparency feature — the teacher can see exactly what
  text the LLM received from the student's submission.
- Helps debug "the AI proposal seems off" by checking whether
  the right content actually made it into the prompt.
- Aligns with the EU AI Act right-to-explanation requirement.

## Filename convention

| File | Purpose |
|---|---|
| `01-manage-overview.png` | hero shot |
| `02-review-form.png` | HITL editing surface |
| `03-bulk-confirm.png` | safety confirmation |
| `04-assignment-config.png` | configuration |
| `05-seen-by-ai.png` | transparency / AI-Act |

The Moodle Plugin Directory orders screenshots by the upload
sequence, so the `0N-` numeric prefix is for our own bookkeeping
when re-uploading. Use the same filenames if regenerating —
keeps the listing stable.

## Anonymisation note

The demo cohort visible in these screenshots is **fictional**:
María García López, Carlos Martínez Ruiz, Laura Sánchez
Hernández, Diego Fernández Castro, Sofía Rodríguez Vega, Javier
Pérez Moreno, Carmen Gómez Álvarez, Roberto Jiménez Torres,
Elena Díaz Romero, Andrés Navarro Gil, Lucía Ramos Iglesias,
Miguel Ortega Serrano, Patricia Castillo Vargas. These are not
real users — they were generated specifically for public Plugin
Directory screenshots, replacing earlier pilot data that
contained realistic Spanish/LATAM names that should not appear
in a public listing. The replacement is permanent in our reference
dev DB; older pilot names are not preserved.
