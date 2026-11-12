<!-- phil-dev source-sha256=997e5561c1d557784eee939f21797f4c395b2d3a37b746fbeddd8de3351512d5 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Attribute\RateLimit` | L'attribut répétable : limiteur, quantité, stratégie de clé |
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
| `Application\RateLimitRules` | La valeur par défaut de route et l'option de formulaire lues comme des règles |
| `Application\RateLimitEnforcer` | Soumet une requête à ses règles |
| `Application\RateLimitHeaders` | Les en-têtes d'une décision |
| `Application\Event\RateLimitExceeded` | L'événement d'un refus |
| `Application\Event\RateLimitObserved` | L'événement d'un refus qu'un limiteur en observation n'a pas appliqué |
| `Application\Exception\TooManyRequests` | Le `429` d'un refus |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Infrastructure\Http\RateLimitListener` | `kernel.controller` et `kernel.response` |
| `Infrastructure\Http\TooManyRequestsResponder` | `kernel.exception` : le problème en JSON ou la page |
| `Infrastructure\Key\ClientIpKey`, `UserKey`, `UserOrIpKey`, `IpRouteKey` | Les quatre stratégies de clé |
| `Infrastructure\IpAnonymizer\NetworkAddress` | Le réseau du client avec phil/ip-anonymizer |
| `Infrastructure\RateLimiter\SymfonyLimiter` | Une fabrique de symfony/rate-limiter comme limiteur |
| `Infrastructure\Form\RateLimitTypeExtension` | Les soumissions des formulaires |
| `Infrastructure\DependencyInjection\RateLimitServices` | Les services de la configuration |
| `Infrastructure\DependencyInjection\LimitersPass` | Les limiteurs et les stratégies étiquetés, et les vérifications de la construction |
| `Infrastructure\Http\RateLimitCacheWarmer` | Arrête la construction sur une règle d'une route ou d'un contrôleur qui nomme ce que rien ne déclare |
| `PhilRateLimitBundle` | Le bundle et sa configuration ; il ajoute la `RateLimitGuard` de `phil/rate-limit`, qui refuse un stockage propre à chaque serveur en `prod` sauf si `single_server` est défini |

## Fichiers

| Fichier | Rôle |
|---|---|
| `templates/too_many_requests.html.twig` | La page de refus |
| `translations/phil_rate_limit.en.php`, `phil_rate_limit.fr.php` | Les mots de la page et de l'erreur de formulaire |
