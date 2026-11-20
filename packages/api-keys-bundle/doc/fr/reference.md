<!-- phil-dev source-sha256=58058b81f29bd55c3bb3ba1e08078bb8c2ed2396305a6abaceecf93a2a53133b -->
# Référence

Chaque type du bundle, par couche.

## Domain

| Type | Rôle |
|---|---|
| `Domain\Token\TokenFormat` | Génère les identifiants et les secrets, écrit et lit les jetons d'un préfixe |
| `Domain\Token\TokenOwner` | Pour qui un jeton agit : un type, `user` pour un membre, et un identifiant |
| `Domain\Token\PresentedToken` | Un jeton lu chez un client : identifiant et secret, affiché comme son identifiant, jamais sérialisé |
| `Domain\Token\Base62` | Interne : l'alphabet, des chaînes aléatoires sans biais, des sommes de contrôle de largeur fixe |
| `Domain\Model\ApiToken` | La ligne de `phil_api_token` et ses règles : propriétaire typé, expiration, révocation, grâce, utilisation à débit limité |
| `Domain\Contract\ApiTokenRepository` | Le port du stockage |
| `Domain\Event\TokenCreated`, `TokenRevoked`, `TokenUsed`, `TokenLeaked` | Les événements, chacun avec l'identifiant du jeton |
| `Domain\Exception\ApiKeysException` | Ce qu'implémente chaque exception du bundle |
| `Domain\Exception\MalformedToken`, `InvalidTokenValue` | Un texte qui n'est pas un jeton; une valeur qu'un jeton ne peut pas prendre |

## Application

| Type | Rôle |
|---|---|
| `Application\ApiTokens` | Crée, trouve, révoque (un jeton, ou tous ceux d'un propriétaire), renouvelle, purge et vérifie des jetons, et révoque ceux dont on signale la fuite |
| `Application\IssuedToken` | Un jeton qui vient d'être créé et son texte, montré une fois, jamais sérialisé |
| `Application\TokenHasher` | Le HMAC-SHA-256 gardé à la place du secret, qui nomme son algorithme et sa version, sous la première clé d'une liste ordonnée, comparé en temps constant à chaque clé |
| `Application\OwnerTypes` | Les types de propriétaires pour qui un jeton peut agir, et les portées que chacun peut détenir |
| `Application\ScopeCatalogue`, `ScopeDefinition` | Les portées déclarées, leurs descriptions et leurs libellés |
| `Application\CredentialFailure` | La raison du refus d'un jeton : mal formé, inconnu, révoqué, expiré |
| `Application\Contract\ClientAddressAnonymizer` | Ce que la ligne garde de l'adresse du client |
| `Application\Exception\InvalidCredentials`, `TokenNotFound`, `UnknownScope` | Les refus du service |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Attribute\RequiresScope` | Les portées qu'exige un contrôleur ou une action |
| `Infrastructure\Security\ApiTokenHandler` | Le gestionnaire de jetons de l'authentificateur `access_token` de Symfony : un jeton vers le badge de son propriétaire |
| `Infrastructure\Security\TokenAuthenticationListener` | Les rôles des portées et l'identifiant du jeton, sur le jeton de sécurité et la requête |
| `Infrastructure\Security\ScopeVoter` | Accorde `API_SCOPE` pour une portée que porte le jeton |
| `Infrastructure\Security\RequiresScopeListener` | Vérifie `#[RequiresScope]` avant le contrôleur |
| `Infrastructure\Security\ProblemErrorResponder` | Le `401` en détails de problème : un point d'entrée et un gestionnaire d'échec |
| `Infrastructure\SecretScanning\GitHub\GitHubSecretScanningController` | Le point de terminaison de l'analyse de secrets de GitHub |
| `Infrastructure\SecretScanning\GitHub\GitHubPublicKeys` | Les clés de GitHub, cherchées et gardées dans un bassin de cache |
| `Infrastructure\SecretScanning\GitHub\LeakReport` | Le corps qu'envoie GitHub, lu dans des bornes |
| `Infrastructure\SecretScanning\Exception\UnreadableReport` | Un rapport ou un document de clés qu'on ne peut pas lire |
| `Infrastructure\Doctrine\DoctrineApiTokenRepository` | Le stockage dans la table, par le gestionnaire d'entités |
| `Infrastructure\Memory\InMemoryApiTokenRepository` | Le stockage en mémoire, vidé entre les requêtes |
| `Infrastructure\RateLimit\TokenKey` | La stratégie de clé d'un limiteur de débit, par jeton |
| `Infrastructure\IpAnonymizer\AnonymizedClientAddress` | L'adresse par une stratégie de `phil/ip-anonymizer` |
| `Infrastructure\Console\CreateTokenCommand`, `ListTokensCommand`, `RevokeTokenCommand`, `PurgeExpiredTokensCommand` | Les commandes |
| `Infrastructure\Form\CreateTokenType` | Le formulaire de la page des membres, qui offre les portées qu'un type de propriétaire peut détenir |
| `Infrastructure\Http\TokenPageController` | La page des membres, la page des jetons d'un autre propriétaire derrière `MANAGE`, et la révocation |
| `Infrastructure\DependencyInjection\ApiKeysServices` | Le câblage de la configuration |
| `PhilApiKeysBundle` | Le bundle : sa configuration, l'association Doctrine, les services |
