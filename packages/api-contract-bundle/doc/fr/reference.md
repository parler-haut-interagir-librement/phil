<!-- phil-dev source-sha256=fdce7ac77693f176217df4c0f67ebdc9b730087c6ace555fbb372259e4df2415 -->
# Référence

| Type | Nature | Rôle |
|---|---|---|
| `PhilApiContractBundle` | `final class`, `AbstractBundle` | Alias `phil_api_contract` ; la configuration et les services ; la constante `RULE_TAG` |
| `Application\Library` | `final class` | Les ports des bibliothèques dont le bundle crée les alias, par nom |
| `Infrastructure\Http\ProblemException` | exception | Un problème à retourner en réponse, et ses en-têtes |
| `Infrastructure\Http\ProblemDetailsResponse` | réponse | Un problème, `application/problem+json` |
| `Infrastructure\Http\ProblemDetailsListener` | écouteur de `kernel.exception` | Les exceptions sous forme de problèmes |
| `Infrastructure\Http\Idempotent` | attribut | Un contrôleur qui exige un `Idempotency-Key` |
| `Infrastructure\Http\IdempotencyListener` | écouteur | La garde de `phil/idempotency` autour d'un tel contrôleur, après ses contrôles d'accès ; constantes `REPLAYED_HEADER`, `RESERVED`, `PRIORITY`, `RESERVATION_LOST`, `KEY_FORMAT` |
| `Infrastructure\Http\IdempotencyReservation` | valeur | La réservation qu'une requête a prise, venue de `phil/idempotency`, et la rétention de sa réponse |
| `Infrastructure\Http\IdempotencyScopeResolver` | interface | Qui a envoyé une requête |
| `Infrastructure\Http\CallerScopeResolver` | résolveur | Le locataire, le client et l'utilisateur, hachés ; les appelants anonymes refusés ou rattachés à leur adresse ; constante `ANONYMOUS` |
| `Infrastructure\Http\IdempotencyTenantResolver` | interface | Le locataire dans lequel une requête agit |
| `Infrastructure\Http\ActiveTenantResolver` | résolveur | Le locataire actif de `phil/tenancy-bundle` |
| `Infrastructure\Http\IdempotencyClientResolver` | interface | Le client qui a envoyé une requête |
| `Infrastructure\Http\TokenAttributeClientResolver` | résolveur | Le client nommé par un attribut du jeton de sécurité ; constante `DEFAULT_ATTRIBUTE` |
| `Infrastructure\Http\AnonymousCallers` | énumération de chaînes | `Refuse` (`refuse`), `ByIp` (`ip`) |
| `Infrastructure\Http\DeprecatedEndpoint` | attribut | Un contrôleur obsolète et ses dates |
| `Infrastructure\Http\DeprecationListener` | écouteur | Ses en-têtes sur chaque réponse, son `Link` à côté de ceux de la réponse |
| `Infrastructure\DependencyInjection\CheckDeprecatedEndpoints` | passe de compilation | Chaque `#[DeprecatedEndpoint]` des services de contrôleur, construit en même temps que le conteneur |
| `Infrastructure\HttpClient\DeprecationObservingHttpClient` | décorateur du client HTTP | Les signaux des API appelées |
| `Infrastructure\Deprecation\CacheObservedDeprecations` | `ObservedDeprecations` | Les signaux, un élément par point de terminaison dans un *pool* de cache, conservés une semaine après leur dernière écriture |
| `Infrastructure\Doctrine\IdempotencySchemaListener` | écouteur Doctrine | La table `phil_idempotency` de `phil/idempotency` dans le schéma de l'ORM, construite pour la plateforme de la connexion |
| `Infrastructure\Console\DeprecationsCommand` | commande de console | `phil:api-contract:deprecations` |
| `Infrastructure\Console\VerifyCommand` | commande de console | `phil:api-contract:verify` |
| `Infrastructure\OpenApi\ContractVerifierFactory` | fabrique | Le vérificateur, à partir des règles de la bibliothèque, des règles étiquetées et de `verification.skip` |
| `Infrastructure\OpenApi\DocumentReader` | lecteur | Un document OpenAPI depuis un fichier JSON ou YAML, borné avant d'être lu |
