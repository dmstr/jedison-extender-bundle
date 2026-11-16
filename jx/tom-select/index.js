// file generated with AI assistance: Claude Code - 2026-11-16 16:03:25 UTC

/**
 * Enables Jedison's Tom Select editor (array, `x-format: tom-select`).
 *
 * The editor classes come from Jedison itself; this extension only puts the
 * library where Jedison looks for it (`globalThis.TomSelect`). An existing
 * global is reused and the library is never loaded twice.
 */

import { ensureGlobal } from '../_shared/load-library.js'

await ensureGlobal(
  'TomSelect',
  new URL('./vendor/tom-select.complete.min.js', import.meta.url),
  [new URL('./vendor/tom-select.min.css', import.meta.url)]
)

export default function register () {
  return { editors: [], constraints: {} }
}
