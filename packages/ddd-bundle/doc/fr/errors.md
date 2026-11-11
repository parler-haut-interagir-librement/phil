<!-- phil-dev source-sha256=ebd153e8f8d48d69f6675a2d4b31f432b4d82a4580121ac244196ce61bd307ab -->
# Erreurs du domaine

Deux types dans `Phil\DddBundle\Domain\Exception`.

| Type | Nature | Rôle |
|---|---|---|
| `DomainError` | interface, étend `Throwable` | Ce que lève le domaine quand un invariant refuse quelque chose |
| `InvalidValue` | `final class`, étend `InvalidArgumentException`, implémente `DomainError` | Un objet-valeur a refusé sa propre construction |

## `DomainError`

Une interface plutôt qu'une classe de base : chaque contexte donne à ses erreurs la hiérarchie qui lui convient, et la
limite de l'application les attrape quand même toutes avec un seul `catch` :

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

`CommandBus::dispatch()` laisse sortir une `DomainError` exactement comme le *handler* l'a levée, jamais enveloppée
dans la `HandlerFailedException` de Messenger. L'appelant attrape donc `DomainError` sans connaître le bus. Voir
[command-and-query-buses.md](command-and-query-buses.md).

## `InvalidValue`

```php
final class InvalidValue extends InvalidArgumentException implements DomainError
{
    public static function because(string $reason): self;
}
```

Les objets-valeurs se valident eux-mêmes dans leur constructeur ou leur constructeur nommé, en PHP simple, et lèvent
`InvalidValue::because('…')` quand la valeur ne peut pas exister :

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

- **Les deux parents comptent.** `DomainError` est ce qu'attrape la limite de l'application ; `InvalidArgumentException`
  est ce qu'attrape déjà le code qui ne connaît pas le domaine.
- **Aucun validateur dans le domaine.** Un validateur produit une liste de violations destinée à un formulaire ; ici,
  l'échec est binaire et l'objet ne doit pas exister. Validez si vous le voulez les saisies de l'utilisateur avec
  `symfony/validator` à la périphérie, et laissez le dernier mot à l'objet-valeur.
- Le message est la seule charge utile. Écrivez-le pour le développeur qui lit le journal ; un message destiné à
  l'utilisateur est la traduction de l'application.

`Identifier::fromString()`, du bundle lui-même, lève `InvalidValue`, et `IdentifierType` la joint comme exception
précédente d'une erreur de conversion DBAL (voir [doctrine-identifier-type.md](doctrine-identifier-type.md)).
