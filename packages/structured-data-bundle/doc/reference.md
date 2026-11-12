# Reference

| Type | Role |
|---|---|
| `PhilStructuredDataBundle` | The bundle, extension alias `phil_structured_data` |
| `Application\PageStructuredData` | The `NodeCollector` of the current page, its graph, reset between requests |
| `Application\PageContext` | The addresses, language and request of the page; `siteId()` and `pageId()` |
| `Application\DefaultNodes` | The `WebSite`, `Organization` and `WebPage` of the configuration |
| `Application\Contract\StructuredDataProvider` | Gives nodes to every page |
| `Application\Contract\ScriptNonceProvider` | Gives the nonce of the page |
| `Application\Event\CollectStructuredData` | Dispatched once per page, after the providers |
| `Infrastructure\Menu\MenuBreadcrumbProvider` | The breadcrumb of a phil/menu-bundle menu |
| `Infrastructure\SocialCards\PageShareMetadata` | The share metadata of phil/social-cards-bundle, read from the graph |
| `Infrastructure\Twig\StructuredDataExtension` | `structured_data()`, `structured_data_add()` and `structured_data_about()` |
| `Infrastructure\Http\RequestAttributeNonce` | The nonce held by an attribute of the main request |
| `Infrastructure\Http\ContentSecurityPolicyNonce` | The script nonce of phil/browser-security-bundle's policy |
| `Infrastructure\Console\CheckStructuredDataCommand` | `phil:structured-data:check` |
| `Infrastructure\DependencyInjection\StructuredDataServices` | The services of a configuration |

| Service | Class |
|---|---|
| `Phil\StructuredDataBundle\Application\PageStructuredData`, public; alias `Phil\StructuredData\Contract\NodeCollector` | `PageStructuredData` |
| `Phil\StructuredDataBundle\Application\DefaultNodes` | `DefaultNodes` |
| `Phil\StructuredDataBundle\Infrastructure\Menu\MenuBreadcrumbProvider`, with `breadcrumb.menu` | `MenuBreadcrumbProvider` |
| `Phil\StructuredDataBundle\Infrastructure\Http\RequestAttributeNonce`, with `nonce.request_attribute` | `RequestAttributeNonce` |
| `Phil\StructuredDataBundle\Infrastructure\Http\ContentSecurityPolicyNonce`, with `nonce.content_security_policy` | `ContentSecurityPolicyNonce` |
| `Phil\StructuredDataBundle\Infrastructure\SocialCards\PageShareMetadata`, with phil/social-cards-bundle | `PageShareMetadata` |
| `Phil\StructuredDataBundle\Infrastructure\Twig\StructuredDataExtension`, with Twig | `StructuredDataExtension` |
| `Phil\StructuredData\Validation\Validator` | `Validator` |
| `Phil\StructuredDataBundle\Infrastructure\Console\CheckStructuredDataCommand` | `CheckStructuredDataCommand` |

| Tag | Holds |
|---|---|
| `phil_structured_data.provider` | The providers |
| `phil_structured_data.nonce_provider` | The nonce providers |
