// file generated with AI assistance: Claude Code - 2026-11-16 15:55:00 UTC

/**
 * Loads a third-party browser library that ships with an extension.
 *
 * Public API, like the rest of `_shared`: library extensions import it as
 * `'../_shared/load-library.js'`, resolved by the browser against the module
 * URL. Do not rename or move anything in here.
 *
 * WHY A HELPER: the Jedison plugin editors read the library from a browser
 * global (`globalThis.flatpickr`, ...). An extension therefore has to put the
 * library there BEFORE its module finishes evaluating, which is what a
 * top-level `await ensureGlobal(...)` does — the host's `import()` only
 * resolves once the global exists.
 *
 *   import { ensureGlobal } from '../_shared/load-library.js'
 *   await ensureGlobal(
 *     'flatpickr',
 *     new URL('./vendor/flatpickr.min.js', import.meta.url),
 *     [new URL('./vendor/flatpickr.min.css', import.meta.url)]
 *   )
 *
 * URLs are absolute and built by the caller from `import.meta.url`: a relative
 * URL would be resolved against the page, not against the extension.
 *
 * AMD CAVEAT: most libraries are UMD files. On a page that has an AMD loader
 * (`define.amd` is set, e.g. RequireJS) such a file registers itself with the
 * loader and does NOT set the global. `ensureGlobal` then fails with a clear
 * error instead of leaving an editor that silently never resolves.
 */

/**
 * Appends a classic <script> tag.
 *
 * @param {string|URL} url
 * @returns {Promise<void>} resolves on load, rejects on error
 */
export function loadScript (url) {
  return new Promise((resolve, reject) => {
    const script = document.createElement('script')
    script.src = String(url)
    script.onload = () => resolve()
    script.onerror = () => reject(new Error('Could not load script ' + script.src))
    document.head.appendChild(script)
  })
}

/**
 * Appends a <link rel="stylesheet"> once per URL; a second call with the same
 * URL resolves without adding another tag.
 *
 * @param {string|URL} url
 * @returns {Promise<void>} resolves on load, rejects on error
 */
export function loadStylesheet (url) {
  const href = String(url)

  return new Promise((resolve, reject) => {
    const existing = Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
      .find((link) => link.href === href)

    if (existing) {
      resolve()
      return
    }

    const link = document.createElement('link')
    link.rel = 'stylesheet'
    link.href = href
    link.onload = () => resolve()
    link.onerror = () => reject(new Error('Could not load stylesheet ' + href))
    document.head.appendChild(link)
  })
}

/**
 * Makes sure `globalThis[name]` exists. Does nothing when it already does:
 * the host may have loaded the library itself, and a second copy would
 * overwrite it with a different instance.
 *
 * @param {string} name global the library defines, e.g. 'flatpickr'
 * @param {string|URL} scriptUrl
 * @param {Array<string|URL>} [stylesheetUrls]
 * @returns {Promise<void>}
 */
export async function ensureGlobal (name, scriptUrl, stylesheetUrls = []) {
  if (globalThis[name]) {
    return
  }

  await Promise.all(stylesheetUrls.map(loadStylesheet))
  await loadScript(scriptUrl)

  if (!globalThis[name]) {
    throw new Error(
      'jedison-extender: ' + String(scriptUrl) + ' loaded but did not define ' +
      'the global "' + name + '". If the page has an AMD loader (define.amd), ' +
      'a UMD library registers with it instead of setting the global.'
    )
  }
}
