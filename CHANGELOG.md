<!-- file generated with AI assistance: Claude Code - 2026-11-25 22:03:23 UTC -->

# Changelog

All notable changes to this bundle. Versions are git tags (plain SemVer, no `v` prefix); there are no separate GitHub releases.

## 0.3.2 - 2026-11-25

Tests and documentation only; no change to shipped behaviour.

- The entry-module contract (default export of `register()`, no exported `name`, `version` or `jedison`) is checked for every shipped editor instead of `core` only. The editors are discovered by scanning `jx/*/manifest.json`, and a guard test keeps the discovered set equal to `core` plus `Library::ALL`.
- `CHANGELOG.md` added.

## 0.3.1 - 2026-11-16

- New optional configuration `core.locales`: a library's locales are served through the catalogue, first use flatpickr. Validated at container build; only that library's catalogue entry gets a `?locales=` parameter, delivery and ETags are unchanged (PR #8).
- `jx/flatpickr/index.js` loads the locales named in its module URL and fails loudly when a file does not register.

## 0.3.0 - 2026-11-16

- Switchable library extensions: `core.editors` enables any of the eight libraries in `Library::ALL` (ace, choices, flatpickr, imask, jodit, quill, raty, tom-select), each shipped as its own extension with a pinned, vendored build. Disabled libraries are absent from the catalogue and their files are 404 (PR #5).
- `core` ships Awesomplete 1.1.7 and loads it itself (manifest `1.1.0`).
- The file route serves `css` and `png` besides `js` and allows subdirectories.
- PHPUnit test suite with a test kernel, delivery tests over the real `jx/`, guard tests, and GitHub Actions CI.
- IRI reference editor: clear button, all options listed on focus, no reopening of the list after a pick (rest of PR #3).

## 0.2.0 - 2026-10-14

- IRI reference editor shows the label of a stored reference instead of the raw value.
- IRI reference editor links to the referenced record through the new optional host service `host.resolveLink(iri, schema)`.

## 0.1.0 - 2026-08-27

- Initial release: serves Jedison editor modules and a catalogue over `/ui-extensions`. `jx/core` ships the IRI reference editor, `jx/_shared` the collection source and the label catalogue.
