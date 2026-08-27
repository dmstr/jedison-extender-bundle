// file generated with AI assistance: Claude Code - 2026-08-26

/**
 * Single reference into an API collection, entered through a type-ahead.
 *
 * Ported from the copy that lived in the consuming front ends. It existed twice
 * — once per application — and the copies had already drifted: one honoured
 * `x-value-property`, the other wrote the full IRI regardless. Serving it from
 * the API is what ends that.
 *
 * TWO THINGS THE ORIGINALS GOT WRONG, fixed here:
 *
 * 1. `x-collection` was read VERBATIM, so a placeholder in it never resolved.
 *    A field configured as `/api/user?role=field_{{ districtNo }}` therefore
 *    queried a literal `{{ districtNo }}` and returned an empty list — forever,
 *    and without an error. Templates are compiled here, the same way
 *    `CollectionSource` does it, including dependencies on sibling fields.
 * 2. The fetcher was a module singleton a consumer could only reach by
 *    importing an internal file path. It now comes from `host.fetchCollection`.
 *
 * AWESOMPLETE is required and is NOT bundled: like Jedison's own plugin editors
 * (`choices`, `flatpickr`, …) this one resolves its library from a global and
 * declines to resolve when it is absent. A host that wants this editor loads
 * Awesomplete; one that does not simply gets Jedison's plain string input,
 * which is usable rather than broken.
 */

import { compileCollectionUrl, resolveInstancePath } from '../_shared/collection-source.js'

const SEARCH_DEBOUNCE_MS = 300
const MAX_SUGGESTIONS = 50

/** Dot-separated property path, so `x-label-property` may address nested data. */
function readPath (object, path) {
  return String(path).split('.').reduce(
    (carry, key) => (carry === null || carry === undefined ? carry : carry[key]),
    object,
  )
}

/**
 * The label shown for a member. Falls back through the usual suspects so a
 * mis-set `x-label-property` yields something recognisable rather than blank.
 */
export function extractLabel (member, labelProperty) {
  const value = readPath(member, labelProperty)
  if (value !== null && value !== undefined) return String(value)
  return String(member.title ?? member.name ?? member['@id'] ?? 'Unknown')
}

/**
 * What gets STORED on selection: the property named by `x-value-property`, or
 * the full IRI when none is named.
 *
 * This is the setting the two front-end copies disagreed on. It matters beyond
 * taste: a backend column may hold a bare id, in which case an IRI silently
 * fails to match anything downstream.
 */
export function resolveStoredValue (member, schema) {
  const valueProperty = schema['x-value-property']
  if (!valueProperty) return member['@id']

  const value = readPath(member, valueProperty)
  return value === null || value === undefined ? member['@id'] : value
}

/**
 * @param {object} Jedison the host's Jedison namespace
 * @param {object} host    host services, notably `fetchCollection(url)`
 */
