// file generated with AI assistance: Claude Code - 2026-08-25

/**
 * Loads the options of a form field from an API collection, and RELOADS them
 * whenever a field the collection URL depends on changes.
 *
 * This is the mechanism ADR 0009 left open (follow-up 2): a cascading selection
 * where one field determines the next field's list — Landkreis -> Gemeinden ->
 * Reviere. It is deliberately client-side: the catalogue never enters the
 * process (ADR 0009, decision 2), so it leaves no trace in the engine history,
 * needs no credentials in the deployment artefact, and the user's Kreis scope
 * applies because the request goes through the normal HTTP layer.
 *
 * Schema contract, all read off the node that carries `x-collection` (for an
 * array field that is `items`):
 *
 *   x-collection            URL, may contain `{{ alias.value }}` placeholders
 *   x-watch                 { alias: '#/absolute/instance/path' }
 *   x-label-property        member property shown to the user
 *   x-value-property        member property stored as the value
 *   x-collection-page-size  optional, default 200
 *   x-collection-max-pages  optional, default 5
 *
 * TWO THINGS HERE ARE LOAD-BEARING, both of which produce a WRONG BUT PLAUSIBLE
 * list when removed rather than an error:
 *
 * 1. Placeholders must be DOTTED (`{{ gemeinden.value }}`). The server-side
 *    Dmstr\Flowable\Schema\ProcessVariablePlaceholderResolver rewrites every
 *    string of the schema, but its regex matches bare identifiers only — a dot
 *    makes the token unreachable for it. Written bare, and with a process
 *    variable of the same name in scope, the server substitutes it first; for an
 *    ARRAY variable `stringify()` yields the empty string, so the client would
 *    receive `?gemeindeAgs=` and load the whole catalogue.
 *
 * 2. Every alias referenced in the template is IMPLICITLY REQUIRED. If any of
 *    them is blank, no request is made at all. Without that guard an empty
 *    selection compiles to `?gemeindeAgs=` — which, under the usual
 *    "empty means unfiltered" semantics, is again the whole catalogue. A server
 *    should close the same hole from the other end and treat a filter that is
 *    present but empty as an empty set, not as no filter at all.
 */

const DEFAULT_PAGE_SIZE = 200
const DEFAULT_MAX_PAGES = 5

/* -------------------------------------------------------------------------- */
/* Pure helpers — no Jedison instance, no DOM, no network. Unit-testable.      */
/* -------------------------------------------------------------------------- */

/**
 * Collects the alias names a URL template refers to.
 * Matches both `{{ alias }}` and `{{ alias.value }}` so a bare token can be
 * DETECTED (and reported) rather than silently ignored.
 *
 * @param {string} template
 * @returns {string[]} unique alias names, in order of first appearance
 */
