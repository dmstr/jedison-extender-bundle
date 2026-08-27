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

A consuming application can pin the rule cheaply by asserting that the served modules contain no non-ASCII **letters** — typography (`—`, `…`, `‹`, `›`, `▾`, `×`) is fine, umlauts and accents are not — plus a short list of the ASCII transliterations. One does, with both directions verified by mutation; the same check belongs here once this repository has a test runner.

## Versioning

The version comes from the **git tag**, never from `composer.json` — there is no `version` field and none may be added. Tags are plain SemVer **without a `v` prefix** (`0.1.0`, not `v0.1.0`).

`manifest.json` carries a *separate* number: the version of the JavaScript extension, which consumers compare against their Jedison range before executing it. It is not the package version and the two are not kept in sync.

## Tests

There is no test infrastructure in this repository yet. Until there is, the behaviour is covered downstream: a consuming application asserts the delivery contract (catalogue shape, content type, the route path outside `/api`, realpath containment, the English-only guard) and drives the editors headless in a real browser against a real API.

That arrangement is on borrowed time — a bundle whose only tests live in one of its consumers is untested from the moment a second one appears. Anything added here ships with a test the day this repository has a runner; see the open issue.

## Naming a consumer

Don't. Not in code, not in comments, not here. The bundle is used by applications it must not know about, and a comment mentioning one is the first step of the coupling this repository exists to prevent — the next reader takes the example for the contract. Where a concrete case genuinely explains a behaviour, describe the behaviour instead: "a server should treat a present-but-empty filter as an empty set" carries the point without an owner.
