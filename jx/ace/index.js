// file generated with AI assistance: Claude Code - 2026-11-16 16:10:00 UTC

/**
 * Enables Jedison's Ace code editor (`x-format: ace` on a string schema).
 *
 * The editor class itself comes from Jedison (`EditorStringAce`); this module
 * only puts the library where that editor looks for it, the global `ace`. An
 * existing global is reused and never loaded twice. Schema options go in
 * `x-ace` (`mode`, `theme`, `height`, `options`, ...), e.g.
 * `"x-ace": {"mode": "json", "theme": "ace/theme/monokai"}`.
 *
 * SHIPPED MODES (under `vendor/`, fixed selection): text, json, javascript,
 * html, css, markdown, xml, yaml, sql. SHIPPED THEMES: chrome (Jedison's
 * default), github, github_dark, monokai, tomorrow. Any other mode or theme is
 * NOT available: Ace requests the file from the bundle, gets a 404 and falls
 * back to plain text / the default look.
 *
 * WORKERS: syntax-check workers are off by default, because none are shipped.
 * A host that ships its own workers and enables them needs a CSP that allows
 * `worker-src blob:`.
 *
 * Ace injects its CSS from JavaScript, so no stylesheet is loaded here.
 */

import { ensureGlobal } from '../_shared/load-library.js'

const vendorUrl = new URL('./vendor/', import.meta.url)

await ensureGlobal('ace', new URL('ace.js', vendorUrl))

// Modes and themes are fetched lazily from the bundle by their module id.
globalThis.ace.config.set('basePath', vendorUrl.href)
globalThis.ace.config.setDefaultValue('session', 'useWorker', false)

export default function register () {
  return { editors: [], constraints: {} }
}
