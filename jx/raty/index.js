// file generated with AI assistance: Claude Code - 2026-11-16 16:03:41 UTC

/**
 * Enables Jedison's Raty editor (number, `x-format: raty`).
 *
 * The editor class comes from Jedison itself; this extension only provides the
 * library. An existing `Raty` is reused and the library is never loaded twice.
 *
 * QUIRK: raty.min.js declares `class Raty` at the top level of a classic
 * script. That creates a global binding but NOT a property of `globalThis`,
 * so `ensureGlobal('Raty', ...)` would always fail. Jedison itself checks
 * `typeof Raty`, which does see the binding, so this module does the same.
 */

import { loadScript } from '../_shared/load-library.js'

if (typeof Raty === 'undefined') {
  await loadScript(new URL('./vendor/raty.min.js', import.meta.url))
}

if (typeof Raty === 'undefined') {
  throw new Error('jedison-extender: vendor/raty.min.js loaded but did not define Raty.')
}

// Raty resolves star images against `path`, which defaults to the page. Point
// the default at the shipped images; a schema's `x-raty.path` still wins. The
// flag keeps a second evaluation of this module from wrapping twice.
if (!Raty.prototype.defaultOptions.jxPatched) {
  const original = Raty.prototype.defaultOptions
  const imagePath = new URL('./vendor/images/', import.meta.url).href

  Raty.prototype.defaultOptions = function () {
    return { ...original.call(this), path: imagePath }
  }
  Raty.prototype.defaultOptions.jxPatched = true
}

export default function register () {
  return { editors: [], constraints: {} }
}