export function collectAliases (template) {
  if (typeof template !== 'string') return []

  const found = []
  const pattern = /\{\{\s*([A-Za-z_][A-Za-z0-9_]*)/g
  let match

  while ((match = pattern.exec(template)) !== null) {
    if (!found.includes(match[1])) found.push(match[1])
  }

  return found
}

/**
 * "Nothing selected" for the purposes of the load guard. An empty array counts,
 * which is the whole point: it is what a checkbox list with no ticks holds, and
 * `Array.prototype.toString()` would turn it into the empty string.
 *
 * @param {unknown} value
 */
export function isBlank (value) {
  if (value === null || value === undefined) return true
  if (typeof value === 'string') return value.trim() === ''
  if (Array.isArray(value)) return value.length === 0
  return false
}

/**
 * Compiles the collection URL. Uses Jedison's own `compileTemplate` rather than
 * a hand-rolled replace, so the coercion rules are identical to the ones the
 * form renderer applies elsewhere — in particular an array becomes `"a,b,c"`,
 * which is exactly the comma-separated form the API expects.
 *
 * `Jedison` is passed in rather than imported: this module runs inside the
 * HOST's Jedison instance (see index.js), and importing the library here would
 * pull in a second copy.
 *
 * @param {object} Jedison the host's Jedison namespace
 * @param {string} template
 * @param {Record<string, {value: unknown, schema: object, properties: string[]}>} watchedData
 * @returns {string}
 */
export function compileCollectionUrl (Jedison, template, watchedData) {
  return Jedison.Utils.compileTemplate(template, watchedData)
}

/**
 * Turns Hydra members into `{label, value}` option objects.
 *
 * Members without a value property are DROPPED and counted: rendering an option
 * whose value is `undefined` would store `undefined` on selection, which looks
 * like a data problem much later. `Landkreis.externeNummer` is nullable, so this
 * is a real case, not a hypothetical one.
 *
 * @param {object[]} members
 * @param {object} node the schema node carrying x-label-property/x-value-property
 * @returns {{options: {label: string, value: unknown}[], dropped: number}}
 */
export function mapMembers (members, node) {
  const labelProperty = node['x-label-property'] || 'name'
  const valueProperty = node['x-value-property']
  const options = []
  let dropped = 0

  for (const member of members) {
    const value = valueProperty ? readPath(member, valueProperty) : member['@id']

    if (value === null || value === undefined) {
      dropped++
      continue
    }

    const label = readPath(member, labelProperty)

    options.push({
      label: label === null || label === undefined ? String(value) : String(label),
      value,
      // The raw member travels along: a table renders arbitrary columns from it
      // (`x-columns`), which label/value alone cannot supply. Editors that only
      // need a list simply ignore it.
      member,
    })
  }

  return { options, dropped }
}

/**
 * Turns an `x-watch` target into an instance path valid in THIS document.
 *
 * The same schema is rendered in two shapes. Standalone its fields sit at the
 * root, so `gemeinden` means `#/gemeinden`. Wrapped by a host that puts form
 * fields inside an envelope — as the Flowable bundle does with `variables` —
 * the very same field becomes `#/variables/gemeinden`. A schema hard-coding
 * either form is broken in the other, and broken SILENTLY: watching a path that
 * does not exist never fires, so the dependent list simply never reloads.
 *
 * A relative target is therefore resolved against the editor's own position;
 * an absolute one (`#/…`) is honoured as written.
 *
 * @param {string} ownPath the editor instance's path, e.g. `#/variables/revierIds`
 * @param {string} target  the `x-watch` value
 * @returns {string}
 */
export function resolveInstancePath (ownPath, target) {
  if (target.startsWith('#')) {
    return target
  }

  let base = (ownPath || '#').slice(0, (ownPath || '#').lastIndexOf('/'))
  let rest = target

  while (rest.startsWith('../')) {
    base = base.slice(0, base.lastIndexOf('/'))
    rest = rest.slice(3)
  }

  return `${base || '#'}/${rest.replace(/^\.\//, '')}`
}

/** Dot-separated property path, same shape `x-label-property` allows. */
function readPath (object, path) {
  return String(path).split('.').reduce(
    (carry, key) => (carry === null || carry === undefined ? carry : carry[key]),
    object
  )
}

/* -------------------------------------------------------------------------- */
/* Fetcher — supplied by the host                                              */
/* -------------------------------------------------------------------------- */

/*
 * The host passes its own HTTP client in through `register(Jedison, host)`,
 * because that client is what carries the bearer token: each front end hangs it
 * into its own request interceptor, and a collection behind `/api` returns 401
 * without it.
 *
 * This used to be a module-level singleton with a setter. That only works if the
 * consumer imports this very file by its internal path and relies on module
 * cache identity — which ties every consumer to our file layout, the opposite of
 * a contract. The default below exists so a plain public collection still works
 * when a host passes nothing.
 */
export function defaultFetchCollection (url) {
  return fetch(url, { headers: { Accept: 'application/ld+json' } }).then((response) => {
    if (!response.ok) {
      throw new Error(`GET ${url} -> HTTP ${response.status}`)
    }
    return response.json()
  })
}

/* -------------------------------------------------------------------------- */
/* CollectionSource                                                            */
/* -------------------------------------------------------------------------- */

export class CollectionSource {
  /**
   * @param {object} Jedison   the host's Jedison namespace
   * @param {object} host      host services, notably `fetchCollection(url)`
   * @param {object} editor    the Jedison editor instance (needs .instance)
   * @param {object} node      schema node carrying x-collection (items for arrays)
   * @param {(options: {label: string, value: unknown}[], meta: object) => void} onOptions
   * @param {{paged?: boolean}} [options] `paged: true` reports ONE page per call
   *   and hands the navigation links along in the meta; the default walks every
   *   page and reports one complete list.
   */
  constructor (Jedison, host, editor, node, onOptions, options = {}) {
    this.Jedison = Jedison
    this.fetchCollection = host?.fetchCollection ?? defaultFetchCollection
    this.editor = editor
    this.node = node || {}
    this.onOptions = onOptions
    this.paged = Boolean(options.paged)

    this.template = this.node['x-collection']
    this.aliases = collectAliases(this.template)
    this.watchMap = this.node['x-watch'] || {}
    this.pageSize = this.node['x-collection-page-size'] || DEFAULT_PAGE_SIZE
    this.maxPages = this.node['x-collection-max-pages'] || DEFAULT_MAX_PAGES

    // Monotonic sequence, so a slow response for an older selection can never
    // overwrite a newer one.
    this.seq = 0
    this.registered = []
    this.lastUrl = null

    this.registerWatchers()
  }

  get jedison () {
    return this.editor.instance.jedison
  }

  /** @see resolveInstancePath — the shared implementation and its rationale. */
  resolvePath (target) {
    return resolveInstancePath(this.editor.instance.path, target)
  }

  /**
   * Registers our OWN watchers rather than relying on Jedison's `x-watch`
   * handling: that one feeds `x-template` (it writes a VALUE), while we need to
   * reload a list. Both can coexist on the same keyword.
   */
  registerWatchers () {
    for (const target of Object.values(this.watchMap)) {
      const path = this.resolvePath(target)
      const callback = () => { void this.reload() }
      this.jedison.watch(path, callback)
      this.registered.push({ path, callback })
    }
  }

  /**
   * Current `{alias: {value, schema, properties}}` map — the exact data shape
   * Jedison builds for `x-watch`, so `{{ alias.value }}` resolves the same way
   * it would in an `x-template`.
   */
  watchedData () {
    const data = {}

    for (const [alias, target] of Object.entries(this.watchMap)) {
      const instance = this.jedison.getInstance(this.resolvePath(target))
      data[alias] = {
        value: instance ? instance.getValue() : undefined,
        schema: instance ? instance.getSchema() : {},
        properties: instance && instance.schema && instance.schema.properties
          ? Object.keys(instance.schema.properties)
          : []
      }
    }

    return data
  }

  /**
   * Names of aliases that are referenced by the template but currently blank.
   * Also catches an alias that has no `x-watch` entry at all — that is a schema
   * error (a token nobody can ever fill), and treating it as "incomplete" keeps
   * us from firing a request with a literal `{{ … }}` in the query string.
   */
  missingAliases () {
    const data = this.watchedData()

    return this.aliases.filter(
      (alias) => !(alias in this.watchMap) || isBlank(data[alias].value)
    )
  }

  /**
   * The compiled URL for the current watched values, or `null` when a referenced
   * alias is blank. Shared entry point of both load modes.
   */
  currentUrl () {
    if (!this.template) return null

    const missing = this.missingAliases()

    if (missing.length > 0) {
      this.lastUrl = null
      this.onOptions([], { reason: 'incomplete', missing })
      return null
    }

    const url = withPageSize(
      compileCollectionUrl(this.Jedison, this.template, this.watchedData()),
      this.pageSize,
    )
    this.lastUrl = url

    return url
  }

  /**
   * Loads ONE page and reports its navigation links — for a paged view where the
   * user browses rather than picks from a complete list.
   *
   * Deliberately a separate method rather than a flag on `load()`: a boolean that
   * inverts what a method returns is the kind of API that costs a reader more
   * than it saves. Both modes share the guards.
   *
   * @param {string|null} pageUrl an absolute `hydra:view` link, or null for page 1
   */
  /**
   * Reloads in the mode the owning editor asked for. Watchers go through here
   * and NOT through `load()` directly: a paged editor whose cascade reloaded via
   * `load()` received the complete list on every dependency change — measured as
   * 11 rows in a table configured for 10, with the pager sitting inert because
   * there was no next page left to fetch. The first load looked correct, so the
   * mismatch only showed up after touching a dependency.
   */
  reload () {
    return this.paged ? this.loadPage() : this.load()
  }

  async loadPage (pageUrl = null) {
    const url = pageUrl ?? this.currentUrl()
    if (!url) return

    const seq = ++this.seq

    try {
      const page = await this.fetchCollection(url)

      // Discard a response for an older selection, or one that arrived after the
      // editor was torn down.
      if (seq !== this.seq) return

      const view = page['hydra:view'] || {}
      const { options, dropped } = mapMembers(page['hydra:member'] || [], this.node)

      this.onOptions(options, {
        url,
        paged: true,
        total: page['hydra:totalItems'] ?? options.length,
        dropped,
        next: view['hydra:next'] ?? null,
        previous: view['hydra:previous'] ?? null,
        first: view['hydra:first'] ?? null,
      })
    } catch (cause) {
      if (seq !== this.seq) return
      this.onOptions([], { reason: 'error', url, message: String(cause?.message || cause) })
    }
  }

  /**
   * Loads EVERY page (following `hydra:next` up to `maxPages`) and reports one
   * complete option list — for a picker that shows all choices at once.
   */
  async load () {
    const url = this.currentUrl()
    if (!url) return

    const seq = ++this.seq

    try {
      const members = []
      let next = url
      let pages = 0
      let total = null

      while (next && pages < this.maxPages) {
        const page = await this.fetchCollection(next)

        // Discard a response that belongs to an older selection, or one that
        // arrived after the editor was torn down.
        if (seq !== this.seq) return

        members.push(...(page['hydra:member'] || []))
        if (total === null && typeof page['hydra:totalItems'] === 'number') {
          total = page['hydra:totalItems']
        }

        next = (page['hydra:view'] || {})['hydra:next'] || null
        pages++
      }

      if (seq !== this.seq) return

      const { options, dropped } = mapMembers(members, this.node)

      this.onOptions(options, {
        url,
        total: total === null ? options.length : total,
        dropped,
        truncated: total !== null && members.length < total
      })
    } catch (cause) {
      if (seq !== this.seq) return
      this.onOptions([], { reason: 'error', url, message: String(cause && cause.message || cause) })
    }
  }

  /**
   * Unregisters the watchers. Without this a watcher outlives an editor rebuild
   * and fires into a destroyed editor — which shows up as an option list that
   * updates a control nobody can see any more.
   */
  destroy () {
    // Any in-flight response is now stale.
    this.seq++

    for (const { path, callback } of this.registered) {
      this.jedison.unwatch(path, callback)
    }

    this.registered = []
  }
}

/**
 * Adds `itemsPerPage` if the template did not set it. The API default is 30,
 * so a template without it would silently show the first 30 entries of a list
 * the user believes to be complete.
 */
function withPageSize (url, pageSize) {
  if (/[?&]itemsPerPage=/.test(url)) return url
  return url + (url.includes('?') ? '&' : '?') + 'itemsPerPage=' + pageSize
}
