<!-- phil-dev source-sha256=09b3ad7db86b990f7e938cf87b2b8100ecde06a6744d964e7af009c5f1994bcf -->
# Référence

| Type | Rôle |
|---|---|
| `PhilStructuredDataBundle` | Le bundle, alias d'extension `phil_structured_data` |
| `Application\PageStructuredData` | Le `NodeCollector` de la page courante, son graphe, réinitialisé entre les requêtes |
| `Application\PageContext` | Les adresses, la langue et la requête de la page ; `siteId()` et `pageId()` |
| `Application\DefaultNodes` | Le `WebSite`, l'`Organization` et la `WebPage` de la configuration |
| `Application\Contract\StructuredDataProvider` | Fournit des nœuds à chaque page |
| `Application\Contract\ScriptNonceProvider` | Fournit le *nonce* de la page |
| `Application\Event\CollectStructuredData` | Réparti une fois par page, après les fournisseurs |
| `Infrastructure\Menu\MenuBreadcrumbProvider` | Le fil d'Ariane d'un menu de phil/menu-bundle |
| `Infrastructure\SocialCards\PageShareMetadata` | Les métadonnées de partage de phil/social-cards-bundle, lues dans le graphe |
| `Infrastructure\Twig\StructuredDataExtension` | `structured_data()`, `structured_data_add()` et `structured_data_about()` |
| `Infrastructure\Http\RequestAttributeNonce` | Le *nonce* contenu dans un attribut de la requête principale |
| `Infrastructure\Http\ContentSecurityPolicyNonce` | Le *nonce* de script de la politique de phil/browser-security-bundle |
| `Infrastructure\Console\CheckStructuredDataCommand` | `phil:structured-data:check` |
| `Infrastructure\DependencyInjection\StructuredDataServices` | Les services d'une configuration |

| Service | Classe |
|---|---|
| `Phil\StructuredDataBundle\Application\PageStructuredData`, public ; alias `Phil\StructuredData\Contract\NodeCollector` | `PageStructuredData` |
| `Phil\StructuredDataBundle\Application\DefaultNodes` | `DefaultNodes` |
| `Phil\StructuredDataBundle\Infrastructure\Menu\MenuBreadcrumbProvider`, avec `breadcrumb.menu` | `MenuBreadcrumbProvider` |
| `Phil\StructuredDataBundle\Infrastructure\Http\RequestAttributeNonce`, avec `nonce.request_attribute` | `RequestAttributeNonce` |
| `Phil\StructuredDataBundle\Infrastructure\Http\ContentSecurityPolicyNonce`, avec `nonce.content_security_policy` | `ContentSecurityPolicyNonce` |
| `Phil\StructuredDataBundle\Infrastructure\SocialCards\PageShareMetadata`, avec phil/social-cards-bundle | `PageShareMetadata` |
| `Phil\StructuredDataBundle\Infrastructure\Twig\StructuredDataExtension`, avec Twig | `StructuredDataExtension` |
| `Phil\StructuredData\Validation\Validator` | `Validator` |
| `Phil\StructuredDataBundle\Infrastructure\Console\CheckStructuredDataCommand` | `CheckStructuredDataCommand` |

| Étiquette | Contient |
|---|---|
| `phil_structured_data.provider` | Les fournisseurs |
| `phil_structured_data.nonce_provider` | Les fournisseurs de *nonce* |
