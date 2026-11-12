# Installation

## Requirements

| Package | Version | Used for |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/structured-data` | ^0.1 | The nodes, the graph, the JSON-LD, the validator |
| `phil/indexing-consent` | ^0.1 | No JSON-LD for a record withdrawn from search engines |
| `phil/url` | ^0.1 | The addresses of the configuration, read as a browser reads them |
| `symfony/config`, `symfony/dependency-injection` | ^8.2 | The configuration and the services |
| `symfony/http-foundation` | ^8.2 | The request of each page |
| `symfony/console` | ^8.2 | `phil:structured-data:check` |
| `symfony/service-contracts` | ^3.6 | The reset between requests |
| `psr/event-dispatcher` | ^1.0 | `CollectStructuredData` |
| `twig/twig` | ^3.21, optional | `structured_data()` and `structured_data_add()` |
| `symfony/http-client` | ^8.2, optional | Checking a page at its address |
| `phil/menu-bundle` | ^0.1, optional | The breadcrumb of a menu |
| `phil/browser-security-bundle` | ^0.1, optional | The script nonce of its content security policy, with `nonce.content_security_policy` |

## Registering

```php
// config/bundles.php
return [
    // ...
    Phil\StructuredDataBundle\PhilStructuredDataBundle::class => ['all' => true],
];
```

The bundle needs no route. Without configuration, each page has its `WebPage` and nothing else: the site and its owner
are turned on in the [configuration](configuration.md).

## The layout

Print the graph once, after the content has added its nodes — at the end of the body of the base layout:

```twig
    {{ structured_data() }}
</body>
```

A template that adds nodes — `contact_json_ld()`, `calendar_event_json_ld()`, `structured_data_add()` — is rendered
before it in a child template's blocks, since Twig renders the layout's blocks where they stand.
