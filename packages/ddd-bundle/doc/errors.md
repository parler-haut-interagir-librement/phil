# Domain errors

Two types in `Phil\DddBundle\Domain\Exception`.

| Type | Kind | Role |
|---|---|---|
| `DomainError` | interface, extends `Throwable` | What the domain throws when an invariant refuses something |
| `InvalidValue` | `final class`, extends `InvalidArgumentException`, implements `DomainError` | A value object refused its own construction |

## `DomainError`

An interface rather than a base class, so that each context gives its errors whatever hierarchy suits it while
the application boundary still catches them all with one `catch`:

```php
namespace App\Ordering\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

final class OrderAlreadyShipped extends DomainException implements DomainError
{
    public static function withId(string $orderId): self
    {
        return new self(sprintf('The order "%s" has already shipped.', $orderId));
    }
}
```

```php
try {
    $commandBus->dispatch(new CancelOrder($orderId));
} catch (DomainError $error) {
    // An invariant refused the command: show the message, return a 409 or a 422…
}
```

`CommandBus::dispatch()` lets a `DomainError` out exactly as the handler threw it, never wrapped in Messenger's
`HandlerFailedException`, so the caller catches `DomainError` without knowing the bus. See
[command-and-query-buses.md](command-and-query-buses.md).

## `InvalidValue`

```php
final class InvalidValue extends InvalidArgumentException implements DomainError
{
    public static function because(string $reason): self;
}
```

Value objects validate themselves in their constructor or named constructor, in plain PHP, and throw
`InvalidValue::because('…')` when the value cannot exist:

```php
final readonly class Quantity
{
    private function __construct(public int $value)
    {
    }

    public static function of(int $value): self
    {
        if ($value < 1) {
            throw InvalidValue::because(sprintf('A quantity is at least 1; %d is not.', $value));
        }

        return new self($value);
    }
}
```

- **Both parents matter.** `DomainError` is what the application boundary catches; `InvalidArgumentException` is
  what code that does not know the domain already catches.
- **No validator in the domain.** A validator produces a list of violations meant for a form; here failure is
  binary and the object must not exist. Validate user input with `symfony/validator` at the edge if you like,
  and let the value object have the last word.
- The message is the only payload. Write it for the developer reading the log; a user-facing message is the
  application's translation.

The bundle's own `Identifier::fromString()` throws `InvalidValue`, and `IdentifierType` attaches it as the previous
exception of a DBAL conversion error (see [doctrine-identifier-type.md](doctrine-identifier-type.md)).
