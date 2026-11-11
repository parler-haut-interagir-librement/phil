<!-- phil-dev source-sha256=d1f671e990de39098d27057aebf13261c3b532c3799fa3a746bd7425a321290e -->
# Opérations sur les cas

Comptez les cas d'une enum, prenez le premier ou le dernier, filtrez-les par une fonction de rappel ou par une propriété
méta, et triez-les. `AsCollectableEnumTrait` place les opérations sur l'enum ; celles qui conservent plusieurs cas
renvoient une `CaseCollection`, qui les offre toutes de nouveau, afin qu'une sélection puisse être affinée pas à pas.

```php
use Phil\Enums\Attribute\Label;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Traits\AsCollectableEnumTrait;
use Phil\Enums\Traits\AsMetadatableEnumTrait;

#[Meta(Label::class, Visible::class)]
enum Status: string
{
    use AsCollectableEnumTrait;
    use AsMetadatableEnumTrait;

    #[Label('Draft')] #[Visible(true)]
    case DRAFT = 'draft';

    #[Label('Published')] #[Visible(true)]
    case PUBLISHED = 'published';

    #[Label('Archived')] #[Visible(false)]
    case ARCHIVED = 'archived';
}

Status::count();                                     // 3
Status::first();                                     // Status::DRAFT
Status::last(fn (Status $s): bool => $s->visible()); // Status::PUBLISHED
Status::whereMeta(Visible::class, true)->options();  // ['DRAFT' => 'draft', 'PUBLISHED' => 'published']
Status::sortBy(fn (Status $s): string => $s->label(), descending: true)->names();
                                                     // ['PUBLISHED', 'DRAFT', 'ARCHIVED']
```

## Sur l'enum

| Méthode | Renvoie |
|---|---|
| `collect()` | Tous les cas, dans l'ordre de déclaration, sous forme de `CaseCollection`. |
| `count()` | Le nombre de cas. |
| `first(?callable $accept = null)` | Le premier cas, ou le premier que la fonction de rappel accepte ; `null` s'il n'y en a aucun. |
| `last(?callable $accept = null)` | Le dernier cas, ou le dernier que la fonction de rappel accepte ; `null` s'il n'y en a aucun. |
| `filter(callable $accept)` | Les cas que la fonction de rappel accepte. |
| `whereMeta(string $metaProperty, mixed $value)` | Les cas dont la propriété méta a cette valeur. |
| `inGroup(string ...$groups)` | Les cas d'au moins un de ces groupes : les valeurs de leurs métadonnées `#[Group]`, un cas pouvant en porter plusieurs. |
| `sortBy(callable $key, bool\|SortDirection $descending = false)` | Les cas ordonnés selon la clé que la fonction de rappel donne à chacun. |
| `groupBy(callable $key)`, `groupByMeta(string $metaProperty)` | Les cas par clé, sous forme de collections. |
| `withoutDeprecated()` | Les cas sans le `#[\Deprecated]` de PHP ([Ordre, analyse et valeurs par défaut](order-and-parsing.md)). |
| `random(?Randomizer $randomizer = null)` | Un cas tiré au sort, chacun aussi souvent que son `Weight`. |

## Sur une collection

`CaseCollection` a les mêmes méthodes, plus :

| Méthode | Renvoie |
|---|---|
| `all()` | Les cas, sous forme de liste. |
| `isEmpty()` | S'il n'y a aucun cas. |
| `contains(UnitEnum $case)` | Si la collection contient le cas. |
| `reject(callable $refuse)` | Les cas que la fonction de rappel refuse. |
| `only(UnitEnum ...$cases)`, `except(UnitEnum ...$cases)` | Les cas parmi ceux qui sont donnés, ou autres que ceux-ci — dans l'ordre de la collection, et non dans celui des arguments. |
| `sortByName(bool\|SortDirection $descending = false)` | Les cas ordonnés par nom. |
| `sortByValue(bool\|SortDirection $descending = false)` | Les cas ordonnés par valeur, ou par nom pour une enum pure. |
| `reverse()` | Les cas dans l'ordre inverse. |
| `map(callable $map)` | Ce que la fonction de rappel fait de chaque cas, sous forme de liste. |
| `names()`, `values()`, `options()` | Comme les traits du même nom, pour les cas conservés : les `options()` d'une collection filtrée forment la liste de choix d'un formulaire qui n'offre que ces cas. |
| `isBacked()` | Si les cas sont ceux d'une enum adossée ; `false` pour une collection vide. |
| `partition(callable $accept)` | Deux collections : les cas que la fonction de rappel accepte, puis ceux qu'elle refuse. |
| `keyBy(callable $key)` | Les cas indexés par ce que la fonction de rappel donne à chacun ; deux cas d'une même clé lèvent une `LogicException`. |
| `pluck(string $metaProperty)` | La valeur d'une propriété méta pour chaque cas, sa valeur par défaut pour un cas qui ne l'a pas. |
| `deprecated()` | Les cas qui portent le `#[\Deprecated]` de PHP. |

