# Extension points

## `StructuredDataProvider`

`Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider` — gives nodes to every page. Autoconfigured,
tagged `phil_structured_data.provider` after `DefaultNodes`.

| Method | Contract |
|---|---|
| `nodes(PageContext $page): iterable<Node>` | The nodes this provider knows of for the page; asked once per page, when the graph is first printed |

A provider that must run before or after another one says so on its tag, with `before` or `after`.

## `ScriptNonceProvider`

`Phil\StructuredDataBundle\Application\Contract\ScriptNonceProvider` — the nonce of the page's content security
policy, for a site that wants one on every JSON-LD element. None is registered by default: a JSON-LD element is a data
block, which a policy does not execute, and a nonce would keep the page out of shared caches. The bundle registers
`RequestAttributeNonce` with `nonce.request_attribute`, and `ContentSecurityPolicyNonce` with
`nonce.content_security_policy`. Autoconfigured, tagged `phil_structured_data.nonce_provider`.

| Method | Contract |
|---|---|
| `scriptNonce(): ?string` | The nonce of the current page as base64 text, or `null` when this provider has none |

## `CollectStructuredData`

`Phil\StructuredDataBundle\Application\Event\CollectStructuredData` — dispatched once per page, after the providers.
A listener reads `$event->page` and calls `$event->add(Node ...)`.

## `NodeCollector`

`Phil\StructuredData\Contract\NodeCollector`, aliased to `PageStructuredData`: what a controller, a service or another
bundle asks for to add the nodes of the page. A bundle that only adds nodes depends on phil/structured-data, never on
this bundle, and asks for the collector with `NULL_ON_INVALID_REFERENCE`.

## The reach of a page

A record of the application implements `HasIndexingConsent` of phil/indexing-consent; its controller sets its
`Indexing` on the request under `IndexingPolicy::REQUEST_ATTRIBUTE`, or calls `PageStructuredData::about()`, and the
page prints no JSON-LD when the record is withdrawn from search engines. A bundle that shows records sets the request
attribute: it depends on phil/indexing-consent, never on this bundle.

## Configuration and services

| What | How |
|---|---|
| The site, its owner, the page | The [configuration](configuration.md) |
| The rules of the validator the command uses | Replace the `Phil\StructuredData\Validation\Validator` service, for instance with `new Validator()->withRules(...)` |
| The HTTP client of the command | The `http_client` service |

## What the bundle relies on

The `request_stack` and `event_dispatcher` services, both optional; Twig's attribute extensions; the `kernel.reset`
tag; phil/menu-bundle's `MenuExtension` for the breadcrumb; phil/social-cards-bundle's `ShareMetadataSource`, which
`PageShareMetadata` implements when that package is installed; phil/indexing-consent's `Indexing`, `IndexingPolicy` and
`HasIndexingConsent`; phil/browser-security-bundle's `ContentSecurityPolicyContext`, with
`nonce.content_security_policy`.
