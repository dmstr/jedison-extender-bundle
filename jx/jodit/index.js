// file generated with AI assistance: Claude Code - 2026-11-16 16:10:00 UTC

/**
 * Enables Jedison's Jodit rich-text editor (`x-format: jodit` on a string
 * schema).
 *
 * The editor class itself comes from Jedison (`EditorStringJodit`); this module
 * only puts the library where that editor looks for it, the global `Jodit`. An
 * existing global is reused and never loaded twice. Schema options go in
 * `x-jodit` and are merged over Jedison's defaults (a short toolbar).
 *
 * The fat build ships with this bundle: all languages, icons and plugins are
 * inside the two files, so nothing else is requested at runtime.
 *
 * SOURCE VIEW: Jodit's "source" button would load Ace and a HTML beautifier
 * from a public CDN by default. When this module loads Jodit it therefore
 * switches the source view to a plain textarea and disables the beautifier.
 * A host that brings its own Jodit keeps its own settings.
 */

import { ensureGlobal } from '../_shared/load-library.js'

const providedByHost = Boolean(globalThis.Jodit)

await ensureGlobal(
  'Jodit',
  new URL('./vendor/jodit.fat.min.js', import.meta.url),
  [new URL('./vendor/jodit.fat.min.css', import.meta.url)]
)

if (!providedByHost) {
  globalThis.Jodit.defaultOptions.sourceEditor = 'area'
  globalThis.Jodit.defaultOptions.beautifyHTML = false
}

export default function register () {
  return { editors: [], constraints: {} }
}
