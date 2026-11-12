# Overrides

The configuration is global; a route, a controller or a service changes any protection for its own responses, with the
same three verbs: add, replace, remove.

## The content security policy

A route, a controller or a service changes the policy of its own responses, in this order: the route's `_csp`
default, then the attributes of the controller's class, then those of its method, then the services, each override
applied to the result of the previous ones.

| Field | Effect |
|---|---|
| `policy` | Another named policy is enforced instead of `enforce`. |
| `add` | Sources added to directives; `'none'` gives way to them; a fetch directive the policy does not declare starts from `default-src`. |
| `replace` | The sources of directives replaced. |
| `remove` | Directives removed. |
| `reportOnly` (`report_only` on a route) | `true` sends the enforced policy as `Content-Security-Policy-Report-Only`, `false` enforces it again. |
| `disabled` | `true` sends no policy at all. |

`replace` comes before `add`, and `remove` last. `add`, `replace` and `remove` apply to the enforced and to the
report-only policy alike. Every source is checked like those of the configuration: an invalid one throws
`InvalidPolicy` when the response is written.

### The attribute

```php
use Phil\BrowserSecurityBundle\Application\Attribute\ContentSecurityPolicy;

#[ContentSecurityPolicy(add: ['img-src' => ['https://images.example.com']])]
final class GalleryController
{
    #[ContentSecurityPolicy(policy: 'preview')]
    public function preview(): Response
    {
        // ...
    }
}
```

The attribute is repeatable, on a class and on a method.

### The route default

```yaml
# config/routes.yaml
map:
    path: /map
    controller: App\Controller\MapController
    defaults:
        _csp:
            add: { img-src: ['https://tiles.example.com'], connect-src: ['https://tiles.example.com'] }
```

`_csp` takes `policy`, `add`, `replace`, `remove`, `report_only` and `disabled`; any other key, or a value of the wrong
type,
throws `InvalidPolicy`.

### From a service

```php
use Phil\BrowserSecurityBundle\Application\ContentSecurityPolicyContext;
use Phil\BrowserSecurityBundle\Application\PolicyOverride;

final readonly class EmbedRenderer
{
    public function __construct(private ContentSecurityPolicyContext $csp)
    {
    }

    public function render(Embed $embed): string
    {
        $this->csp->override(new PolicyOverride(add: ['frame-src' => [$embed->origin()]]));

        return $embed->html();
    }
}
```

The override goes to the main request, so a fragment rendered in a sub-request changes the policy of its page.

### A response with its own policy

A response that already carries `Content-Security-Policy` or `Content-Security-Policy-Report-Only` when it leaves the
controller keeps it untouched, and gets no other policy header.

## Every other protection

`#[BrowserSecurity]`, the route default `_browser_security` and `BrowserSecurityContext::override()` name what they
change by the name of the header or of the protection.

```php
use Phil\BrowserSecurityBundle\Application\Attribute\BrowserSecurity;

#[BrowserSecurity(
    add: ['Permissions-Policy' => ['camera' => ['self']]],
    replace: ['X-Frame-Options' => 'DENY', 'Cross-Origin-Resource-Policy' => 'cross-origin'],
    remove: ['Cross-Origin-Embedder-Policy'],
)]
final class VideoCallController
```

```yaml
share:
    path: /share/{id}
    controller: App\Controller\ShareController
    defaults:
        _browser_security:
            replace: { Cross-Origin-Resource-Policy: cross-origin }
            remove: [X-Frame-Options]
```

| Name | `add` | `replace` |
|---|---|---|
| `Content-Security-Policy` | sources by directive | sources by directive |
| `Permissions-Policy` | origins by feature | origins by feature |
| `Clear-Site-Data` | directives | directives |
| `Integrity-Policy`, `Integrity-Policy-Report-Only` | destinations | destinations |
| `signed-cookies` | names of cookies | names of cookies |
| `external-redirects` | hosts | hosts |
| `https-redirect` | no | `true` |
| `Strict-Transport-Security` | no | the text of the header |
| the other headers of [the list](headers.md) | no | a value of that header |

Any of them can be removed. For a given name, `replace` comes first, then `add`, then `remove`; each override starts from
the value of the previous one, and the first from the configuration. A header the response already carries stays
unless an override names it: then the override wins, and `remove` takes it off.

The order is the same as for the policy: the route, the class, the method, the services. `_browser_security` is read
before `_csp`. `signed-cookies` and `https-redirect` act before the controller is known, so only a route can change them:
an attribute or a service naming them throws `InvalidPolicy`.

```php
use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;

$this->browserSecurity->override(new ProtectionOverride(remove: ['X-Frame-Options']));
```

Every name and value is checked when the override is read; an unknown one throws `InvalidPolicy`.
