// file generated with AI assistance: Claude Code - 2026-11-16 16:03:25 UTC

/**
 * Enables Jedison's flatpickr editor (string, `x-format: flatpickr`). Locale
 * files under vendor/l10n/ are not loaded; a host loads one itself.
 *
 * The editor classes come from Jedison itself; this extension only puts the
 * library where Jedison looks for it (`globalThis.flatpickr`). An existing
 * global is reused and the library is never loaded twice.
 */

import { ensureGlobal } from '../_shared/load-library.js'

await ensureGlobal(
  'flatpickr',
  new URL('./vendor/flatpickr.min.js', import.meta.url),
  [new URL('./vendor/flatpickr.min.css', import.meta.url)]
)

export default function register () {
  return { editors: [], constraints: {} }
}
