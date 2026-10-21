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
import { resolveLabels } from '../_shared/labels.js'

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
 * @param {object} host    host services: `fetchCollection(url)` and, optionally,
 *                         `resolveLink(iri, schema) -> string|null`, the address
 *                         at which the host shows the referenced record
 * @param {object} [options] `labels` overrides, see `../_shared/labels.js`
 */
export function makeEditorIriReference (Jedison, host, options = {}) {
  const { EditorString, Utils } = Jedison
  const labels = resolveLabels(options.labels)

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
      // True from the first keystroke until the input shows a stored value
      // again: only then is its text a search term.
      this.typing = false
      // True while the user asked for the list (focus, click, typing). A
      // search answer opens it only then, so a pick or a clear is not undone
      // by a late answer.
      this.wantsList = false
      // Set by a pick: Jedison re-renders the field after setValue() and puts
      // the focus back on the input, which is no request for the list.
      this.refocusExpected = false
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

      this.buildButtons()
      this.initTypeAhead()
    }

    /**
     * Puts a row of buttons next to the input: one that clears the value and,
     * where the host can show the referenced record (`host.resolveLink`), a
     * link to it. The link is an anchor with a real `href`, so it stays usable
     * while the form is disabled — the read-only view is exactly where it is
     * needed — and a middle click opens a tab.
     *
     * Without `host.resolveLink` no link is added: the bundle knows no front
     * end routes, only the host does.
     */
    buildButtons () {
      const input = this.control.input
      const row = document.createElement('div')
      row.style.display = 'flex'
      row.style.alignItems = 'flex-start'
      row.style.gap = '0.25rem'
      input.parentNode.insertBefore(row, input)
      row.appendChild(input)
      input.style.flex = '1 1 auto'
      input.style.minWidth = '0'
      this.row = row

      // Borrow the theme's button styling, so the buttons look like the other
      // controls of the form whatever theme the host uses.
      const themed = this.theme.getButton({ content: labels.clear })

      // As tall as the input in any theme. No `display` here: an inline one
      // would beat the `hidden` attribute that hides a button. Colours stay
      // with the theme and the host's own CSS.
      const fit = (button) => { button.style.alignSelf = 'stretch' }

      const clear = document.createElement('button')
      clear.type = 'button'
      clear.className = themed.className
      clear.classList.add('jedi-iri-clear')
      clear.setAttribute('title', labels.clear)
      clear.setAttribute('aria-label', labels.clear)
      clear.textContent = '×'
      clear.hidden = true
      fit(clear)
      clear.addEventListener('click', () => {
        this.dismissList()
        // The same value as emptying a plain string input gives.
        this.instance.setValue('', true, 'user')
      })
      row.appendChild(clear)
      this.clearButton = clear

      this.link = null
      if (typeof host.resolveLink !== 'function') return

      const link = document.createElement('a')
      link.className = themed.className
      link.classList.add('jedi-iri-link')
      link.setAttribute('title', labels.openReference)
      link.setAttribute('aria-label', labels.openReference)
      link.textContent = '↗'
      link.hidden = true
      fit(link)
      row.appendChild(link)
      this.link = link
    }

    /** The clear button shows while there is a value to clear and the field is editable. */
    refreshClear (value) {
      if (!this.clearButton) return
      const empty = value === null || value === undefined || value === ''
      this.clearButton.hidden = empty || this.control.input.disabled
    }

    /** Points the link at $value, or hides it when the host has no address for it. */
    refreshLink (value) {
      if (!this.link) return

      // Only an IRI identifies a record across collections; a bare id
      // (`x-value-property` naming another property) does not.
      const href = typeof value === 'string' && value.startsWith('/')
        ? host.resolveLink(value, this.instance.schema)
        : null

      if (href) {
        this.link.setAttribute('href', href)
        this.link.hidden = false
      } else {
        this.link.removeAttribute('href')
        this.link.hidden = true
      }
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
          // The stored value is the tooltip of an option, as it is of the
          // shown label.
          item (suggestion, term, index) {
            const item = globalThis.Awesomplete.ITEM(suggestion, term, index)
            item.setAttribute('title', String(suggestion.value))
            return item
          },
          replace (suggestion) {
            this.input.value = suggestion.label
            this.input.setAttribute('data-selected-value', suggestion.value)
          },
        })
        // Awesomplete wraps the input in its own container; in the row next
        // to the buttons that container has to take the width the input had.
        if (input.parentNode !== this.row) {
          input.parentNode.style.flex = '1 1 auto'
          input.parentNode.style.minWidth = '0'
        }
      }

      // Fetch on focus, so there is something to look at before typing. While
      // the input shows a stored value its text is no search term: listing
      // only the member it already shows would hide the other options. The
      // text is selected, so typing replaces it.
      const open = () => {
        ensure()
        this.wantsList = true
        if (!this.typing && input.value !== '') input.select()
        this.scheduleSearch(this.typing ? input.value : '')
      }
      input.addEventListener('focus', () => {
        if (this.refocusExpected) {
          this.refocusExpected = false
          return
        }
        open()
      })
      // The user is acting on the input: what follows is no refocus by Jedison.
      for (const type of ['pointerdown', 'keydown']) {
        input.addEventListener(type, () => { this.refocusExpected = false })
      }
      // A click on a field that already has focus does not focus it again.
      input.addEventListener('click', () => {
        if (!this.awesomplete?.isOpened) open()
      })

      input.addEventListener('input', () => {
        ensure()
        this.wantsList = true
        this.typing = true
        // Typing invalidates a previous pick — drop it, or `blur` would write
        // the stale value back.
        input.removeAttribute('data-selected-value')
        this.scheduleSearch(input.value)
      })
    }

    /** Closes the list and drops every search that could reopen it. */
    dismissList () {
      this.wantsList = false
      if (this.debounceTimer) clearTimeout(this.debounceTimer)
      this.searchSeq++
      this.awesomplete?.close()
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

        // Assigning the list can open it by itself; without a request for the
        // list from the user it must stay shut.
        if (!this.wantsList) {
          this.awesomplete.close()
        } else if (document.activeElement === this.control.input) {
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
          // A search still pending or in flight (focus and click each start
          // one) would reopen the list after the pick: cancel it.
          this.dismissList()
          this.refocusExpected = true
          // The input shows the picked label; remember it, or the re-render
          // that follows setValue() would replace it with the raw value.
          labelCache.set(this.labelCacheKey(selected), this.control.input.value)
          this.instance.setValue(selected, true, 'user')
        }
      })

      this.control.input.addEventListener('blur', () => {
        this.wantsList = false
        const typed = this.control.input.value.trim()
        // The shown label of the stored value is no input, even when it
        // contains a slash (model names such as `hf.co/org/model` do).
        if (this.shownLabel !== null && typed === this.shownLabel) return

        // Only accept free-typed input when the field stores IRIs; with
        // `x-value-property` an arbitrary string is meaningless.
        if (!this.instance.schema['x-value-property'] && typed.includes('/')) {
          this.instance.setValue(typed, true, 'user')
          return
        }

        // Any other text is a search that led nowhere: show what is stored.
        if (this.typing) this.refreshUI()
      })
    }

    refreshUI () {
      this.refreshDisabledState()

      const value = this.instance.getValue()
      this.refreshLink(value)
      this.refreshClear(value)
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

    /**
     * Shows $label, or the stored $value itself when there is no label. The
     * stored value is the tooltip of a label, so the machine value stays one
     * hover away.
     */
    showValue (value, label) {
      this.control.input.value = label ?? value
      this.control.input.title = label !== null && label !== undefined ? value : ''
      this.shownLabel = label ?? null
      this.typing = false
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
// - revised 2026-10-14 (link to the referenced record through host.resolveLink)
// - revised 2026-10-21 (clear button, all options on focus, stored value as tooltip)
