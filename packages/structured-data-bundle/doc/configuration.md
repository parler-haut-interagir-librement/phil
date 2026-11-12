# Configuration

```yaml
phil_structured_data:
    base_url: 'https://example.com/'      # the request's address when null
    language: ~                          # the language of every graph, BCP 47
    request_language: false              # or the request's language
    website:
        name: Example Press              # turns the WebSite on
        alternate_name: Example
        description: Jeux et livres
        url: ~                           # the base address when null
        search_url: 'https://example.com/recherche?q={search_term_string}'
    organization:
        type: Organization               # or Corporation, NGO, LocalBusiness, Store…
        name: Example Press        # turns the Organization on
        legal_name: ~
        url: ~                           # the base address when null
        logo: 'https://example.com/logo.png'
        description: ~
        email: bonjour@example.com
        telephone: '+15145550100'
        same_as: ['https://social.example/example-press']
        address:
            street_address: 1 rue Principale
            address_locality: Montréal
            address_region: QC
            postal_code: H2X 1Y4
            address_country: CA
    web_page: true
    breadcrumb:
        menu: main                       # a phil/menu-bundle menu
    nonce:
        request_attribute: ~             # e.g. script_nonce
        content_security_policy: false   # the script nonce of phil/browser-security-bundle
    check:
        timeout: 10
    allow_localhost: false
```

| Key | Default | Meaning |
|---|---|---|
| `base_url` | `null` | The address of the site, for the identifiers and the addresses of its nodes; without it, the scheme, host and base path of the request. A site behind a proxy or served under several hosts sets it, so that its identifiers stay the same |
| `language` | `null` | The language of the texts of every graph, written as the `@language` of its context |
| `request_language` | `false` | The graph in the language of the request when `language` is null |
| `website` | off | The site as a `WebSite`, `#website` at the base address; turned on by any of its keys, and then it needs a `name` |
| `website.search_url` | `null` | The address of the site's search, with `{search_term_string}` where the words go: a `SearchAction` |
| `organization` | off | The owner as an `Organization`, or the kind `type` names, `#organization` at the base address; turned on by any of its keys, and then it needs a `name` |
| `organization.address` | empty | Its `PostalAddress`, left out when every key is null |
| `web_page` | `true` | Each page as a `WebPage`, `#webpage` at its address — without its query —, part of the site when the site is on |
| `breadcrumb.menu` | `null` | The menu whose breadcrumb is each page's `BreadcrumbList`; needs phil/menu-bundle |
| `nonce.request_attribute` | `null` | The attribute of the main request that holds a nonce for the JSON-LD element; by default the element has none, and the page keeps its shared cache |
| `nonce.content_security_policy` | `false` | The JSON-LD element takes the script nonce of the policy phil/browser-security-bundle sends, which then names it; needs that bundle. A page with a nonce leaves shared caches |
| `check.timeout` | `10` | The seconds `phil:structured-data:check` waits for a page, 0.1 at least |
| `allow_localhost` | `false` | Whether an address of the graphs may be on `localhost` or under `.localhost`, for the factories of `Schema` and the validator of the check: `true` under `when@dev` and `when@test` only |

The site, its owner and the page carry the language of the request as their `inLanguage`.

## What is refused

A value no graph could hold stops the container from being built, with a message naming the key:

- an address — `base_url`, `website.url`, `website.search_url`, `organization.url`, `organization.logo`,
  `organization.same_as` — that is not absolute `http://` or `https://`, as `phil/url` reads it: with a user name or a
  password, on an IP address, or on `localhost` or under `.localhost` while `allow_localhost` is `false`; an
  international host is accepted;
- a `search_url` without `{search_term_string}`;
- a `language` that is not a BCP 47 tag; a locale with underscores, `fr_CA`, is read as `fr-CA`;
- an organisation `type` that is no schema.org term;
- a site or an owner turned on without a `name`;
- a site or an owner its type would not take: the nodes of the configuration are built once by `Schema` of
  phil/structured-data when the container is built, so a `LocalBusiness` without its address is refused there rather
  than on a page;
- both `nonce.request_attribute` and `nonce.content_security_policy`.
