# Placing a flag

A flag is read in four places, all through the same `FlagEvaluator`, so within a request the controller, the
template and the core read the same memoized evaluation for the same context.

## At the edge: the controller attribute

```php
use Phil\FeatureFlagsBundle\Infrastructure\Http\IsFeatureEnabled;

#[IsFeatureEnabled('new-checkout')]                                   // 404 while the flag is off
final class CheckoutController
{
    #[IsFeatureEnabled('checkout-layout', variant: 'two-columns')]    // 404 outside that arm
    public function twoColumns(): Response { /* … */ }
}
```

`IsFeatureEnabled(string $flag, ?string $variant = null)` targets a class or a method and is repeatable.
`FeatureGateListener` listens to `kernel.controller` and, for every `IsFeatureEnabled` on the controller class and
method, evaluates the flag:

- without `variant`, the controller runs only while the flag is on;
- with `variant`, only for contexts the experiment puts in that variant, so each arm can have controllers of its own.

Otherwise it throws `NotFoundHttpException`. **A flag that is off answers 404, not 403**: a feature that is not
released does not exist yet, and saying it is forbidden would say that it does.

An invalid flag name in the attribute throws `InvalidValue` when the controller is called (a 500), not at build
time.

## In routing: the route condition

`FlagRoutingCondition` is registered as a routing condition service under the alias `feature_flags`, with two
methods, `enabled(string $flag): bool` and `variant(string $flag): ?string`:

```yaml
# A route that matches only while its flag is on; the next route with the same path answers otherwise.
checkout_v2:
    path: /checkout
    controller: App\Controller\CheckoutV2Controller
    condition: "service('feature_flags').enabled('new-checkout')"

checkout:
    path: /checkout
    controller: App\Controller\CheckoutController
```

```php
#[Route('/checkout', condition: "service('feature_flags').variant('checkout-layout') == 'two-columns'")]
```

Declare the flagged route **before** the current one. Route conditions need `symfony/expression-language`, and they
are evaluated at match time, on every request, so the matcher calls the evaluator.

**A route condition reads only a flag that targets neither subjects nor tenants.** The router matches the request
in `RouterListener` (`kernel.request`, priority 32), before the firewall (priority 8) loads the user and before
the listeners that usually set the tenant: the context it would evaluate against does not know who asks. A flag
with a membership or a percentage rule, or an experiment, on the subject or the tenant is therefore refused: the
condition throws `FlagNotRoutable` the first time it evaluates such a flag, with a message naming the flag, and the
request answers 500. The check runs on every evaluation, so a flag edited in the admin to target tenants is refused
from its next request on. A flag on or off for everyone fits, and so does one targeted at projects, provided the
resolver can tell the project from the request alone (its host, for instance) before routing.

**A flag targeted at subjects or tenants guards a controller with `#[IsFeatureEnabled]`**, which runs on
`kernel.controller`, after the firewall, so it sees the authenticated user. To serve a new controller on the path of
the current one for some users only, route the path to one controller and branch inside it, or guard the new
controller with the attribute on a path of its own.

## In templates: the Twig functions

`FeatureFlagsExtension` declares two functions with `#[AsTwigFunction]`:

| Function | Returns |
|---|---|
| `feature_enabled(flag)` | `bool` |
| `feature_variant(flag)` | the variant name as a string, or `null` |

```twig
{% if feature_enabled('new-checkout') %}…{% endif %}
{% if feature_variant('checkout-layout') == 'two-columns' %}…{% endif %}
```

They need TwigBundle (the extension is registered through the `twig.attribute_extension` and `twig.runtime` tags).

## In the core

A service asks `FlagEvaluator::isEnabled()` or `variant()` **once**, at the point where it chooses between two
implementations, and a named method of the application keeps the flag's name in one place:

```php
final readonly class OrderMailer
{
    public function __construct(private FlagEvaluator $flags) {}

    private function includeOrderCancellationInEmail(): bool
    {
        return $this->flags->isEnabled(FlagName::of('order-cancellation-in-email'));
    }
}
```

When the flag is retired, only that method and its callers change. Avoid scattering `isEnabled()` calls on the
same flag across layers: each one is a branch to remove later.

A flag can also choose an implementation at the service level, with a small dispatcher that holds both
implementations and asks the evaluator on each call: the decision stays per request, unlike a factory resolved
once when the container builds the service.

## Choosing the place

| Place | Use it for |
|---|---|
| Controller attribute | A whole new page or endpoint that must not exist while the flag is off. |
| Route condition | A new controller replacing the current one on the same path, for a flag on or off for everyone (or by project). |
| Controller attribute, for a targeted flag | A feature for some subjects or tenants: the attribute runs after the firewall. |
| Twig | A block of a page shown or hidden, a variant of a layout. |
| Core | A behaviour change inside a service (a new calculation, a new e-mail section). |
