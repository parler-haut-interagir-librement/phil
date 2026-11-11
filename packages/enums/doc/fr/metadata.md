<!-- phil-dev source-sha256=3e6dd7c207ebdc7b150ae8f60d8c77188384b9c6b6a80537d436f86320184e0a -->
# Métadonnées

`AsMetadatableEnumTrait` associe des valeurs aux cas au moyen d'attributs — un libellé, une couleur, un poids — et les
relit par des accesseurs nommés d'après eux.

## Déclarer des métadonnées sur une enum

Une **propriété méta** est une classe d'attribut qui étend `Meta\AbstractMetaProperty`. L'enum énumère celles qu'elle
active dans `#[Meta(...)]`, et chaque cas porte celles pour lesquelles il a des valeurs :

```php
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

#[Meta(Label::class, Badge::class)]
enum Ticket: string
{
    use AsMetadatableEnumTrait;

    #[Label('Open')] #[Badge('amber')]
    case OPEN = 'open';

    #[Label('Resolved')] #[Badge('teal')]
    case RESOLVED = 'resolved';

    #[Label('Spam')]
    case SPAM = 'spam';
}
```

`Label` est fourni avec la bibliothèque ; `Badge` est propre à l'application :

```php
use Attribute;
use Phil\Enums\Meta\AbstractMetaProperty;

/** @extends AbstractMetaProperty<string> */
#[Attribute]
final class Badge extends AbstractMetaProperty
{
}
```

## Les lire

Chaque propriété méta activée donne aux cas un accesseur : le nom court de la classe, avec une première lettre
minuscule.

```php
Ticket::OPEN->label();   // 'Open'
Ticket::RESOLVED->badge(); // 'teal'
Ticket::SPAM->badge();   // null: the case carries no Badge, and Badge's default value is null
```

**Une méthode qu'aucune propriété méta activée ne nomme échoue** avec `BadMethodCallException`, formulée comme l'erreur
propre à PHP — `Call to undefined method App\Ticket::lable()` — : un accesseur mal orthographié, ou dont la propriété
méta a été retirée de `#[Meta]`, ne passe donc jamais pour une valeur manquante. L'extension PHPStan le signale comme
une méthode inconnue avant l'exécution ([PHPStan](phpstan.md)).

## Écrire une propriété méta

Une sous-classe d'`AbstractMetaProperty` peut remplacer trois choses.

| Remplacement | Effet | Exemple |
|---|---|---|
| `transform(mixed $value): mixed` | Réécrit la valeur à la construction de l'attribut. | `'amber'` stocké sous la forme `'badge-amber'` |
| `defaultValue(): mixed` (statique) | Ce que répond un cas sans l'attribut ; `null` sauf remplacement. Restreindre son type de retour (`string`) restreint celui de l'accesseur pour PHPStan. | `'grey'` |
| `customMethodName(): ?string` (statique) | Le nom de l'accesseur, lorsque le nom de la classe ne doit pas le donner. | `'colour'` donne `->colour()` |

```php
#[Attribute]
final class Badge extends AbstractMetaProperty
{
    public static function defaultValue(): string
    {
        return 'badge-grey';
    }

    protected function transform(mixed $value): mixed
    {
        return 'badge-'.$value;
    }

    protected static function customMethodName(): ?string
    {
        return 'colour';
    }
}

Ticket::OPEN->colour(); // 'badge-amber'
Ticket::SPAM->colour(); // 'badge-grey'
```

`method()` donne le nom de l'accesseur, tel que le calculent le trait et l'extension PHPStan.

## Trouver un cas par ses métadonnées

`fromMeta()` et `tryFromMeta()` prennent une instance de la propriété méta — construite avec la méthode statique
`make()`, qui transforme la valeur comme le ferait l'attribut — et donnent le premier cas qui porte cette valeur :

```php
Ticket::fromMeta(Label::make('Resolved'));  // Ticket::RESOLVED
Ticket::tryFromMeta(Label::make('Closed')); // null
Ticket::fromMeta(Label::make('Closed'));    // ValueError
```

### Propriétés méta déclarées par un trait

Un trait qu'utilise l'enum peut porter son propre `#[Meta(...)]`. Ses propriétés méta sont lues après celles de l'enum,
à l'exécution comme par l'extension PHPStan : un trait peut donc apporter un attribut et son accesseur à chaque enum qui
l'utilise.

