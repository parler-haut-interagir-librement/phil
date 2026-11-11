<!-- phil-dev source-sha256=43372544a6f1e1a625fa231044a5a119d068591186f093951bc722efc9d96945 -->
# Identifiants

`Phil\DddBundle\Domain\Identifier\Identifier` est la base abstraite de tout identifiant d'agrégat. Elle enveloppe un
UUIDv7 de `symfony/uid`.

## Déclarer un identifiant

Une sous-classe vide par agrégat :

```php
namespace App\Ordering\Domain;

use Phil\DddBundle\Domain\Identifier\Identifier;

final readonly class OrderId extends Identifier
{
}
```

Le constructeur est `final private` : on n'obtient un identifiant que par `generate()`, `fromString()` ou `fromUuid()`,
et une sous-classe ne peut ni ajouter d'état ni changer la façon dont il est construit.

## API

| Membre | Retourne | Comportement |
|---|---|---|
| `public UuidV7 $value` | | L'UUID enveloppé, en lecture seule |
| `static generate()` | `static` | Un nouvel identifiant avec un UUIDv7 neuf, de la sous-classe sur laquelle il est appelé |
| `static fromString(string $value)` | `static` | Analyse une chaîne UUID ; lève `InvalidValue` quand ce n'est pas un UUID, ou que c'est un UUID d'une autre version |
| `static fromUuid(Uuid $uuid)` | `static` | Enveloppe un UUID déjà analysé, sans l'écrire puis l'analyser de nouveau ; lève `InvalidValue` quand ce n'est pas un v7. `IdentifierType` construit ainsi un identifiant lu dans une colonne binaire |
| `equals(Identifier $other)` | `bool` | Vrai seulement quand les deux sont de la **même classe** et portent le même UUID |
| `toString()` | `string` | La forme canonique RFC 4122, en minuscules : `0192f3c4-8a1e-7b2c-9d3e-4f5a6b7c8d9e` |
| `__toString()` | `string` | Comme `toString()` |

```php
$id = OrderId::generate();
$same = OrderId::fromString($id->toString());

$id->equals($same);                                   // true
$id->equals(CustomerId::fromString($id->toString())); // false: another identifier class
OrderId::fromString('philippe');                      // InvalidValue: not a UUID
OrderId::fromString(Uuid::v4()->toRfc4122());         // InvalidValue: a UUID, but not a v7
```

## Choix de conception

- **UUIDv7, jamais v4.** Un v7 est ordonné dans le temps : dans un index B-tree, une nouvelle clé arrive en queue au
  lieu de scinder des pages au hasard, et l'identifiant porte sa propre date de création (`$id->value->getDateTime()`).
  `fromString()` refuse un v4 même si c'est un UUID valide : l'admettre laisserait entrer dans le stockage des
  identifiants qui n'ont aucune de ces propriétés. L'UUID nul est refusé lui aussi.
- **Généré par l'appelant, pas par la base de données.** Le code qui envoie une commande génère l'identifiant et le met
  dans la commande. Le *handler* peut alors retourner `void`, et aucune valeur de retour n'a à franchir la limite de la
  transaction du bus de commandes (voir [command-and-query-buses.md](command-and-query-buses.md)).
- **L'égalité se fait par classe et par valeur.** Deux identifiants d'agrégats différents qui portent par hasard le
  même UUID ne sont pas égaux. Sans cela, un identifiant de compte pourrait réussir un test d'égalité contre un
  identifiant de session.
- **`generate()` retourne `static`.** `OrderId::generate()` est un `OrderId` : le code typé en aval reste typé.
- **C'est la représentation interne, rien de plus.** Tout ce qui sort du système (un jeton, une URL publique, un
  export) a une forme que décide l'application. La classe n'a aucune méthode de présentation et n'en aura pas.

## Invariants

- La valeur est toujours un `Symfony\Component\Uid\UuidV7`.
- `toString()` correspond toujours à `/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/`.
- `X::fromString($id->toString())->equals($id)` est vrai pour tout identifiant `$id` de la classe `X`.
- Un identifiant n'est jamais trié avant un autre généré plus tôt dans le même processus, comme chaîne ou comme octets.

## Stocker les identifiants

Dans une colonne de type chaîne, `toString()` et `fromString()` suffisent. Avec Doctrine DBAL, l'`IdentifierType` du
bundle les stocke sur 16 octets, ou dans la colonne UUID native de la plateforme ; voir
[doctrine-identifier-type.md](doctrine-identifier-type.md).
