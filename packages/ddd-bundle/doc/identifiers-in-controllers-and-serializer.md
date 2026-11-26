# Identifiers in controllers and the Serializer

Two optional integrations let an identifier cross the edge of the application typed:

- `Infrastructure\HttpKernel\IdentifierValueResolver` turns a route placeholder into the identifier class a
  controller argument declares, with `symfony/http-kernel`;
- `Infrastructure\Serializer\IdentifierNormalizer` writes an identifier as its canonical string and builds it back,
  with `symfony/serializer`.

Each is registered by the bundle when its component is installed, and only then. Nothing is configured.

Both use the canonical string, the internal representation of the identifier. They suit an API or a back office,
where that form may be seen. Where an identifier must not be shown as it is (a public URL, a token, an export), the
application maps it to the form it decides, as [identifiers.md](identifiers.md) says, and opts that argument out.

## Controller arguments

Type the argument with the identifier class, and name it like the route placeholder:

```php
use App\Ordering\Domain\OrderId;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class OrderController
{
    #[Route('/orders/{order}')]
    public function show(OrderId $order): Response
    {
        // $order is an OrderId, built with OrderId::fromString().
    }
}
```

No `#[MapEntity]`, no entity and no repository are involved: the controller receives the identifier and asks the
query bus for what it needs.

### What the resolver does

| The request attribute named like the argument | Result |
|---|---|
| A UUIDv7 string | An instance of the declared class |
| An identifier of the declared class, put there by a listener | That identifier, as it is |
| Any other string: not a UUID, a UUID of another version, empty | `NotFoundHttpException`: a **404** |
| An identifier of another class, an integer, an array, another object | `NotFoundHttpException`: a **404** |
| Missing, or `null` | Nothing: Symfony's own resolvers give the default value or `null`, or report the argument as unresolved |

A value that is not an identifier of the declared class names no resource: the answer is a 404, never a 500. The
exception message names the argument, not the value received; the `InvalidValue` thrown by `fromString()`, when there
is one, is kept as its previous exception for the logs.

The resolver leaves the argument to the other resolvers when its type is not a concrete subclass of `Identifier`:
no type, a scalar, `Identifier` itself, an abstract subclass, a union. A variadic argument is left alone too.

The resolver reads request attributes only, which is where the router puts placeholders. A query string or a body
is read by Symfony's `#[MapQueryParameter]`, `#[MapQueryString]` or `#[MapRequestPayload]`; the last two build their
object with the Serializer, and so use the normaliser below.

### Opting out

The resolver is tagged `controller.argument_value_resolver` under the name of its class. An argument opts out with
Symfony's attribute:

```php
use Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

public function legacy(#[ValueResolver(IdentifierValueResolver::class, disabled: true)] ?OrderId $order = null): Response
```

The resolver also reads that attribute itself, so the opt-out holds with the kernel in debug mode, where Symfony wraps
each resolver in a `TraceableValueResolver` and no longer recognises it by its class name.

## The Serializer

```php
final readonly class OrderView
{
    public function __construct(public OrderId $id, public string $reference) {}
}

$serializer->serialize(new OrderView($orderId, 'A-1042'), 'json');
// {"id":"0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e","reference":"A-1042"}

$view = $serializer->deserialize($json, OrderView::class, 'json');
// $view->id is an OrderId
```

Without the normaliser, the `ObjectNormalizer` prints an identifier as `{"value": …}` and cannot build one back,
since the constructor of `Identifier` is private.

| Direction | Input | Result |
|---|---|---|
| Normalisation | Any `Identifier` | Its `toString()`: the canonical RFC 4122 form, lowercase |
| Denormalisation | A UUIDv7 string, to a concrete subclass of `Identifier` | An instance of that class |
| Denormalisation | Any other string, or a value that is not a string | `NotNormalizableValueException` |
| Denormalisation | To `Identifier` itself, an abstract subclass or another class | `supportsDenormalization()` is false; `denormalize()` throws the Serializer's `InvalidArgumentException` |

The `NotNormalizableValueException` carries the path the Serializer reports (`deserialization_path`), the expected
type `string`, and the message `The data is not an identifier: a UUIDv7 string is expected.` Its message may be shown
to the user (`canUseMessageForUser()` is true): what failed is data a client sent. It names neither the value nor the
class. With `#[MapRequestPayload]`, the refusal becomes a 422 response, as for any other field.

`getSupportedTypes()` returns `[Identifier::class => true]`: the answer depends on the type alone, and the Serializer
caches it. The normaliser has the default priority, ahead of the `ObjectNormalizer`.

## Services

| Service id | Tag | Registered when |
|---|---|---|
| `Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver` | `controller.argument_value_resolver`, `name: Phil\DddBundle\Infrastructure\HttpKernel\IdentifierValueResolver` | `Symfony\Component\HttpKernel\Controller\ValueResolverInterface` exists |
| `Phil\DddBundle\Infrastructure\Serializer\IdentifierNormalizer` | `serializer.normalizer` | `Symfony\Component\Serializer\Normalizer\NormalizerInterface` exists |

Both are stateless and private: one instance serves every request of a worker.
