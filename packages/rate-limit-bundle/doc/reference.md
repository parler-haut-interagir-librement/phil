# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\Attribute\RateLimit` | The repeatable attribute: limiter, amount, key strategy |
| `Domain\ValueObject\Decision` | What a limiter answered, and which of two decisions leaves less |
| `Domain\HeaderStyle` | `draft`, `legacy`, `both`, `none` |
| `Domain\Exception\RateLimitException` | The marker of the bundle's exceptions |
| `Domain\Exception\InvalidRateLimit` | A rule written wrong |
| `Domain\Exception\UnknownLimiter` | A limiter or a key strategy nothing declares |

## Application

| Class | Role |
|---|---|
| `Application\Contract\Limiter` | The port of a limiter |
| `Application\Contract\KeyResolver` | The port of a key strategy |
| `Application\Contract\AddressNormalizer` | The port of the address's normalization |
| `Application\Limiters` | The limiters and their defaults, by name |
| `Application\KeyResolvers` | The key strategies, by name |
| `Application\RateLimitRules` | The route default and the form option read as rules |
| `Application\RateLimitEnforcer` | Holds a request to its rules |
| `Application\RateLimitHeaders` | The headers of a decision |
| `Application\Event\RateLimitExceeded` | The event of a refusal |
| `Application\Event\RateLimitObserved` | The event of a refusal a limiter in observation did not enforce |
| `Application\Exception\TooManyRequests` | The `429` of a refusal |

## Infrastructure

| Class | Role |
|---|---|
| `Infrastructure\Http\RateLimitListener` | `kernel.controller` and `kernel.response` |
| `Infrastructure\Http\TooManyRequestsResponder` | `kernel.exception`: the problem in JSON or the page |
| `Infrastructure\Key\ClientIpKey`, `UserKey`, `UserOrIpKey`, `IpRouteKey` | The four key strategies |
| `Infrastructure\IpAnonymizer\NetworkAddress` | The client's network with phil/ip-anonymizer |
| `Infrastructure\RateLimiter\SymfonyLimiter` | A factory of symfony/rate-limiter as a limiter |
| `Infrastructure\Form\RateLimitTypeExtension` | The submissions of forms |
| `Infrastructure\DependencyInjection\RateLimitServices` | The services of the configuration |
| `Infrastructure\DependencyInjection\LimitersPass` | The tagged limiters and strategies, and the build's checks |
| `Infrastructure\Http\RateLimitCacheWarmer` | Stops the build on a rule of a route or a controller that names what nothing declares |
| `PhilRateLimitBundle` | The bundle and its configuration; it adds the `RateLimitGuard` of `phil/rate-limit`, which refuses a store local to each server in `prod` unless `single_server` is set |

## Files

| File | Role |
|---|---|
| `templates/too_many_requests.html.twig` | The refusal page |
| `translations/phil_rate_limit.en.php`, `phil_rate_limit.fr.php` | The words of the page and of the form error |
