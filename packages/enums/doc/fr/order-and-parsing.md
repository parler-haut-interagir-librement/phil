<!-- phil-dev source-sha256=88f8c8e2dfd2fdf0ddbf5f54dc5e696031bb6173c1a36b505904c465bbbffa07 -->
# Ordre, analyse, valeurs par défaut et transitions

Quatre traits pour ce dont une enum a souvent besoin autour de ses cas : leur ordre, une lecture souple des entrées
venues de l'extérieur, un cas par défaut, et les passages de l'un à l'autre. `AsEnumerableEnumTrait` inclut les quatre.
Un cinquième sujet n'exige aucun trait : les cas obsolètes.

## Ordre : AsOrdinalEnumTrait

L'ordinal d'un cas est sa position dans le fichier source, à partir de 0. Les statuts qui se suivent, les niveaux et les
priorités se comparent par lui.

```php
enum Stage: string
{
    use AsOrdinalEnumTrait;

    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
}

Stage::Review->ordinal();                         // 1
Stage::Review->next();                            // Stage::Published
Stage::Published->next();                         // null
Stage::Published->next(cycle: true);              // Stage::Draft
Stage::Draft->isBefore(Stage::Published);         // true
Stage::Review->isBetween(Stage::Draft, Stage::Review); // true, both ends included
```

| Méthode | Renvoie |
|---|---|
| `ordinal()` | La position du cas, à partir de 0. |
| `next(bool $cycle = false)`, `previous(bool $cycle = false)` | Le cas suivant ou précédent ; `null` au-delà d'une extrémité, ou le cas de l'autre extrémité lorsque `$cycle` est vrai. |
| `compareTo($other)` | Moins que, égal à ou plus que 0, comme `<=>` : utilisable comme fonction de rappel de `usort()`. |
| `isBefore($other)`, `isAfter($other)` | Des comparaisons strictes selon la position. |
| `isBetween($from, $to)` | Si le cas se trouve entre les deux, bornes incluses. |

**L'ordre est celui du fichier source.** Réordonner les cas change chaque ordinal : stockez la valeur d'un cas, jamais
son ordinal.

## Analyse : AsParsableEnumTrait et Alias

`from()` lit une valeur exacte. `parse()` lit ce qu'envoient les entrées venues de l'extérieur — une chaîne de requête,
une importation, une ancienne API — : une valeur, un nom ou l'une des graphies `#[Alias]` du cas, et sans tenir compte
de la casse sur demande.

```php
use Phil\Enums\Attribute\Alias;

enum Priority: string
{
    use AsParsableEnumTrait;

    #[Alias('minor')]
    case Low = 'low';

    #[Alias('major')] #[Alias('3')]
    case High = 'high';
}

Priority::parse('low');                     // Priority::Low, by value
Priority::parse('High');                    // Priority::High, by name
Priority::parse('major');                   // Priority::High, by alias
Priority::parse(3);                         // Priority::High, the alias '3'
Priority::tryParse('MAJOR');                // null
Priority::tryParse('MAJOR', ignoreCase: true); // Priority::High
Priority::parse('extreme');                 // ValueError
```

- **Les valeurs d'abord, puis les noms, puis les alias.** Chaque sorte est essayée sur tous les cas avant la suivante :
  une valeur n'est donc jamais prise pour l'alias que porte un autre cas.
- **Un alias est une chaîne.** `Alias` est répétable ; écrivez un alias qui ressemble à un nombre sous forme de chaîne,
  `#[Alias('3')]`. L'entrée est comparée sous forme de chaîne : `parse(3)` et `parse('3')` concordent donc.
- **La casse est celle de l'ASCII.** `ignoreCase` compare avec `strcasecmp()`.
- **N'importe quelle enum, sans le trait.** `Reflection::parse(Priority::class, 'major')` lit de la même façon, pour du
  code qui reçoit une classe d'enum — un résolveur d'arguments de contrôleur, un importateur.

## Cas par défaut : AsDefaultableEnumTrait et DefaultCase

```php
use Phil\Enums\Attribute\DefaultCase;

enum Visibility: string
{
    use AsDefaultableEnumTrait;

    case Public = 'public';

    #[DefaultCase]
    case Private = 'private';
}

Visibility::default();    // Visibility::Private
Visibility::tryDefault(); // Visibility::Private, or null for an enum that marks none
```

La valeur par défaut est déclarée une seule fois sur l'enum plutôt que dans chaque entité et chaque formulaire.
`default()` lève une `LogicException` lorsque l'enum ne marque aucun cas ; les deux méthodes en lèvent une lorsqu'elle
en marque plus d'un. Le cas marqué est lu une fois par processus.

## Transitions : AsStatefulEnumTrait et TransitionsTo

Un cycle de vie déclaré sur les cas : `#[TransitionsTo]` nomme les cas vers lesquels un cas peut passer.

```php
use Phil\Enums\Attribute\TransitionsTo;

enum OrderStatus: string
{
    use AsStatefulEnumTrait;

    #[TransitionsTo(self::Paid, self::Cancelled)]
    case Pending = 'pending';

    #[TransitionsTo(self::Shipped)]
    case Paid = 'paid';

    case Shipped = 'shipped';

    case Cancelled = 'cancelled';
}

OrderStatus::Pending->canTransitionTo(OrderStatus::Paid);   // true
OrderStatus::Pending->nextStates()->names();                // ['Paid', 'Cancelled']
OrderStatus::Shipped->isFinal();                            // true
OrderStatus::initialStates()->all();                        // [OrderStatus::Pending]
OrderStatus::transitions();                                 // [[Pending, Paid], [Pending, Cancelled], [Paid, Shipped]]
```

| Méthode | Renvoie |
|---|---|
| `canTransitionTo($to)` | Si le cas peut passer à l'autre. |
| `nextStates()` | Les cas vers lesquels il peut passer, dans l'ordre de déclaration, sous forme de `CaseCollection`. |
| `isFinal()` | S'il ne passe nulle part : un cas sans l'attribut. |
| `initialStates()` | Les cas vers lesquels aucun cas ne passe, là où commence un cycle de vie. |
| `transitions()` | Chaque passage, sous forme de paires de cas. |

Une transition vers un cas d'une autre enum lève une `LogicException` à la première lecture du cas. Le trait répond à ce
que demande un gabarit ou un validateur — cette commande peut-elle être annulée ? — ; pour les gardes, les événements,
l'historique ou une piste d'audit, Symfony Workflow lit la même déclaration par `phil/enums-bundle`, dont la contrainte
`#[Transition]` refuse un changement d'état que la déclaration ne permet pas.

## Cas obsolètes

L'attribut `#[\Deprecated]` de PHP 8.4 s'applique aux cas d'enum : écrire `Status::LEGACY` déclenche alors un avis
d'obsolescence. La bibliothèque le lit, afin qu'un formulaire cesse d'offrir un cas pendant que les enregistrements qui
le contiennent continuent de le lire.

```php
enum Status: string
{
    use AsCollectableEnumTrait;
    use AsMetadatableEnumTrait;

    case Active = 'active';

    #[\Deprecated('Use Active')]
    case Legacy = 'legacy';
}

Status::withoutDeprecated()->all();         // [Status::Active]
Status::collect()->deprecated()->all();     // [Status::Legacy]
Status::from('legacy')->isDeprecated();     // true
```

`isDeprecated()` vient avec `AsMetadatableEnumTrait` ; `withoutDeprecated()` avec `AsCollectableEnumTrait` et
`CaseCollection`. Lire un cas obsolète par `from()`, `cases()` ou une collection ne déclenche aucun avis ; seule
l'écriture de sa constante le fait.
