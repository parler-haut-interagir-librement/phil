# Extension points

| Point | How |
|---|---|
| The policy of one response, from code | `Application\ContentSecurityPolicyContext::override()` with a `PolicyOverride` |
| A nonce outside Twig | `ContentSecurityPolicyContext::nonce('script')`, `nonce('style')`, `sharedNonce()` |
| Inline code written at run time | `ContentSecurityPolicyContext::allowInline('script', $code)` |
| The policy of a controller | `#[Application\Attribute\ContentSecurityPolicy]` on its class or method |
| The policy of a route | Its `_csp` default |
| Any protection of one response, from code | `Application\BrowserSecurityContext::override()` with a `ProtectionOverride` |
| Any protection of a controller | `#[Application\Attribute\BrowserSecurity]` on its class or method |
| Any protection of a route | Its `_browser_security` default |
| A file someone uploaded | `Application\UserContentPolicy::apply()`, `#[Application\Attribute\UserContent]` or the `_user_content` route default |
| A violation reported | Listen to `Application\Event\ViolationReported` |
| How the bodies are throttled | Implement `Application\Contract\ReportThrottle`, name a rate limiter factory in `reporting.rate_limit.limiter`, or decorate `phil_browser_security.report_limit` with phil/rate-limit-bundle |
| How violations are deduplicated | Implement `Application\Contract\ReportDeduplicator` |
| What is kept of the client's address | Implement `Application\Contract\ClientAddressAnonymizer` |
| Where the violations are kept | Implement `Domain\Contract\ReportRepository` and alias it |
| Reading the violations | `ReportRepository::latest()` |
| The headers outside a request | `Application\HeaderBuilder::build()` with a `RequestState` |
| A signature outside the listener | `Application\CookieSigner` |
| The profiler panel | The template `@PhilBrowserSecurity/profiler/collector.html.twig`, overridden in `templates/bundles/PhilBrowserSecurityBundle/` |
| The clock | The `clock` option: the bundle never reads the time itself |

## Replacing a port

The receiver takes its collaborators by argument; an application replaces one by setting the argument of
`ReportReceiver` in its own services:

```yaml
services:
    App\Csp\SaltedAddress: ~

    Phil\BrowserSecurityBundle\Application\ReportReceiver:
        arguments:
            $anonymizer: '@App\Csp\SaltedAddress'
```

```php
use Phil\BrowserSecurityBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\IpAnonymizer\IpAddress;
use Phil\IpAnonymizer\KeyedHash;

final readonly class SaltedAddress implements ClientAddressAnonymizer
{
    public function __construct(private KeyedHash $hash)
    {
    }

    public function anonymize(string $address): ?string
    {
        $parsed = IpAddress::tryParse($address);

        return null === $parsed ? null : $this->hash->anonymize($parsed);
    }
}
```

The invariants:

- an anonymizer never returns the raw address;
- a throttle receives a keyed digest, never the address, and counts the body it accepts;
- a deduplicator remembers a fingerprint when it says it is new, for a window shared by the workers;
- a repository counts a fingerprint once per call to `record()`, and keeps no raw address.
