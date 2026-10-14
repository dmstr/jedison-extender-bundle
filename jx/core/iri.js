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

import { compileCollectionUrl, defaultFetchCollection, resolveInstancePath } from '../_shared/collection-source.js'

const SEARCH_DEBOUNCE_MS = 300
const MAX_SUGGESTIONS = 50

/**
 * Labels of stored values, shared by every instance: a form re-renders its
 * editors often, and the same reference shows up in many forms. Keyed by the
 * `x-collection` template and the value, because a bare id is unique per
 * collection only. A value without a label is cached as `null`, so a dead
 * reference costs one lookup, not one per render.
 */
const labelCache = new Map()

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
      this.labelSeq = 0
      // The label the input shows instead of the stored value, or null when
      // it shows the value itself; `blur` must not take it for typed input.
      this.shownLabel = null
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

    /** The collection URL narrowed to members matching $term (`x-search-property`). */
    searchUrl (term) {
      const schema = this.instance.schema
      const searchProperty = schema['x-search-property'] || schema['x-label-property'] || 'name'

      let url = this.collectionUrl()
      const trimmed = (term || '').trim()
      if (trimmed) {
        url += (url.includes('?') ? '&' : '?') +
          encodeURIComponent(searchProperty) + '=' + encodeURIComponent(trimmed)
      }
      return url
    }

    fetch (url) {
      return (host.fetchCollection ?? defaultFetchCollection)(url)
    }

    labelCacheKey (value) {
      return `${this.instance.schema['x-collection']}\u0000${value}`
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
      const url = this.searchUrl(term)

      try {
        const collection = await this.fetch(url)

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
          // The input shows the picked label; remember it, or the re-render
          // that follows setValue() would replace it with the raw value.
          labelCache.set(this.labelCacheKey(selected), this.control.input.value)
          this.instance.setValue(selected, true, 'user')
        }
      })

      this.control.input.addEventListener('blur', () => {
        // Only accept free-typed input when the field stores IRIs; with
        // `x-value-property` an arbitrary string is meaningless.
        if (this.instance.schema['x-value-property']) return

        const typed = this.control.input.value.trim()
        // The shown label of the stored value is no input, even when it
        // contains a slash (model names such as `hf.co/org/model` do).
        if (this.shownLabel !== null && typed === this.shownLabel) return
        if (typed.includes('/')) {
          this.instance.setValue(typed, true, 'user')
        }
      })
    }

    refreshUI () {
      this.refreshDisabledState()

      const value = this.instance.getValue()
      if (value === null || value === undefined || value === '') {
        this.labelSeq++
        this.showValue('', null)
        return
      }

      // The stored value is an IRI or a bare id; the user should see the
      // member's label instead. Until the lookup answers, and when it finds
      // nothing, the raw value is shown — honest, where a blank would not be.
      const stored = String(value)
      const key = this.labelCacheKey(stored)
      if (labelCache.has(key)) {
        this.showValue(stored, labelCache.get(key))
        return
      }
      this.showValue(stored, null)
      void this.resolveLabel(stored, key)
    }

    /** Shows $label, or the stored $value itself when there is no label. */
    showValue (value, label) {
      this.control.input.value = label ?? value
      this.shownLabel = label ?? null
    }

    async resolveLabel (value, key) {
      const seq = ++this.labelSeq
      let label = null
      try {
        label = await this.lookupLabel(value)
      } catch (cause) {
        console.error(`[iri-reference] label lookup for ${value} failed:`, cause)
        return
      }
      labelCache.set(key, label)

      // Drop an answer that is out of date (another value, teardown) or that
      // would overwrite what the user is typing.
      if (seq !== this.labelSeq || !this.control) return
      if (String(this.instance.getValue()) !== value) return
      if (this.control.input.value !== value) return
      this.showValue(value, label)
    }

    /**
     * The label of the member a stored value refers to, or null. An IRI is
     * fetched as an item. A bare value is tried as an item of the collection
     * (`<collection path>/<value>`, the API Platform item route) and else
     * searched through `x-search-property`, matched against the stored value
     * or the last segment of `@id` — so it works whether the backend stores
     * an id or `x-value-property` names another property, provided the
     * search can find it.
     */
    async lookupLabel (value) {
      const schema = this.instance.schema
      const labelProperty = schema['x-label-property'] || 'name'
      const label = (member) => (member && member['@id'] ? extractLabel(member, labelProperty) : null)

      if (value.includes('/')) {
        return label(await this.fetch(value))
      }

      const collectionPath = this.collectionUrl().split('?')[0].replace(/\/+$/, '')
      try {
        const item = label(await this.fetch(`${collectionPath}/${encodeURIComponent(value)}`))
        if (item !== null) return item
      } catch {
        // Not an item id of this collection; fall through to the search.
      }

      const collection = await this.fetch(this.searchUrl(value))
      const member = (collection['hydra:member'] ?? []).find((candidate) =>
        String(resolveStoredValue(candidate, schema)) === value ||
        String(candidate['@id'] ?? '').endsWith(`/${value}`),
      )
      return label(member)
    }

    destroy () {
      if (this.debounceTimer) clearTimeout(this.debounceTimer)
      this.searchSeq++
      this.labelSeq++
      if (this.awesomplete && typeof this.awesomplete.destroy === 'function') {
        this.awesomplete.destroy()
      }
      super.destroy()
    }
  }
}

// - revised 2026-10-09 (label lookup for stored values, label cache, shared fetch)
