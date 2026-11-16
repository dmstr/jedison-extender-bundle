// file generated with AI assistance: Claude Code - 2026-11-16 16:03:25 UTC

/**
 * Enables Jedison's flatpickr editor (string, `x-format: flatpickr`). Locale
 * files under vendor/l10n/ are loaded when the module URL carries
 * `?locales=de,fr` (set by the bundle configuration `core.locales`). A host
 * may still load a locale file itself; registered locales are skipped.
 *
 * The editor classes come from Jedison itself; this extension only puts the
 * library where Jedison looks for it (`globalThis.flatpickr`). An existing
 * global is reused and the library is never loaded twice.
 */

import { ensureGlobal, loadScript } from '../_shared/load-library.js'

await ensureGlobal(
  'flatpickr',
  new URL('./vendor/flatpickr.min.js', import.meta.url),
  [new URL('./vendor/flatpickr.min.css', import.meta.url)]
)

const requested = new URL(import.meta.url).searchParams.get('locales')

if (requested) {
  const base = new URL('./vendor/l10n/', import.meta.url)

  for (const locale of requested.split(',')) {
    if (!/^[a-z][a-z0-9_-]*$/.test(locale)) {
      throw new Error('jedison-extender: invalid locale name in the "locales" parameter')
    }

    if (globalThis.flatpickr.l10ns?.[locale]) {
      continue
    }

    await loadScript(new URL(`${locale}.js`, base))

    if (!globalThis.flatpickr.l10ns?.[locale]) {
      throw new Error(
        'jedison-extender: locale "' + locale + '" loaded but did not register ' +
        'with flatpickr. If the page has an AMD loader (define.amd), a UMD ' +
        'file registers with it instead.'
      )
    }
  }
}

export default function register () {
  return { editors: [], constraints: {} }
}
