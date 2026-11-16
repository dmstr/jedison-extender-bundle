// file generated with AI assistance: Claude Code - 2026-11-16 16:03:25 UTC

/**
 * Enables Jedison's IMask editors (string and number, `x-format: imask`).
 *
 * The editor classes come from Jedison itself; this extension only puts the
 * library where Jedison looks for it (`globalThis.IMask`). An existing
 * global is reused and the library is never loaded twice.
 */

import { ensureGlobal } from '../_shared/load-library.js'

await ensureGlobal(
  'IMask',
  new URL('./vendor/imask.min.js', import.meta.url)
)

export default function register () {
  return { editors: [], constraints: {} }
}
