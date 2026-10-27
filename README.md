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
| `GET /ui-extensions/{name}/{file}.js` | a module file, `text/javascript`, with `ETag` |

Both are **public and outside any `/api` firewall — by necessity**. A front end loads a module with a dynamic `import()`, and that cannot carry an `Authorization` header; behind a JWT firewall the module would be unreachable and the route pointless.

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

## Layout: `jx/core`, `jx/_shared` — and your application's own

```
jx/
  core/       one extension: editors any API can use
  _shared/    what editors are built from; served, but not an extension
```

| Schema | Editor (`core`) |
|---|---|
| `format: iri-reference` + `x-collection` | type-ahead single reference (**requires Awesomplete** as a global; declines to resolve without it, exactly like Jedison's own plugin editors). A stored value is shown by its label: an IRI is fetched as an item, a bare id is tried as `<collection path>/<id>` and else searched through `x-search-property`; until then, and when nothing is found, the raw value is shown. Labels are cached per `x-collection` and value; the stored value is the tooltip of the shown label. Focusing or clicking a field that has a value lists all options, not only the one shown, and the text is selected so typing replaces it. A button next to the input clears the value (it is hidden while the form is disabled). Text that is typed but not picked is dropped on blur and the stored value is shown again. When the host passes `resolveLink(iri, schema)`, a link to the referenced record sits next to the input, also in a disabled form. |

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
