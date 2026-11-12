# The graph of a page

`PageStructuredData` holds the graph of the page being served. It is the `NodeCollector` of the container: any
service that describes something on a page asks for `Phil\StructuredData\Contract\NodeCollector` and adds its node.

## Where the nodes come from

| Source | When | Example |
|---|---|---|
| `DefaultNodes`, from the configuration | First, once per page | The `Organization`, the `WebSite`, the `WebPage` |
| Every `StructuredDataProvider` | After it, once per page, in the order of their `before` and `after` constraints | The `BreadcrumbList` of a menu, the telephone of the organisation |
| The listeners of `CollectStructuredData` | After the providers | A node that depends on the route |
| `NodeCollector::add()` | Whenever a controller, a template or a package adds one | The `Event` of an event's page, a contact card |

The providers and the listeners are asked the first time the graph is printed; what is added after that still
reaches the graph, should it be printed again. Nodes merge by `@id` where the first one stands, and a value given later
replaces an earlier one: what the page adds wins over what the providers give, which wins over the configuration. A
provider that gives `Node::of('Organization', ['telephone' => '+15145550100'], $page->siteId('organization'))`
completes the organisation of the configuration.

## The page

Each provider is given a `PageContext`:

| Member | Holds |
|---|---|
| `$baseUrl` | The address of the site, ending with `/`: `base_url`, else the request's scheme, host and base path |
| `$url` | The address of the page without its query: the base address and the request's path |
| `$locale` | The request's language as a BCP 47 tag, `fr-CA`; `null` for a locale that is none |
| `$request` | The main request |
| `siteId($fragment)` | `https://example.com/#organization` |
| `pageId($fragment)` | `https://example.com/jeux/tournoi#event` |

The identifiers the bundle gives are `#website` and `#organization` at the base address, `#webpage` and `#breadcrumb`
at the page's address. A package that gives the main content of a page names it after its kind at the page's address,
`#event`, `#book`, `#code`, so that another package can refer to it.

## Requests, fragments and workers

A page is the main request. A fragment rendered by a sub-request — an ESI, a `render()` — adds to the graph of its
page. What a page gathered is held by its request, in a weak map, so it goes with it. Outside a request — a command,
a message handler rendering a template — one graph is kept.

`PageStructuredData` is tagged `kernel.reset`: between two requests of a worker, FrankenPHP's or another, the kernel
forgets every graph. No node of one visitor's page can reach the next one's.

## A record withdrawn from search engines

A page that shows a record whose owner withdrew it from search engines — a `HasIndexingConsent` of
phil/indexing-consent whose `Indexing` is `NotIndexed` or `Unlisted` — prints no structured data: `structured_data()`
gives an empty string and asks for no nonce. The page is as narrow as the strictest of:

- the records named by `PageStructuredData::about()`, or by `structured_data_about()` in a template; an object
  without the contract is listed;
- the `Indexing` its main request carries under `IndexingPolicy::REQUEST_ATTRIBUTE`, set by the controller that knows
  the record. An `Indexing`, or its value as text, is read; any other value is read as `Unlisted`, so that an error
  never puts a withdrawn record back in sight.

| Method | Gives |
|---|---|
| `about(object ...$records): void` | Names the records the page shows |
| `indexing(): Indexing` | The reach of the page |
| `isPrintable(): bool` | Whether `Reader::StructuredData` is allowed |

The graph is still built, for the share metadata of the page, which search engines do not read. A record never
widens the page: once a withdrawn one is named, a listed one does not put the JSON-LD back. The reach is reset with
the graph, between two requests of a worker.

## Sharing a page

With phil/social-cards-bundle installed, `PageShareMetadata` is a source of its share metadata, autoconfigured: a page
is shared with what its graph says of it.

| Key | Read from |
|---|---|
| `title` | `headline`, then `name` |
| `description` | `description` |
| `image` | `image`, then `primaryImageOfPage`: an absolute address, or an `ImageObject` given or referred to |

The main entity is read first — the `mainEntity` of the page's `WebPage`, or else the node with a `mainEntityOfPage` —,
then the `WebPage`, for what the entity lacks. A page whose nodes give none of the three adds nothing. An image a
shared page cannot name, such as a relative address, is left out rather than stopping the page.

This source comes before what the request sets with `SharePage` or `social_page()` and what a template passes to
`social_meta()`: those keep the last word. The graph read is the one the page prints, so that its providers are asked
once per page.
