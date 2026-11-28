<!-- phil-dev source-sha256=7eceee28646ad21148bb3cfee8dba9e92cae27977cb036127a553535e9634515 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\ValueObject\Rule` | Une règle d'une valeur par défaut de route ou d'un formulaire : limiteur, quantité, stratégie de clé |
| `Domain\ValueObject\Decision` | Ce qu'a répondu un limiteur, et laquelle de deux décisions laisse le moins |
| `Domain\HeaderStyle` | `draft`, `legacy`, `both`, `none` |
| `Domain\Exception\RateLimitException` | Le marqueur des exceptions du bundle |
| `Domain\Exception\InvalidRateLimit` | Une règle mal écrite |
| `Domain\Exception\UnknownLimiter` | Un limiteur ou une stratégie de clé que rien ne déclare |

## Application

| Classe | Rôle |
|---|---|
| `Application\Contract\Limiter` | Le port d'un limiteur |
| `Application\Contract\KeyResolver` | Le port d'une stratégie de clé |
| `Application\Contract\AddressNormalizer` | Le port de la normalisation de l'adresse |
| `Application\Limiters` | Les limiteurs et leurs valeurs par défaut, par nom |
| `Application\KeyResolvers` | Les stratégies de clé, par nom |
| `Application\IdentifierKey` | `normalize()` : la forme d'un nom d'utilisateur ou d'une adresse courriel dont une clé est construite, comme la limitation de connexion de Symfony construit la sienne |
| `Application\RateLimitRules` | La valeur par défaut de route et l'option de formulaire lues comme des règles |
| `Application\RateLimitEnforcer` | Soumet une requête à ses règles, consomme une limite pour une clé hachée, garde la décision la plus serrée pour les en-têtes |
| `Application\RateLimitHeaders` | Les en-têtes d'une décision |
| `Application\Event\RateLimitExceeded` | L'événement d'un refus |
| `Application\Event\RateLimitObserved` | L'événement d'un refus qu'un limiteur en observation n'a pas appliqué |
| `Application\Exception\TooManyRequests` | Le `429` d'un refus |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Http\RateLimitListener` | `kernel.controller` : la valeur par défaut de route ; `kernel.response` : les en-têtes |
| `Infrastructure\Http\TooManyRequestsResponder` | `kernel.exception` : le problème en JSON ou la page, pour les refus du bundle et ceux de l'attribut de Symfony |
| `Infrastructure\Key\ClientIpKey`, `UserKey`, `UserOrIpKey`, `IpRouteKey` | Les quatre stratégies de clé |
| `Infrastructure\IpAnonymizer\NetworkAddress` | Le réseau du client avec phil/ip-anonymizer |
| `Infrastructure\RateLimiter\SymfonyLimiter` | Une fabrique de symfony/rate-limiter comme limiteur |
| `Infrastructure\RateLimiter\AttributeLimiterFactory` | Un limiteur du bundle comme le nomme le `#[RateLimit]` de Symfony, étiqueté `rate_limiter` |
| `Infrastructure\RateLimiter\AttributeLimiter` | Le limiteur d'une clé qu'elle crée : il consomme, et ne réserve ni ne réinitialise |
| `Infrastructure\RateLimiter\AttributeLimits` | La clé de l'attribut de Symfony changée en clé du bundle, et la consommation par l'exécuteur |
| `Infrastructure\Form\RateLimitTypeExtension` | Les soumissions des formulaires |
| `Infrastructure\DependencyInjection\RateLimitServices` | Les services de la configuration |
| `Infrastructure\DependencyInjection\LimitersPass` | Les limiteurs et les stratégies étiquetés, leurs fabriques pour l'attribut de Symfony, et les vérifications de la construction |
| `Infrastructure\Http\RateLimitCacheWarmer` | Arrête la construction sur une valeur par défaut de route ou un `#[RateLimit]` qui nomme ce que rien ne déclare |
| `PhilRateLimitBundle` | Le bundle et sa configuration ; il ajoute la `RateLimitGuard` de `phil/rate-limit`, qui refuse un stockage propre à chaque serveur en `prod` sauf si `single_server` est défini |

## Fichiers

| Fichier | Rôle |
|---|---|
| `templates/too_many_requests.html.twig` | La page de refus |
| `translations/phil_rate_limit.en.php`, `phil_rate_limit.fr.php` | Les mots de la page et de l'erreur de formulaire |
