# Testing

| Area | What is covered |
|---|---|
| `PageStructuredData` | Providers asked once, listeners, nodes added before and after printing, one graph per main request, fragments, reset, the configured or requested address and language, the reach of the records named and of the request attribute |
| `DefaultNodes` | Every key of the site and its owner, what is turned off, a page without an address |
| `MenuBreadcrumbProvider` | The breadcrumb, its link from the page, a page outside the menu |
| `PageShareMetadata` | Each node read, the order of the properties, the images left out, a page with nothing to say, providers asked once |
| `StructuredDataExtension`, `RequestAttributeNonce`, `ContentSecurityPolicyNonce` | Nodes and data added, the element printed once, the order of the nonces, nothing printed nor nonce asked for a withdrawn record, the nonce the policy sends |
| `CheckStructuredDataCommand` | Files and addresses, warnings and `--strict`, JSON, the bounds of a fetch, the failures |
| `PhilStructuredDataBundle` | The services of each configuration, each value refused, the nodes of the configuration built once |
| The functional application | A page whose configuration, provider, menu, controller and template give one graph, printed at the end of the body; two pages in one worker; a page shared from its graph, what the request sets winning over it; a page that shows a withdrawn record, then the next page of the worker |

```sh
vendor/bin/phpunit packages/structured-data-bundle/tests
```
