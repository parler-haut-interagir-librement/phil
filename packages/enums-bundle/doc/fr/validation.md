<!-- phil-dev source-sha256=87892585d1e4db4eab5939ac517104076dd1c4ea1cfaaac7a0eb3315e16a55e6 -->
# Validation

`EnumCase` n'accepte une valeur que si elle est un cas d'une [sélection](selection.md) :

```php
use Phil\EnumsBundle\Infrastructure\Validator\EnumCase;

final class OrderForm
{
    #[EnumCase(OrderStatus::class, inGroup: 'customer', except: [OrderStatus::CANCELLED])]
    public ?OrderStatus $status = null;
}
```

| Argument | Type | Valeur par défaut | Signification |
|---|---|---|---|
| `enum` | class-string | requis | L'enum. |
| `only`, `except` | liste de cas | `[]` | Comme dans une sélection. |
| `inGroup` | chaîne ou liste de chaînes | `[]` | Les groupes ; nommé ainsi parce que `groups` désigne les groupes de validation de la contrainte. |
| `whereMeta` | table de classe de méta-propriété vers valeur | `[]` | Comme dans une sélection. |
| `message` | chaîne | `The value {{ value }} is not an allowed choice.` | Le message de violation. |
| `groups`, `payload` | | | Comme pour toute contrainte. |

## Ce qui est validé

- `null` est valide, comme pour toute contrainte sauf `NotNull`.
- Un cas de l'enum est vérifié par rapport à la sélection.
- Une valeur de cas (`'in_transit'`) est d'abord lue sous forme de cas, et il en va de même pour un nom de cas d'une
  enum pure. Pour une enum adossée à des entiers, une chaîne qui écrit un entier comme PHP l'écrit (`'3'` ou `'-3'`,
  comme les apporte une chaîne de requête) est lue comme cet entier.
- Tout le reste (le cas d'une autre enum, un tableau, un entier pour une enum pure ou pour une enum adossée à des
  chaînes, `'03'`, `'3.0'` ou `' 3'` pour une enum adossée à des entiers) n'est pas un cas.

| Code | Quand |
|---|---|
| `EnumCase::NOT_A_CASE_ERROR` | La valeur ne représente aucun cas de l'enum ; `{{ value }}` est la valeur. |
| `EnumCase::NOT_ALLOWED_ERROR` | La valeur est un cas hors de la sélection ; `{{ value }}` est le nom du cas. |

## Changements d'état : Transition

`Transition` refuse un changement d'état que l'enum ne déclare pas avec `#[TransitionsTo]`
([phil/enums](../../../enums/doc/fr/order-and-parsing.md)).

```php
use Phil\EnumsBundle\Infrastructure\Validator\Transition;

#[ORM\Column(enumType: Delivery::class)]
#[Transition]
public Delivery $status = Delivery::Ordered;

// A DTO that keeps the case it was loaded with:
#[Transition(from: 'loadedStatus')]
public ?Delivery $status = null;
```

Le cas précédent est lu dans la propriété que nomme `from` ou, sinon, dans ce que Doctrine a chargé pour une entité
gérée : la valeur stockée, relue sous forme de cas. Rester dans le même cas est valide. Un objet sans cas précédent (une
nouvelle entité, une colonne `null`) doit commencer dans un cas initial, un cas vers lequel aucun cas ne mène, sauf si
`fromInitial` vaut `false`.

| Option | Signification |
|---|---|
| `from` | La propriété de l'objet qui contient le cas précédent ; `null` interroge Doctrine. |
| `fromInitial` | Si un objet sans cas précédent doit commencer dans un cas initial ; `true` par défaut. |
| `message`, `initialMessage` | Les deux messages, avec `{{ from }}` et `{{ to }}`. |

| Code | Levé lorsque |
|---|---|
| `Transition::NOT_ALLOWED_ERROR` | Le cas précédent ne mène pas au nouveau. |
| `Transition::NOT_INITIAL_ERROR` | Il n'y a aucun cas précédent, et le nouveau n'est pas initial. |

Une valeur qui n'est pas un cas lance `UnexpectedValueException` ; `null` est valide. Sans DoctrineBundle, seul `from`
donne un cas précédent.

## Ensembles : EnumSetOf

`EnumSetOf` est la contrainte d'un `EnumSet` : chaque cas de l'ensemble fait partie d'une sélection, et le nombre de cas
se situe entre des bornes.

```php
use Phil\EnumsBundle\Infrastructure\Validator\EnumSetOf;

#[EnumSetOf(Permission::class, inGroup: 'basic', min: 1, max: 2)]
public EnumSet $permissions;
```

| Option | Signification |
|---|---|
| `enum`, `only`, `except`, `inGroup`, `whereMeta` | La sélection, comme pour `EnumCase`. |
| `min`, `max` | Les bornes du nombre de cas, toutes deux incluses ; `null` pour aucune. Une borne négative, ou un minimum supérieur au maximum, est refusé à la construction de la contrainte. |
| `message`, `notAllowedMessage`, `minMessage`, `maxMessage` | Les quatre messages ; les deux derniers sont au pluriel, avec `{{ count }}` et `{{ limit }}`. |

| Code | Levé |
|---|---|
| `EnumSetOf::NOT_A_SET_ERROR` | Une fois, pour une valeur qui n'est pas un `EnumSet` de l'enum ; rien d'autre n'est vérifié. |
| `EnumSetOf::NOT_ALLOWED_ERROR` | Une fois par cas hors de la sélection. |
| `EnumSetOf::TOO_FEW_ERROR`, `EnumSetOf::TOO_MANY_ERROR` | Lorsque le nombre est hors des bornes. |

`null` est valide, comme pour toute contrainte sauf `NotNull`.
