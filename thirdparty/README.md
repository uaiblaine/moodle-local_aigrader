# Third-party libraries bundled with AI Grader Pro

This directory contains third-party libraries vendored verbatim from
Composer so the plugin works on Moodle sites without requiring
administrators to install or run Composer themselves.

## Contents

### `vendor/smalot/pdfparser` (v2.12.5)

Pure-PHP PDF text extractor used by
`\local_aigrader\extractor\pdf_extractor` to handle student submissions
in `.pdf` format (typically LaTeX-rendered reports for the "alternative
project" path).

- Upstream:  https://github.com/smalot/pdfparser
- License:   GNU Lesser General Public License v3.0 (see
             `vendor/smalot/pdfparser/LICENSE.txt`)
- Compatibility note: LGPL-3.0 is one-way compatible with Moodle's
  GPL-3.0+, so the bundling is legally fine. We do not modify the
  library and we honour its notice files.

### `vendor/symfony/polyfill-mbstring` (v1.37.0)

Transitive dependency of `smalot/pdfparser`. Provides `mb_*` functions
on PHP installs where the `mbstring` extension is missing. Effectively
a no-op on the vast majority of Moodle installs (which ship `mbstring`)
but bundled for completeness.

- Upstream:  https://github.com/symfony/polyfill-mbstring
- License:   MIT (see `vendor/symfony/polyfill-mbstring/LICENSE`)

## Regenerating

To pull updated versions from upstream:

```bash
cd /tmp/refresh-aigrader-vendor
rm -rf vendor composer.json composer.lock
composer require smalot/pdfparser:^2.12
# Then replace the contents of this thirdparty/vendor/ with the new tree.
```

Always include the LICENSE files. Always run the plugin's PHPUnit suite
after a refresh — see `tests/pdf_extractor_test.php` for the
regression cases.
