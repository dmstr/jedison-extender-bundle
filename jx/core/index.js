// file generated with AI assistance: Claude Code - 2026-08-27

/**
 * The editors every API can use, regardless of its domain.
 *
 * THE CONTRACT — a default factory, not an import:
 *
 *   const register = (await import('<api>/ui-extensions/core/index.js')).default
 *   const { editors, constraints } = register(Jedison, { fetchCollection }, { labels })
 *
 * `Jedison` must be the HOST's instance. This module deliberately does not
 * import the library: a second copy would give it a different `Editor` base
 * class than the one the host's `ui-resolver` checks against, and none of these
 * editors would ever resolve.
 *
 * `host.fetchCollection(url) -> Promise<hydra document>` is the host's
 * AUTHENTICATED client. The editors read protected collections, which answer
 * 401 without a bearer token — each front end hangs that token into its own
 * interceptor, so only the host can supply it. Omitting it falls back to a
 * plain `fetch()`, enough for public collections and nothing else.
 *
 * `options.labels` carries the wording; the defaults are English. See
 * `../_shared/labels.js`.
 *
 * WHERE THE RESULT GOES: `editors` into `customEditors`, `constraints` into
 * `constraints` of `new Jedison.Create({...})`. Two notes that cost debugging
 * time otherwise:
 *
 *   - `constraints` is a MAP `{keyword: fn}`, not an array. Jedison's own
 *     default spells it `[]`, but the validator spreads it into an object —
 *     an array registers nothing at all, silently
 *     (https://github.com/germanbisurgi/jedison/issues/69, point 3).
 *   - Order matters in `customEditors`. The array is scanned in order and the
 *     first match wins, so put narrower editors before broader ones. The IRI
 *     editor is broad: it claims any string with `format: iri-reference` and an
 *     `x-collection`. An application registering narrower editors of its own
 *     puts them BEFORE this extension's.
 *
 * Every editor is also importable on its own (`./iri.js` exports its factory
 * under a name) — this file is the convenience, not the only way in.
 *
 * Metadata (name, version, supported Jedison range) lives in `manifest.json`
 * next to this file and is served by the catalogue route — deliberately NOT
 * exported here, so there is exactly one source for it.
 */

import { makeEditorIriReference } from './iri.js'

/**
 * @param {object} Jedison the host's Jedison namespace (its default export)
 * @param {{fetchCollection?: (url: string) => Promise<object>}} [host]
 * @param {{labels?: Record<string, unknown>}} [options]
 * @returns {{editors: Function[], constraints: Record<string, Function>}}
 */
export default function register (Jedison, host = {}, options = {}) {
  if (!Jedison || !Jedison.Editor) {
    throw new Error(
      'jedison-extender/core: register() needs the host Jedison namespace as ' +
      'its first argument (the default export of the jedison package).'
    )
  }

  return {
    editors: [
      // No labels: this editor renders no text of its own — the type-ahead
      // comes from Awesomplete and its entries are collection members.
      makeEditorIriReference(Jedison, host),
    ],
    // No custom constraints yet. `x-validator-*` keywords belong here, as a map
    // keyed by the keyword — see the note above.
    constraints: {},
  }
}
