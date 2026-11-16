<!-- file generated with AI assistance: Claude Code - 2026-08-26 -->

# dmstr/jedison-extender-bundle

Serves [Jedison](https://github.com/germanbisurgi/jedison) form editors **from the API**, so every front end talking to it renders the same widgets from one source.

## Why an API ships editor code

The form schemas live in the backend, and the widgets able to render them belong with them. Without this, a shared editor ends up copied into every front end — and the copies drift. That is not hypothetical: the IRI-reference editor existed twice across two applications, and the copies had already diverged on whether `x-value-property` is honoured.

A second reason follows from the first: a generic admin UI serves several backends. Bundling every project's widgets into it would mean one build per project. Loading them from whichever API is active means one build for all of them.

## Endpoints

| Route | Purpose |
|---|---|
| `GET /ui-extensions` | catalogue: name, version, supported Jedison range, module URL |
| `GET /ui-extensions/{name}/{file}` | a file of an extension, also from subdirectories (`vendor/...`), with `ETag` |

Both are **public and outside any `/api` firewall — by necessity**. A front end loads a module with a dynamic `import()`, and that cannot carry an `Authorization` header; behind a JWT firewall the module would be unreachable and the route pointless.

The file route serves an allow-list of types only: `.js` as `text/javascript`, `.css` as `text/css` and `.png` as `image/png`. Everything else is a 404, and `manifest.json` is never served. The files of a library that is not enabled (see below) are a 404 as well.

This is safe because a module holds no secret: it is UI code every client executes anyway. **What must never live in one is anything tenant- or user-dependent** — that stays behind the authenticated endpoints the editors then call *with* a token supplied by the host.

## The contract

```js
const catalogue = await (await fetch(`${apiUrl}/ui-extensions`)).json()

for (const entry of catalogue.extensions) {
  // Check compatibility BEFORE executing third-party code.
  if (!satisfies(myJedisonVersion, entry.jedison)) continue

  const register = (await import(entry.module)).default
  const { editors, constraints } = register(Jedison, { fetchCollection }, { labels })

  jedisonOptions.customEditors.push(...editors)
  Object.assign(jedisonOptions.constraints, constraints)
}
```

- **`Jedison` must be the host's instance.** A module importing the library itself would extend a *second* copy and get a different `Editor` base class than the one the host's `ui-resolver` checks against — none of its editors would ever resolve.
- **`host.fetchCollection(url)`** is the host's *authenticated* HTTP client. Editors read protected collections, which answer 401 without a token, and only the host has one. Omitting it falls back to a plain `fetch()` — enough for public collections and nothing else.
- **`host.resolveLink(iri, schema)`** (optional) returns the address at which the host shows the record an IRI refers to, or `null`. The reference editor links to it next to its input, in edit and read-only mode alike. The bundle knows no front end routes; without the function no link is shown.
- **`constraints` is a map `{keyword: fn}`, not an array.** Jedison's own default spells it `[]`, but its validator spreads the value into an object, so an array registers nothing at all, silently ([jedison#69](https://github.com/germanbisurgi/jedison/issues/69), point 3).
- **Order matters in `customEditors`** — the array is scanned in order, first match wins. Put narrower editors before broader ones.

## Wording (`options.labels`)

The editors render **English** defaults and take every string they display from the host. Pass the ones you want to change; the rest stay English.

```js
register(Jedison, { fetchCollection }, {
  labels: {
    placeholder: '- select -',
    waitingFor: (fields) => `Select ${fields} first — nothing is loaded.`,
    nextPage: 'Next ›',
  },
})
```

The catalogue is [`jx/_shared/labels.js`](jx/_shared/labels.js); entries are strings or functions of the values that vary — never templates the host has to reassemble, because word order differs between languages.

**Nothing served from `/ui-extensions` carries a word of any language but English.** That is not style: a hardcoded string works perfectly in the application it was written in and only surfaces as wrong when a second front end renders the same form — at which point it takes a bundle release to get out again. A consuming application can pin the rule cheaply by asserting that the served modules contain no non-ASCII **letters** — typography is fine, umlauts and accents are not — plus the ASCII transliterations a language falls back to when someone avoids them.

## Configuration

The bundle always contributes its own extensions. An application adds its own directories:

```yaml
# config/packages/dmstr_jedison_extender.yaml
dmstr_jedison_extender:
    paths:
        - '%kernel.project_dir%/jx'
```

Each subdirectory with a `manifest.json` is one extension. The manifest is the **single source** for name, version and supported Jedison range — a module must not export its own, or the two would drift. On a name clash the earlier path wins, and the bundle's own directory comes first.

Register the routes:

```yaml
# config/routes.yaml
dmstr_jedison_extender:
    resource: '@JedisonExtenderBundle/config/routes.yaml'
```

**CORS is required, not optional:** an ES module loaded from another origin fails without `Access-Control-Allow-Origin`. The front ends usually run on a different origin than the API, so the application has to allow theirs.

## Library extensions: Jedison's plugin editors

Jedison ships editor classes for several third-party libraries (date picker, input masks, rich text, code editor, ...) but does not ship the libraries; an editor only resolves when the library exists as a browser global. This bundle ships the libraries, each as an extension of its own under `jx/<name>/`. **The editor classes come from Jedison, the bundle only provides the library** — so a schema with the right `x-format` renders with the matching editor as soon as the extension is loaded.

Activate the ones you need in the bundle configuration:

```yaml
# config/packages/dmstr_jedison_extender.yaml
dmstr_jedison_extender:
    core:
        editors: [flatpickr, imask, quill]
```

The default is **none**: a library that is not enabled is not listed in the catalogue and its files answer 404, so a host never downloads code nobody asked for. Unknown names are rejected when the configuration is loaded, and so is `awesomplete`, which is part of `core` and always on ("Awesomplete is part of core and always enabled"). The valid names are the entries of `Library::ALL` in the table below.

Libraries that ship translations can have locales loaded for them with `core.locales`, a map from library to a list of locales (default: empty):

```yaml
# config/packages/dmstr_jedison_extender.yaml
dmstr_jedison_extender:
    core:
        editors: [flatpickr]
        locales:
            flatpickr: [de]
```

The configuration is checked when the container is built, and the following is rejected with an `InvalidConfigurationException`: a library name that does not exist (shown as written); a library that ships no locales (today only flatpickr does); locales for a library that is not enabled in `core.editors`; a locale name that does not match `^[a-z][a-z0-9_-]*$`; a locale without a file `vendor/l10n/<locale>.js`; and the names `index` (the collection file) and `default` (English, registers nothing). A locale appears once however often it is listed, the order is kept, and an empty list is the same as no configuration. The catalogue entry of the library then carries the locales in its `module` URL (`/ui-extensions/flatpickr/index.js?locales=de,fr`); without configuration the catalogue is identical to 0.3.0.

| `x-format` | Schema type | Extension | Library (global) | Version | License |
|---|---|---|---|---|---|
| `flatpickr` | string | `flatpickr` | flatpickr (`window.flatpickr`) | 4.6.13 | MIT |
| `imask` | string, number | `imask` | IMask (`window.IMask`) | 6.6.3 | MIT |
| `awesomplete` | string | `core` (always on) | Awesomplete (`window.Awesomplete`) | 1.1.7 | MIT |
| `quill` | string | `quill` | Quill (`window.Quill`) | 2.0.3 | BSD-3-Clause |
| `jodit` | string | `jodit` | Jodit (`window.Jodit`) | 4.2.50 | MIT |
| `ace` | string | `ace` | Ace (`window.ace`) | 1.32.9 | BSD-3-Clause |
| `raty` | number | `raty` | Raty (global class binding `Raty`) | 4.3.0 | MIT |
| `choices` | array | `choices` | Choices.js (`window.Choices`) | 11.2.4 | MIT |
| `tom-select` | array | `tom-select` | Tom Select (`window.TomSelect`) | 2.7.0 | Apache-2.0 |

The `x-format` values were checked against Jedison 1.31.1. Options go in the matching `x-<format>` keyword (`x-flatpickr`, `x-quill`, ...), see the Jedison documentation of the editor. The two array editors (`choices`, `tom-select`) additionally require `uniqueItems: true` and an `items` type of string, number or integer.

Every extension declares what it ships in its manifest (`libraries`: name, version, license), and the shipped license files sit next to the code in `vendor/`.

### A library the host already has

An existing global is **reused, never loaded twice and never reconfigured**: a host that brings its own flatpickr keeps its instance, and the extension then only makes sure nothing is added. Where the bundle would normally tune a library after loading it (Ace's base path and workers, Jodit's source view), it does so only when it loaded the library itself.

Two things to know:

- **Raty** declares `class Raty` at the top level of a classic script, which creates a global binding but no property of `globalThis`. Its module therefore checks `typeof Raty` instead of `globalThis.Raty`, like Jedison does.
- **AMD loaders.** On a page with an AMD loader (`define.amd` is set, e.g. RequireJS), UMD files register with the loader and do not set the global. The extension then throws an error that names the script and the loader instead of leaving an editor that silently never resolves.

### Notes per library

- **flatpickr** is shipped with its locale files under `vendor/l10n/`; none is loaded by default. The standard way to get one is `core.locales` (see Library extensions above): the extension loads the configured files from `./vendor/l10n/` after the library is in place, skips a locale that is already registered in `flatpickr.l10ns` (so a host's own flatpickr and its locales are left alone) and throws if a file did not register. Set `locale` in `x-flatpickr` as before. Loading `/ui-extensions/flatpickr/vendor/l10n/<locale>.js` yourself, after the extension, stays possible. Not accepted are `ar-dz` (registers `ar`), `ga` (registers `hr` and would overwrite Croatian), `ms` (registers nothing), `sr-cyr` (registers `sr`) and `zh-tw` (registers `zh_tw`), because in 4.6.13 these files do not register under their own name. Locale files are UMD: they read `window.flatpickr` and set a global named after the locale; with an AMD loader on the page (`define.amd`) they register with the loader instead, the same caveat as above. The module reads the locales from its own URL, so a host must import `entry.module` unchanged, with its query; `index.js` and `index.js?locales=de` are two module instances in the browser, which is harmless because loading is idempotent. A host that caches the catalogue sees a changed configuration only after it reloads it.
- **ace** ships the modes text, json, javascript, html, css, markdown, xml, yaml and sql, and the themes chrome (the default), github, github_dark, monokai and tomorrow. Any other mode or theme answers 404 and Ace falls back to plain text. Workers (syntax checks) are off, because none are shipped.
- **jodit** is the fat build: languages, icons and plugins are inside two files. The "source" button would fetch Ace and a beautifier from a public CDN by default; when the bundle loads Jodit it switches the source view to a plain textarea (`sourceEditor: 'area'`, `beautifyHTML: false`).
- **quill** ships the snow theme; the schema has to ask for it with `"x-quill": {"theme": "snow"}`.
- **raty** supports the image mode only (`starType: 'img'`, the default); the star images are served from `vendor/images/` and a schema's `x-raty.path` still wins. The font mode (`starType: 'i'`) is not supported.

### Content Security Policy and CORS

A host with a Content Security Policy has to allow the API origin in `script-src` and `style-src`; add it to `font-src` when you enable something that loads fonts, and `worker-src blob:` when you enable Ace workers.

CORS applies to all of it, not only to the modules: module scripts and stylesheets loaded from another origin need `Access-Control-Allow-Origin`, and so do fonts that a cross-origin stylesheet refers to.

### Maintenance of the libraries

Pinned versions are not a statement about health. As of this release: **flatpickr** has not had a release since 2022-04 and is unmaintained, **awesomplete** had its last release in 2024-07 and **raty-js** in 2024-04. They are shipped because Jedison's editors target them; check the upstream issue trackers before relying on them for new work.

To update a library, change the pinned `VERSION` and `INTEGRITY` (the npm `dist.integrity` value) in its recipe `bin/libraries/<lib>.sh`, run `bin/fetch-libraries <lib>` (no argument refreshes all) and commit the result together with the new version in the extension's `manifest.json`. The script verifies the tarball against the integrity value before it touches anything; the files in `vendor/` are only ever written by it and never edited by hand. A new library needs its entry in `Library::ALL`, a recipe, and the extension directory with `manifest.json` and `index.js`.

## Layout: `jx/core`, `jx/_shared` — and your application's own

```
jx/
  core/       one extension: editors any API can use (always on)
  <library>/  one extension per shipped library, switched on in the configuration
  _shared/    what editors are built from; served, but not an extension
```

| Schema | Editor (`core`) |
|---|---|
| `format: iri-reference` + `x-collection` | type-ahead single reference (`core` loads Awesomplete itself and the module only resolves once it is there, like Jedison's own plugin editors). A stored value is shown by its label: an IRI is fetched as an item, a bare id is tried as `<collection path>/<id>` and else searched through `x-search-property`; until then, and when nothing is found, the raw value is shown. Labels are cached per `x-collection` and value; the stored value is the tooltip of the shown label. Focusing or clicking a field that has a value lists all options, not only the one shown, and the text is selected so typing replaces it. A button next to the input clears the value (it is hidden while the form is disabled). Text that is typed but not picked is dropped on blur and the stored value is shown again. When the host passes `resolveLink(iri, schema)`, a link to the referenced record sits next to the input, also in a disabled form. |

**Editors shaped by one domain do not belong here.** They belong in the application that owns the schemas, as its own extension — a directory with a `manifest.json` under a configured `paths` entry. It appears in the same catalogue, and a front end loading both cannot tell which repository either came from. What the bundle contributes to those editors is `jx/_shared`.

`jx/_shared` is **public API**, not internals:

| Module | What it gives you |
|---|---|
| `collection-source.js` | `CollectionSource` — loads options from `x-collection`, follows `hydra:view`, resolves `{{ alias.value }}` placeholders against sibling fields and re-loads on `x-watch` changes. Plus `compileCollectionUrl` / `resolveInstancePath` for editors that need only the URL. |
| `labels.js` | `defaultLabels`, `resolveLabels(overrides)` and `collectionHint(labels, meta, shown)` — the wording catalogue and the hint line every collection-backed editor shows. |

An application's editor imports them **over HTTP**, relative to its own module URL:

```js
import { CollectionSource } from '../_shared/collection-source.js'
```

The browser normalises that against `/ui-extensions/<your-extension>/`, so the request that reaches the server is `/ui-extensions/_shared/collection-source.js` — no `../` ever travels over the wire, and the traversal defence stays as strict as it is. Two consequences worth knowing: on the file system there is no `_shared` next to your extension (a bundler or any disk-resolving tool would fail on that import), and renaming anything in `_shared` breaks consumers that never named this bundle in their own code.

Editors read a common set of keywords: `x-collection` (may carry `{{ alias.value }}` placeholders), `x-watch` (`{alias: target}`), `x-label-property`, `x-value-property`, and optionally `x-collection-page-size` / `x-collection-max-pages`.

**Order matters across extensions.** The IRI editor is broad — it claims any string with `format: iri-reference` and an `x-collection`. A host that also loads narrower editors registers those FIRST; the catalogue is sorted by name and does not express this, so the host has to.

### Writing your own editor: three things Jedison decides for you

Each of these produced a working-looking widget with a wrong behaviour, and none of them raised an error anywhere.

**You do not own `disabled` on your own controls.** `refreshDisabledState()` walks every `button, input, select, textarea` in the editor's container and *removes* `disabled` unless the field itself is disabled or readOnly. A plain `button.disabled = true` therefore survives only until the next refresh — and refreshes happen on every value change. The documented opt-out is the `always-disabled` attribute; set and remove *that*, and assign the property alongside only so the change takes effect before the next refresh. Measured on the pager: both buttons sat enabled on a single-page result and did nothing when clicked, which reads as a broken list rather than the end of one.

**After `setValue()` the focus is on `BODY`.** Not on the control the user just operated — measured, and it holds whether or not the element survives the re-render. Two consequences: a keyboard handler bound to your own subtree (an Escape that closes a panel, say) never sees the key, so it belongs on `document`; and unless you restore the focus yourself, every interaction sends Tab back to the start of the document, which makes a multi-select unusable by keyboard.

**Render from the VALUE, never from the option cache.** In a cascade the options change underneath an existing selection. Chips, ticks and counters read from the instance value, so a chosen entry stays visible even while it is absent from the freshly loaded page.

### Two rules that fail silently when broken

**`x-watch` targets must be RELATIVE** (`otherField`, `../otherField`). The same schema gets rendered in two shapes: standalone its fields sit at the document root, but a host that wraps form fields in an envelope — as the Flowable bundle does with `variables` — moves them to `#/variables/otherField`. An absolute path is wrong in one of the two, and wrong *silently*: watching a non-existent path never fires, so the dependent list simply never reloads.

**Every alias referenced in a template is implicitly required.** If one is blank, no request is made at all. Without that guard an empty selection compiles to `?filter=` — which, under the usual "empty means unfiltered" semantics, returns the entire catalogue as a plausible-looking list. Servers should close the same hole from their end by treating a present-but-empty multi-value filter as "nothing selected".

## Development

```bash
composer install
vendor/bin/phpunit
```

The suite needs PHP 8.4 or newer and no database or browser. It boots a minimal Symfony kernel and checks the catalogue, the file route, the configuration, the shipped modules and every library extension against the real `jx/` directory.

## Upgrading to 0.3.0

The IRI editor changes of [PR #3](https://github.com/dmstr/jedison-extender-bundle/pull/3) ship with 0.3.0:

- A **clear button** next to the input empties the value. Its `title` and `aria-label` come from the new label key `clear` (default `Clear selection`); a host that translates the labels has to add that entry.
- The editor shows the **label of a stored reference** instead of the raw value. Focusing or clicking a field with a value lists all options, and text that is typed but not picked is dropped on blur.
- When the host passes `resolveLink(iri, schema)`, a **link to the referenced record** sits next to the input. Its `title` and `aria-label` use the label key `openReference`; without the function no link is shown.

`core` also loads Awesomplete itself now, so a host no longer has to provide the `Awesomplete` global for the reference editor. A host that does keep providing it is reused as before.

## Upgrading to 0.3.1

0.3.1 is additive: nothing has to be done unless you want flatpickr locales. Configure them with `core.locales` (see Configuration) and make sure the host imports `entry.module` from the catalogue unchanged, including its query string. Without `core.locales` the catalogue is identical to 0.3.0.
