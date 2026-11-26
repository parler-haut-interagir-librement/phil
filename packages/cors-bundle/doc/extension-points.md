# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Choose the policy of a request another way: by host, by tenant, from a database | Implement `Phil\CorsBundle\Application\PolicyResolver` and alias the interface to your service |
| Keep the configured paths and add to them | Decorate `phil_cors.path_policy_resolver`, calling it when your own rule has no answer |
| Build a policy in code | `new CorsPolicy(...)`, which checks its values as the configuration does and raises `InvalidCorsPolicy` |
| Answer a preflight or decorate a response yourself | Inject `phil_cors.headers` (`CorsHeaders`) and call `preflight()` or `apply()` |

A resolver by host, for an application that serves several sites:

```php
use Phil\CorsBundle\Domain\CorsPolicy;
use Phil\CorsBundle\Application\PolicyResolver;
use Symfony\Component\HttpFoundation\Request;

final readonly class HostPolicyResolver implements PolicyResolver
{
    private CorsPolicy $shop;

    public function __construct(private PolicyResolver $paths)
    {
        $this->shop = new CorsPolicy(['https://shop.example.com'], allowMethods: ['GET', 'POST'], allowCredentials: true);
    }

    public function resolve(Request $request): ?CorsPolicy
    {
        if ('api.shop.example.com' === $request->getHost()) {
            return $this->shop;
        }

        return $this->paths->resolve($request);
    }
}
```

```yaml
# config/services.yaml
services:
    App\Cors\HostPolicyResolver:
        decorates: phil_cors.path_policy_resolver
        arguments: ['@.inner']
```

A resolver is called twice for a request, once on `kernel.request` for a preflight and once on `kernel.response`
otherwise: it keeps no state between requests, and builds its policies once, in its constructor or as services, since
building one compiles its patterns.

The services are stateless: they hold only the configuration, the same for every request of a long-running worker.