Elle est `Countable` et itérable, et peut être construite à partir de toute liste de cas d'une même enum avec
`CaseCollection::of()`.

## Regroupement

`groupBy()` prend une fonction de rappel qui donne à chaque cas une clé, ou une liste de clés pour placer le cas dans
plusieurs groupes ; `groupByMeta()` lit une propriété méta, et place un cas sous chaque valeur d'une propriété répétable
comme `Group`. Les groupes viennent dans l'ordre où leurs clés apparaissent pour la première fois, chacun sous forme de
`CaseCollection`.

```php
Planet::groupByMeta(Group::class);
// ['inner' => [Mars, Earth, Mercury, Venus], 'home' => [Earth], 'dwarf' => [Pluto]]

Status::groupBy(fn (Status $s): string => $s->visible() ? 'shown' : 'hidden');
```

Un cas sans l'attribut va sous la valeur par défaut de la propriété méta, ou dans aucun groupe lorsque cette valeur par
défaut est `null`. Une valeur qui ne peut pas être une clé de tableau — ni un entier ni une chaîne — lève une
`UnexpectedValueException`. `groupByMeta(Group::class)` est ce dont un formulaire a besoin pour ses `<optgroup>`.

## Tirages au sort

`random()` tire un cas, chacun aussi souvent que son `#[Weight]` : un cas de poids 3 sort trois fois plus souvent qu'un
cas de poids 1, un cas de poids 0 jamais, et un cas sans l'attribut pèse 1.

```php
use Phil\Enums\Attribute\Weight;

enum Variant: string
{
    use AsCollectableEnumTrait;

    #[Weight(80)]
    case Control = 'control';

    #[Weight(20)]
    case Treatment = 'treatment';
}

Variant::random();                                                   // Control four times out of five
Variant::random(new Randomizer(new Xoshiro256StarStar($seed)));      // the same draw for the same seed
```

`random()` renvoie `null` lorsqu'il n'y a aucun cas ou que tous les poids valent 0. Sans argument, elle tire avec le
moteur sécuritaire de PHP ; passez un `Random\Randomizer` sur un moteur avec germe pour un tirage qu'un test peut
répéter. Un poids négatif lève une `InvalidArgumentException` à la lecture de l'attribut. `Weight` n'a pas besoin d'être
énuméré dans `#[Meta]` pour servir au tirage ; énumérez-le pour le lire avec `->weight()`.

## Les règles

- **Immuable.** Chaque opération renvoie une nouvelle collection ; celle sur laquelle elle a été appelée reste
  inchangée.
- **Toujours une liste.** Les cas sont indexés à partir de 0 dans leur ordre courant : l'ordre de déclaration, jusqu'à
  ce qu'un tri ou `reverse()` le change. Un filtre ne laisse jamais de trous dans les clés.
- **Des comparaisons strictes.** `whereMeta()` compare avec `===` : `whereMeta(Mass::class, '60')` ne trouve donc pas
  une masse de `60`. Un cas sans l'attribut a la valeur par défaut de la propriété méta — `null` sauf si la propriété en
  définit une autre — : `whereMeta(Mass::class, null)` trouve donc les cas qui ne l'ont pas.
- **Un tri stable.** `sortBy()` compare les clés avec `<=>`, et deux cas de clé égale gardent leur ordre, en ordre
  croissant comme décroissant.
- **Les groupes sont des sous-ensembles nommés.** `Group` est répétable, et `inGroup()` lit chaque groupe que porte un
  cas : déclarez les sous-ensembles qu'offre une enum — aux clients, au personnel — une seule fois, sur ses cas, plutôt
  que sous forme de listes dans chaque formulaire.
- **Un seul sens partout.** Chaque tri prend `true` ou une `Sort\SortDirection`, l'enum que partagent une chaîne de
  requête, un dépôt et le SQL : `SortDirection::parse('DESC', ignoreCase: true)` la lit avec souplesse — `asc`, `desc`,
  `ascending`, `descending`, `up`, `down` —, `sql()` donne `ASC` ou `DESC`, `compare($a, $b)` trie avec `usort()`,
  `reverse()` l'inverse, `descendingIf($bool)` la construit.
- **Les métadonnées sont lues une seule fois.** `whereMeta()` et un `sortBy()` sur un accesseur lisent les valeurs des
  propriétés méta dans la mémoire du lecteur après la première lecture de chaque cas ([Métadonnées](metadata.md)).
