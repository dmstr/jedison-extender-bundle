<!-- file generated with AI assistance: Claude Code - 2026-08-27 -->

# CLAUDE.md — dmstr/jedison-extender-bundle

Guidance for Claude Code (and humans) working in this repository. Start with the [README](README.md) — it holds the contract, the configuration and the editor catalogue. What follows is only what the README does not say.

## What this is

A Symfony bundle that **serves Jedison editor code over HTTP** (`/ui-extensions`), so every front end talking to an API renders that API's form schemas with the same widgets. It ships PHP (a controller and a locator) and, more importantly, the JavaScript under `jx/` — that directory is the product, the PHP around it is delivery.

## What belongs here and what does not

`jx/core` holds editors any API can use. `jx/_shared` holds what editors are built from — it is served but carries no `manifest.json`, so it is not an extension and never appears in the catalogue.

**An editor shaped by one domain does not belong in this repository.** It belongs to the application that owns the schemas, as its own extension under a configured `paths` entry; it shows up in the same catalogue and a front end cannot tell the two apart. This is not tidiness: everything served from here runs in front ends this bundle knows nothing about, so a widget that only makes sense for one of them is dead weight in all the others — and an invitation to encode that domain into `_shared` next.

`jx/_shared` is therefore **public API**. An application's editor imports it over HTTP as `'../_shared/collection-source.js'`, which the browser resolves against the module URL — the server only ever sees `/ui-extensions/_shared/…`, so nothing about the traversal defence is loosened, and no `..` reaches the file system. Two things follow: renaming anything in `_shared` breaks consumers that never named this bundle in their code, and the import works **only over HTTP** — on disk there is no `_shared` next to a consumer's extension, so a bundler would fail on it.

The routes sit **outside** `/api` and are public on purpose: a dynamic `import()` cannot send an `Authorization` header, so behind a JWT firewall the modules would be unreachable for every consumer. Anything tenant- or user-specific therefore must not go into them.

## English only — and why the wording lives with the host

Code, comments, docs and commit messages are English, like every `dmstr/*` repo. For the served modules that rule is enforceable and enforced: **no module under `jx/` carries a word of any language but English.** Every string an editor renders comes from `labels.js` and can be replaced by the host through `register(Jedison, host, { labels })`.

This is not style. A hardcoded German string works perfectly in the application it was written in and stays invisible until a *second* front end renders the same form — and by then getting it out costs a bundle release. That is exactly what happened before the extraction: every editor carried its wording inline, half of it in `ae`/`ue` transliteration.

A consuming application can pin the rule cheaply by asserting that the served modules contain no non-ASCII **letters** — typography (`—`, `…`, `‹`, `›`, `▾`, `×`) is fine, umlauts and accents are not — plus a short list of the ASCII transliterations. One does, with both directions verified by mutation; the same check runs here as a guard test (see Tests).

## Libraries

The list of switchable libraries lives in `Library::ALL` and nowhere else; configuration validation, the locator and the tests all read it. Vendored third-party code is written **only** by `bin/fetch-libraries` (recipes in `bin/libraries/`, version pinned with its npm integrity value) and never edited by hand. Third-party code always sits in a `vendor/` subdirectory of its extension, which is what keeps it out of the guard tests. A library's `index.js` never alters a global the host provided.

Locales (`core.locales`) are validated against the files of `jx/<lib>/vendor/l10n/` at container build. A file that does not register under its own name is rejected by name, so a new flatpickr version needs that list re-checked (see README, "Notes per library"). Only the catalogue entry carries the locales, as one query parameter; delivery ignores the query, so ETags do not change. Do not add a second parameter without a reason: the module parses exactly this one.

## Versioning

The version comes from the **git tag**, never from `composer.json` — there is no `version` field and none may be added. Tags are plain SemVer **without a `v` prefix** (`0.1.0`, not `v0.1.0`).

`manifest.json` carries a *separate* number: the version of the JavaScript extension, which consumers compare against their Jedison range before executing it. It is not the package version and the two are not kept in sync.

## Tests

```bash
composer install
vendor/bin/phpunit
```

There is no PHP on some development hosts; run the suite in a `php:8.5-cli` container with the repository mounted at `/app` instead. The suite needs no database and no browser.

Layout under `tests/`: `Support/TestKernel.php` is the smallest application that can host the bundle (FrameworkBundle plus this bundle) and is configurable per test — enabled libraries, extra `paths`, and a fixture standing in for the bundle's own `jx/`; `Support/KernelTestCase.php` builds browsers against it. `Fixtures/` holds the stand-in extension directories. `Bundle/`, `Controller/` and `Service/` mirror what they test.

Guard tests, which discover what they check instead of listing it so a new module is covered the day it is added: the shipped modules are English only (no non-ASCII letters, no German transliterations) and name no consumer. They glob `jx/*/*.js` and `jx/_shared/*.js` — the modules this repository owns — and leave `vendor/` out by construction, because third-party code is not ours to police.

`Bundle/LibraryExtensionTest.php` runs over the REAL `jx/` directory for every entry of `Library::ALL`: listing and manifest, every relative URL in an `index.js` resolves through the file route with the right content type, every servable file in `vendor/` is served, a disabled library is invisible, and the directories match the list. Use fixtures only for what the real directory cannot show.

Locale tests: `Bundle/ConfigurationTest.php` (every rejection and normalisation rule), `Controller/LocaleCatalogueTest.php` (catalogue, delivery, ETag, and a counter-check that a changed configuration does not reuse a stale kernel cache) and `Bundle/FlatpickrLocaleModuleTest.php` (source patterns of the module, since there is no browser). `TestKernel` takes the locales as 4th argument and `KernelTestCase::kernel(..., array $locales = [])` / `realClient(array $editors = [], array $locales = [])` pass them on. The locales must be part of the kernel's fingerprint (its cache directory): otherwise two tests with different locales share one compiled container and the second silently runs against the first one's configuration.

**Every new behaviour ships with a test**, and a fix with a test that fails without it.

## Naming a consumer

Don't. Not in code, not in comments, not here. The bundle is used by applications it must not know about, and a comment mentioning one is the first step of the coupling this repository exists to prevent — the next reader takes the example for the contract. Where a concrete case genuinely explains a behaviour, describe the behaviour instead: "a server should treat a present-but-empty filter as an empty set" carries the point without an owner.
