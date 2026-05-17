<!--
Thanks for the PR! A few things before you submit:

- Read CONTRIBUTING.md once if you haven't ("What we are unlikely to accept").
- Do NOT bump version.php or update CHANGELOG.md — the maintainer does that at release time.
- CI must pass. Fix it before requesting review.
- Keep PRs small and focused. One concern per PR.
-->

## What this PR does

<!-- One paragraph. Why are we doing this? What changes for the user? -->

## Linked issue

<!-- Fixes #NNN / Closes #NNN / Refs #NNN. Open an issue first if non-trivial. -->

Fixes #

## Type of change

<!-- Pick one (or more) and delete the rest. -->

- [ ] Bug fix (non-breaking change that fixes an issue)
- [ ] New feature (non-breaking change that adds functionality)
- [ ] Breaking change (existing functionality changes — version bump implied at release)
- [ ] Documentation update (README, TESTPLAN, lang strings, docs/)
- [ ] Translation (new lang pack or fix to existing)
- [ ] Refactor / internal cleanup (no functional change)
- [ ] CI / tooling

## How I tested it

<!--
Be specific. "Manually tested" is not enough — say what.

Example:
- Ran `moodle-plugin-ci phpunit --fail-on-warning` — 87 tests pass, 0 failures.
- Ran `moodle-plugin-ci behat --profile chrome` — 3 scenarios pass.
- Manually: logged in as prof_demo, opened /local/aigrader/manage.php?cmid=5,
  selected 3 ai_proposed rows, ran "Publicar nota propuesta", verified all 3
  reached gradebook with correct grade.
-->

## Checklist

- [ ] CI passes locally (`phpcs --max-warnings 0`, `phpunit --fail-on-warning`, `mustache`, `validate`).
- [ ] New / changed PHP code has unit tests under `tests/`.
- [ ] New / changed UI flows have Behat scenarios under `tests/behat/`.
- [ ] New user-facing strings added to `lang/en/local_aigrader.php` (other langs handled at release).
- [ ] Schema changes (`db/install.xml`) come with `db/upgrade.php` block + savepoint.
- [ ] New tables / fields that store user data are reflected in `classes/privacy/provider.php` + `tests/privacy/`.
- [ ] No version.php bump in this PR (maintainer does it at release tag).
- [ ] No CHANGELOG.md edit in this PR (maintainer does it at release tag).
- [ ] The human-in-the-loop guarantee still holds — AI never writes to gradebook without a teacher click.
- [ ] No new runtime dependencies introduced (or strong justification provided).

## Screenshots / recordings (UI changes only)

<!-- Drop screenshots or a short screen-capture GIF here. -->

## Additional context

<!-- Anything reviewers should know — design decisions, alternatives you ruled out, etc. -->
