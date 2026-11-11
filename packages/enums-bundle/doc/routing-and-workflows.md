# Routing, expressions and workflows

Three places outside forms and templates where an enum's declarations count: a controller argument read from the
route, the expressions of security, routing and validation, and Symfony Workflow.

## Controller arguments: #[ParseEnum]

Symfony reads a backed enum from a route attribute by its exact value. `#[ParseEnum]` reads it the way
`parse()` does — by value, then name, then `#[Alias]` —, so a URL keeps working after a value is renamed:

```php
use Phil\EnumsBundle\Infrastructure\Http\ParseEnum;

#[Route('/orders/{status}')]
public function list(#[ParseEnum(ignoreCase: true)] OrderStatus $status): Response
```

| Option | Default | Meaning |
|---|---|---|
| `attribute` | the argument's name | The route attribute to read. |
| `ignoreCase` | `false` | Compare without regard to ASCII letter case. |

- **A value that is no case gives a 404**, as a missing resource would; so does a missing value, unless the
  argument is nullable, which then receives `null`.
- **Any enum**, pure or backed, with or without `AsParsableEnumTrait`.
- **An argument that is not typed by an enum is a mistake** of the controller, and throws a `LogicException`.

It is registered with `symfony/http-kernel`, as a resolver the attribute targets by name: it never runs for an
argument without the attribute.

## Expression functions

Registered with `symfony/expression-language` for the expressions of security (`is_granted` with an expression),
routing (`condition`) and validation (`Expression`), beside the language's own `enum()`, which reads one case by its
constant:

| Function | Returns |
|---|---|
| `enum_meta(case, 'App\\Color')` | The value of a meta property on the case. |
| `enum_in_group(case, 'staff', …)` | Whether the case is in one of the groups; `false` for `null`. |
| `enum_from(value, 'App\\OrderStatus')` | The case of a value — a name for a pure enum —, or `null`. |

```php
$routes->add('open_orders', '/orders/{status}')
    ->condition("enum_in_group(enum_from(params['status'], 'App\\\\OrderStatus'), 'open')");
```

## Workflows from transitions

An enum whose cases declare `#[TransitionsTo]` already describes a state machine. The bundle gives it to
FrameworkBundle, so that guards, events, the history and the audit trail of Symfony Workflow apply to it:

```yaml
phil_enums:
    workflows:
        order:
            enum: App\Order\OrderStatus
            supports: [App\Order\Order]
            property: status          # the default
```

```php
$workflow = $registry->get($order, 'order');
$workflow->can($order, 'pending_to_paid');
$workflow->apply($order, 'pending_to_paid');   // $order->status is now OrderStatus::Paid
```

| Part of the workflow | From the enum |
|---|---|
| `type` | `state_machine`: one case at a time. |
| `places` | The values of the cases, as strings. |
| `transitions` | One per declared move, named `<from>_to_<to>` by the values. |
| `initial_marking` | The cases no case moves to. |
| `marking_store` | `method` on `property`; Symfony writes the case itself into a property typed by the enum. |

The enum must be backed — a place is a string — and declare at least one transition; the configuration refuses
otherwise. It is registered with `symfony/workflow`. A workflow needs more than the enum says — guards, metadata,
other transition names —? Declare it in FrameworkBundle's configuration instead; the enum's
`AsStatefulEnumTrait` still answers the same questions in templates.
