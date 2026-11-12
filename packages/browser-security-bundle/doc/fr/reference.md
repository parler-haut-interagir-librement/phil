<!-- phil-dev source-sha256=adfbcc271fcd2039f96af0be1124532093d2c00d6b4697bcf56273abbae9946c -->
# Référence

## Domaine

| Type | Rôle |
|---|---|
| `Domain\Enum\Directive` | Une directive qu'une politique déclare ; si elle prend des sources, retombe sur `default-src`, prend des mots-clés de script. |
| `Domain\Enum\InlineKind` | `script` ou `style`, et les directives qui gouvernent chacun. |
| `Domain\Enum\Disposition` | `enforce` ou `report`. |
| `Domain\Enum\Protection` | Ce qu'une page peut ajuster à côté de sa politique, par le nom de l'en-tête ou de la protection. |
| `Domain\Enum\ExternalRedirectAction` | `refuse`, `fallback` ou `log`. |
| `Domain\Syntax\SourceSyntax` | La grammaire de chaque valeur ; `hashOf()`, la source d'un élément en ligne. |
| `Domain\Syntax\ProtectionSyntax` | Les valeurs de chaque protection, ce qu'y ajouter veut dire, le texte de son en-tête. |
| `Domain\ValueObject\Policy` | Directives et valeurs, vérifiées ; l'en-tête avec les jetons d'une requête. |
| `Domain\ValueObject\PermissionsPolicy` | La valeur de `Permissions-Policy`. |
| `Domain\ValueObject\ViolationReport` | Une violation telle que gardée ; son empreinte. |
| `Domain\ValueObject\StoredReport` | Une violation avec son compte et ses moments. |
| `Domain\Contract\ReportRepository` | L'endroit où les violations sont gardées. |
| `Domain\Exception\InvalidPolicy` | Une directive, une valeur ou une valeur d'en-tête refusée. |

## Application

| Type | Rôle |
|---|---|
| `Application\PolicyRegistry` | Les politiques nommées de la configuration. |
| `Application\PolicyDefinition` | Une politique nommée, avec `'strict-dynamic'` et les rapports. |
| `Application\PolicyOverride` | Ce qu'une route, un contrôleur ou un service change. |
| `Application\Attribute\ContentSecurityPolicy` | La politique d'un contrôleur. |
| `Application\ProtectionOverride` | Ce qu'une route, un contrôleur ou un service change dans n'importe quelle protection. |
| `Application\Attribute\BrowserSecurity` | Les protections d'un contrôleur. |
| `Application\UserContentPolicy` | Les en-têtes stricts d'un fichier téléversé par quelqu'un, et `apply()`. |
| `Application\Attribute\UserContent` | La politique du contenu d'usager sur les réponses d'un contrôleur. |
| `Application\BrowserSecurityContext` | Les surcharges des protections depuis le code. |
| `Application\ResolvedHeaders` | Les en-têtes d'une réponse. |
| `Application\CookieSigner` | Signe et vérifie la valeur d'un cookie. |
| `Application\RequestState` | Les surcharges, nonces, empreintes et en-têtes d'une requête. |
| `Application\ContentSecurityPolicyContext` | Nonces, empreintes et surcharges depuis le code. |
| `Application\HeaderBuilder` | Les en-têtes de politique d'une réponse. |
| `Application\SecurityHeaders` | Les en-têtes voisins, par chemin et avec les surcharges d'une réponse. |
| `Application\ReportParser` | Lit les deux formats de rapport. |
| `Application\ReportReceiver` | Reçoit un corps : type, taille, limitation de débit, rapports, magasin, événement. |
| `Application\Receipt` | Ce en quoi un corps a abouti, avec son statut. |
| `Application\Event\ViolationReported` | Une nouvelle violation. |
| `Application\Contract\ReportThrottle` | Combien de corps un client peut envoyer. |
| `Application\Contract\ReportDeduplicator` | Si une violation a été annoncée dans la fenêtre. |
| `Application\Contract\ClientAddressAnonymizer` | L'adresse du client telle que gardée. |
| `Application\Exception\UnknownPolicy` | Un nom de politique non déclaré. |
| `Application\Exception\InvalidReport` | Un corps qui ne peut pas être lu. |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Http\ContentSecurityPolicyListener` | Lit les surcharges de la politique, écrit ses en-têtes. |
| `Infrastructure\Http\SecurityHeadersListener` | Lit les surcharges des protections, écrit les en-têtes voisins. |
| `Infrastructure\Http\HttpsRedirectListener` | Envoie les requêtes en clair vers HTTPS. |
| `Infrastructure\Http\SignedCookiesListener` | Signe et vérifie les cookies nommés. |
| `Infrastructure\Http\ExternalRedirectListener` | Arrête les redirections vers un hôte non permis. |
| `Infrastructure\Http\UserContentListener` | Applique la politique du contenu d'usager par valeur par défaut de route ou par attribut. |
| `Infrastructure\Http\ReportEndpoint` | L'adresse des rapports d'une réponse. |
| `Infrastructure\Http\ReportController` | Le point d'entrée des rapports. |
| `Infrastructure\Twig\CspExtension` | `csp_nonce()` et `csp_meta()`. |
| `Infrastructure\Twig\NoncedImportMapRuntime` | `importmap()` avec le nonce des scripts. |
| `Infrastructure\RateLimiter\RateLimiterThrottle` | La limitation de débit sur `symfony/rate-limiter`. |
| `Infrastructure\RateLimit\LimiterReportThrottle` | La limitation de débit sur un limiteur de phil/rate-limit-bundle. |
| `Infrastructure\Cache\CacheDeduplicator` | La fenêtre de dédoublonnage dans un pool PSR-6. |
| `Infrastructure\IpAnonymizer\TruncatedClientAddress` | L'adresse par `phil/ip-anonymizer`. |
| `Infrastructure\Doctrine\DbalReportRepository` | Les violations dans `phil_csp_report`. |
| `Infrastructure\Doctrine\ReportTable` | La table. |
| `Infrastructure\Doctrine\ReportSchemaListener` | La table dans le schéma de l'ORM. |
| `Infrastructure\Console\PurgeReportsCommand` | `phil:csp:purge-reports`. |
| `Infrastructure\Profiler\CspDataCollector` | Le panneau du profileur. |
| `PhilBrowserSecurityBundle` | La configuration et le câblage ; il ajoute la `RateLimitGuard` de `phil/rate-limit`. |

## Services

| Id | Public |
|---|---|
| `Phil\BrowserSecurityBundle\Application\HeaderBuilder` | oui |
| `Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext` | oui |
| `Phil\BrowserSecurityBundle\Application\BrowserSecurityContext` | oui |
| `Phil\BrowserSecurityBundle\Application\UserContentPolicy` | oui |
| `Phil\BrowserSecurityBundle\Application\ReportReceiver` | oui, avec les rapports |
| `Phil\BrowserSecurityBundle\Domain\Contract\ReportRepository` | oui, avec le magasin |
| `phil_browser_security.report_limiter` | non ; la fabrique de limiteurs construite par le bundle |
| `phil_browser_security.report_limit` | non ; le limiteur `phil_browser_security.report` de phil/rate-limit-bundle, avec ce bundle |
| `phil_browser_security.importmap_runtime` | non ; le runtime de `importmap()` |