```php
#[Meta(Shade::class)]
trait HasShade
{
}

#[Meta(Label::class)]
enum Theme
{
    use AsMetadatableEnumTrait;
    use HasShade;

    #[Label('Dark')] #[Shade('black')]
    case DARK;
}

Theme::DARK->label(); // 'Dark'
Theme::DARK->tint();  // 'black', when Shade names its accessor `tint` through customMethodName()
```

### Lues une seule fois

Les propriétés méta d'une enum, et les valeurs que porte chaque cas, sont lues au premier accès et conservées pour le
reste du processus : les attributs ne peuvent pas changer pendant l'exécution du code. Un accesseur, `fromMeta()`,
`tryFromMeta()` ou `whereMeta()` appelé de nouveau ne fait aucune réflexion et n'instancie aucun attribut. Seuls les
attributs de propriétés méta sont instanciés, jamais un autre attribut que porte un cas. Pour une propriété méta répétée
comme `Group`, l'accesseur donne la première valeur ; `Reflection::metaValueList()` les donne toutes, dans l'ordre de
déclaration, et une liste vide pour un cas qui ne l'a pas.

### Messages d'erreur

`fromMeta()` nomme l'enum, la propriété méta et la valeur cherchée : une valeur scalaire ou `Stringable` est écrite sous
forme de chaîne, toute autre valeur sous forme de son type — `Enum App\Status does not have a case with a meta property
"App\Weight" of value "array"`.

### Propriétés méta incluses

Le paquet est fourni avec cinq propriétés méta prêtes à l'emploi :

| Propriété méta | Valeur | Répétable | Utilisée par |
|---|---|---|---|
| `Description` | `string` | Non | Vos propres accesseurs ; une intégration peut la traduire. |
| `Group` | `string` | Oui | `inGroup()` et `groupByMeta()` ([Opérations](operations.md)). |
| `Label` | `string` | Non | Vos propres accesseurs ; une intégration peut la traduire. |
| `Alias` | `string` | Oui | `parse()` ([Ordre, analyse et valeurs par défaut](order-and-parsing.md)). |
| `Weight` | `int`, 0 ou plus, 1 par défaut | Non | `random()` ([Opérations](operations.md)). |

Trois attributs ne sont pas des propriétés méta — ils ne portent aucune valeur par cas et n'ont pas d'accesseur :
`#[DefaultCase]` et `#[TransitionsTo]` sur les cas ([Ordre, analyse, valeurs par défaut et
transitions](order-and-parsing.md)), et `#[BitFlags]` sur une enum ([Ensembles et tables
associatives](sets-and-maps.md)).

### Métadonnées requises

`#[RequiredMeta]` nomme les propriétés méta que chaque cas de l'enum doit porter, comme un `Label` pour chaque cas
qu'affiche un formulaire. Il est répétable.

```php
use Phil\Enums\Attribute\RequiredMeta;

#[Meta(Label::class, Description::class)]
#[RequiredMeta(Label::class)]
enum Status: string
{
    use AsMetadatableEnumTrait;

    #[Label('Draft')]
    case DRAFT = 'draft';

    case PUBLISHED = 'published'; // reported: no #[Label]
}
```

Deux vérifications le lisent. **L'extension PHPStan** signale chaque cas auquel il en manque une, avec l'identifiant
`philEnums.requiredMeta` ([PHPStan](phpstan.md)). **`Reflection::missingRequiredMeta()`** les énumère à l'exécution, par
nom de cas, pour un test dans un projet sans PHPStan ([Tests](testing.md)) :

```php
Reflection::missingRequiredMeta(Status::class); // ['PUBLISHED' => [Label::class]]
```

Une propriété requise que porte chaque cas type aussi son accesseur sans `null`, puisqu'aucun cas ne se rabat sur la
valeur par défaut.

### Typer les accesseurs sans l'extension PHPStan

Les accesseurs sont servis par `__call()`, qu'un éditeur ne peut pas voir. Avec PHPStan, l'extension les type. Pour
aider aussi un éditeur, déclarez-les avec `@method` sur l'enum, ou sur un trait que plusieurs enums utilisent avec la
même propriété méta :

```php
/**
 * @method string      label()
 * @method string|null badge()
 */
#[Meta(Label::class, Badge::class)]
enum Ticket: string
{
    use AsMetadatableEnumTrait;
    // …
}
```