export function makeEditorIriReference (Jedison, host) {
  const { EditorString, Utils } = Jedison

  return class EditorIriReference extends EditorString {
    /**
     * Declines without Awesomplete — same contract as Jedison's own plugin
     * editors. Rendering a dead type-ahead would be worse than falling through
     * to the built-in string input.
     */
    static resolves (schema) {
      return schema?.format === 'iri-reference' &&
        schema?.type === 'string' &&
        Utils.isSet(schema['x-collection']) &&
        Boolean(globalThis.Awesomplete)
    }

    init () {
      super.init()

      this.searchSeq = 0
      this.debounceTimer = null
      this.awesomplete = null
      this.watchers = []
    }

    /** The collection URL with sibling-field placeholders resolved. */
    collectionUrl () {
      const schema = this.instance.schema
      const template = schema['x-collection']
      const watchMap = schema['x-watch'] || {}
      const data = {}

      for (const [alias, target] of Object.entries(watchMap)) {
        const instance = this.instance.jedison.getInstance(
          resolveInstancePath(this.instance.path, target),
        )
        data[alias] = {
          value: instance ? instance.getValue() : undefined,
          schema: instance ? instance.getSchema() : {},
          properties: [],
        }
      }

      return compileCollectionUrl(Jedison, template, data)
    }

    build () {
      this.control = this.theme.getInputControl({
        title: this.getTitle(),
        description: this.getDescription(),
        type: 'text',
        id: this.getIdFromPath(this.instance.path),
        titleHidden: Jedison.Schema.getSchemaXOption(this.instance.schema, 'titleHidden'),
        info: this.getInfo(),
      })

      this.control.input.setAttribute('data-iri-reference', 'true')
      this.control.input.setAttribute('autocomplete', 'off')

      this.initTypeAhead()
    }

    initTypeAhead () {
      const input = this.control?.input
      if (!input) return

      const ensure = () => {
        if (this.awesomplete) return
        this.awesomplete = new globalThis.Awesomplete(input, {
          minChars: 0,
          maxItems: MAX_SUGGESTIONS,
          autoFirst: true,
          // The server already filtered; filtering again client-side collapses
          // the dropdown while the user is still typing.
          filter: () => true,
          replace (suggestion) {
            this.input.value = suggestion.label
            this.input.setAttribute('data-selected-value', suggestion.value)
          },
        })
      }

      // Fetch on focus, so there is something to look at before typing.
      input.addEventListener('focus', () => {
        ensure()
        this.scheduleSearch(input.value)
      })

      input.addEventListener('input', () => {
        ensure()
        // Typing invalidates a previous pick — drop it, or `blur` would write
        // the stale value back.
        input.removeAttribute('data-selected-value')
        this.scheduleSearch(input.value)
      })
    }

    scheduleSearch (term) {
      if (this.debounceTimer) clearTimeout(this.debounceTimer)
      this.debounceTimer = setTimeout(() => { void this.runSearch(term) }, SEARCH_DEBOUNCE_MS)
    }

    async runSearch (term) {
      if (!this.awesomplete) return

      const seq = ++this.searchSeq
      const schema = this.instance.schema
      const searchProperty = schema['x-search-property'] || schema['x-label-property'] || 'name'

      let url = this.collectionUrl()
      const trimmed = (term || '').trim()
      if (trimmed) {
        url += (url.includes('?') ? '&' : '?') +
          encodeURIComponent(searchProperty) + '=' + encodeURIComponent(trimmed)
      }

      try {
        const collection = await (host.fetchCollection ?? defaultFetch)(url)

        // Discard a response belonging to an older keystroke, or one that
        // arrived after teardown.
        if (seq !== this.searchSeq || !this.control) return

        this.awesomplete.list = (collection['hydra:member'] ?? []).map((member) => ({
          label: extractLabel(member, schema['x-label-property'] || 'name'),
          value: resolveStoredValue(member, schema),
        }))
        this.awesomplete.evaluate()

        if (document.activeElement === this.control.input) {
          this.awesomplete.open()
        }
      } catch (cause) {
        console.error(`[iri-reference] search against ${url} failed:`, cause)
      }
    }

    addEventListeners () {
      this.control.input.addEventListener('awesomplete-selectcomplete', () => {
        const selected = this.control.input.getAttribute('data-selected-value')
        if (selected !== null) {
          this.instance.setValue(selected, true, 'user')
        }
      })

      this.control.input.addEventListener('blur', () => {
        // Only accept free-typed input when the field stores IRIs; with
        // `x-value-property` an arbitrary string is meaningless.
        if (this.instance.schema['x-value-property']) return

        const typed = this.control.input.value.trim()
        if (typed.includes('/')) {
          this.instance.setValue(typed, true, 'user')
        }
      })
    }

    refreshUI () {
      this.refreshDisabledState()

      const value = this.instance.getValue()
      if (value === null || value === undefined || value === '') {
        this.control.input.value = ''
        return
      }

      // With x-value-property the stored value is a bare id, and resolving it
      // back to a label would need a lookup this editor does not do — showing
      // the raw value is honest, showing nothing would not be.
      this.control.input.value = String(value)
    }

    destroy () {
      if (this.debounceTimer) clearTimeout(this.debounceTimer)
      this.searchSeq++
      if (this.awesomplete && typeof this.awesomplete.destroy === 'function') {
        this.awesomplete.destroy()
      }
      super.destroy()
    }
  }
}

function defaultFetch (url) {
  return fetch(url, { headers: { Accept: 'application/ld+json' } }).then((r) => r.json())
}
