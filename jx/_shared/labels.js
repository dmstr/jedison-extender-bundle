// file generated with AI assistance: Claude Code - 2026-08-27

/**
 * Every piece of text these editors put on screen, in one place.
 *
 * The defaults are English because the bundle is generic; the applications that
 * consume it are not. A host passes its own wording as the third argument of
 * `register()` and overrides only the entries it cares about:
 *
 *   register(Jedison, host, { labels: { placeholder: '- select -' } })
 *
 * A full translation is deliberately NOT kept here — no module served from this
 * directory carries a word of any language but English, so a language creeping
 * back in is a single grep away from being caught. Translations live with the
 * hosts, which is also where the wording is decided.
 *
 * WHY A CATALOGUE AND NOT A TRANSLATION LIBRARY: the editors are served as bare
 * ES modules over `/ui-extensions` and executed inside a host that already has
 * its own i18n stack — pulling a second one in would mean a second copy of it in
 * every consumer. A plain object the host merges into is the smallest thing that
 * keeps the wording where the wording is decided.
 *
 * Entries are either a string or a function of the values that vary. Keep them
 * functions rather than templates with placeholders: word order differs between
 * languages, and a host that has to reassemble a sentence from fragments is
 * exactly what this avoids.
 */

export const defaultLabels = {
  /** `title` and `aria-label` of the button that empties a reference. */
  clear: 'Clear selection',

  /** `title` and `aria-label` of the link that opens a referenced record. */
  openReference: 'Open referenced record',

  /** Shown by the single select and in the pill row while nothing is chosen. */
  placeholder: '— please choose —',

  /** A cascade whose source fields are still empty. `fields` is a joined list. */
  waitingFor: (fields) => `Select ${fields} first — nothing is loaded.`,

  /** The collection request failed. `message` is the underlying error. */
  loadFailed: (message) => `Could not load the list: ${message}`,

  /**
   * Server has more entries than were fetched (non-paged editors). The advice to
   * narrow it down applies to all of them — they read the whole collection, so a
   * truncated list is the one case where the user has to act.
   */
  truncated: (shown, total) => `List truncated: ${shown} of ${total} — narrow the selection further.`,

  /** Members skipped because the configured value property was missing. */
  dropped: (count) => `${count} entries without a value skipped.`,

  /** `aria-label` of a pill's remove button in the multiselect. */
  removeSelection: (label) => `remove ${label}`,

  /** `aria-label` of a row checkbox in the selection table. */
  selectRow: (label) => `select ${label}`,

  /** Header above the checkbox column of the selection table. */
  selectionColumn: 'Selection',

  /** Stand-in for a cell whose value is null or empty. */
  emptyCell: '—',

  previousPage: '‹ Back',
  previousPageAria: 'previous page',
  nextPage: 'Next ›',
  nextPageAria: 'next page',

  /** Selection summary of the table — `total` is null while it is unknown. */
  noneSelected: (total) => (total ? `${total} entries — none selected` : 'none selected'),
  selectedCount: (count, total) => `${count} selected${total ? ` of ${total} entries` : ''}`,

  /** How much of the collection the current page shows. */
  pageInfo: (shown, total) => `${shown} of ${total} shown`,
}

/**
 * Merges a host's overrides over the defaults. Shallow on purpose — an entry is
 * replaced whole, so a host cannot end up with half a sentence in one language
 * and half in another.
 *
 * @param {Record<string, unknown>} [overrides]
 * @returns {typeof defaultLabels}
 */
export function resolveLabels (overrides) {
  return { ...defaultLabels, ...(overrides || {}) }
}

/**
 * The hint line under a collection-backed editor. All five editors derive it
 * from the same `meta`, so it lives here rather than five times over.
 *
 * `shown` is the number of options the editor currently displays — the paged
 * table passes its page size, the others their full list.
 *
 * @param {typeof defaultLabels} labels
 * @param {{reason?: string, missing?: string[], message?: string, truncated?: boolean, total?: number|null, dropped?: number}} meta
 * @param {number} shown
 * @returns {string}
 */
export function collectionHint (labels, meta, shown) {
  const messages = []

  if ('incomplete' === meta.reason) {
    messages.push(labels.waitingFor((meta.missing || []).join(', ')))
  } else if ('error' === meta.reason) {
    messages.push(labels.loadFailed(meta.message))
  } else {
    if (meta.truncated) {
      messages.push(labels.truncated(shown, meta.total))
    }
    if (meta.dropped > 0) {
      messages.push(labels.dropped(meta.dropped))
    }
  }

  return messages.join(' ')
}
