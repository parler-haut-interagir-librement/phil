<!-- phil-dev source-sha256=79b5850feaf335b96a72a0bb287dc1e7639ddba1c572f6f1b9634556834509f8 -->
# Position

| Type | Genre | Rôle |
|---|---|---|
| `Contract\Positionable` | interface | `position()`, `placeAt()`, `positionGroup()` |
| `PositionableEntity` | trait | La colonne `position`, un entier, nullable ; un seul ordre pour la table |
| `PositionListener` | écouteur : `onFlush` | Envoie un enregistrement sans position à la fin de son groupe |
| `Positions` | service | `moveBefore()`, `moveAfter()`, `moveFirst()`, `moveLast()` |
| `Exception\InvalidScope` | exception | Un groupe que l'écouteur ne sait pas comparer, ou un déplacement d'un groupe à l'autre |

```php
#[ORM\Entity]
#[ORM\Index(columns: ['menu_id', 'position'])]
class MenuItem implements Positionable
{
    use PositionableEntity;

    #[ORM\ManyToOne(targetEntity: Menu::class)]
    private ?Menu $menu = null;

    public function positionGroup(): array { return ['menu']; }
}
```

```php
$positions = new Positions($manager);
$positions->moveBefore($item, $other);
$positions->moveLast($item);

$repository->findBy(['menu' => $menu], ['position' => 'ASC']);
```

Les positions ne font qu'ordonner. Un trou entre deux positions ne signifie rien, et un enregistrement retiré en laisse
un : l'ordre des autres reste le même.

## Le groupe

`positionGroup()` nomme les champs dont les valeurs forment un groupe ; chaque groupe est ordonné à part. Un champ est
une colonne de l'enregistrement, colonne d'énumération comprise, ou une relation vers un seul côté qu'il tient par une
colonne, comme un parent ou un locataire. Une valeur nulle est un groupe à elle seule, comparée par `IS NULL`. Le trait
donne `[]` : un seul ordre pour toute la table.

Une relation vers plusieurs, une relation tenue par plusieurs colonnes, ou une relation vers un enregistrement dont
l'identifiant est lui-même une relation lève `InvalidScope` au vidage qui en a besoin.

## Quand une position est donnée

| Vidage | Position |
|---|---|
| un enregistrement inséré sans position | la fin de son groupe |
| un enregistrement inséré avec une position | gardée telle quelle |
| un enregistrement modifié après `placeAt(null)` | la fin de son groupe |
| un enregistrement passé dans un autre groupe, sa position intacte | la fin de son nouveau groupe |
| un enregistrement passé dans un autre groupe avec une nouvelle position | gardée telle quelle |
| tout autre changement | laissée en paix |

La fin d'un groupe est une seule requête, `MAX(position)`, sur la table elle-même, pour que les lignes que cachent les
filtres SQL comptent quand même. Les enregistrements d'un même vidage dans un même groupe se suivent ; un groupe dont la
relation pointe vers un enregistrement du même vidage, pas encore enregistré, commence à 1. Une sous-classe compte la
table qui porte la colonne.

## Déplacer

`Positions` déplace un enregistrement enregistré dans son groupe, en une transaction sur la table elle-même :

| Méthode | Effet |
|---|---|
| `moveBefore($record, $other)` | `$record` prend la position de `$other` ; `$other` et les enregistrements qui le suivent reculent d'une place |
| `moveAfter($record, $other)` | `$record` prend la place qui suit `$other` ; les enregistrements qui suivent reculent d'une place |
| `moveFirst($record)` | `$record` prend la première position du groupe ; les autres reculent d'une place |
| `moveLast($record)` | `$record` prend la place qui suit le dernier enregistrement du groupe |

Chaque déplacement verrouille avant de lire, par une écriture qui ne change rien, ce qui tient sur chaque plateforme :
`moveBefore()` et `moveAfter()` verrouillent la ligne de `$other`, `moveFirst()` et `moveLast()` chaque ligne du groupe.
Deux déplacements vers la même extrémité d'un groupe s'attendent donc l'un l'autre, et le second lit la position que le
premier a validée, au lieu que les deux prennent la même place ; des déplacements dans des groupes différents ne
s'attendent pas. Un déplacement vers une extrémité écrit une fois chaque ligne de son groupe, ce que l'index ci-dessous
limite au groupe.

Les écouteurs ne s'exécutent pas, et les lignes supprimées en douceur, désactivées ou filtrées se déplacent avec les
autres. Un enregistrement placé contre lui-même, ou seul dans son groupe, reste où il est. Un enregistrement placé
contre un enregistrement d'un autre groupe ou d'une autre table, un enregistrement pas encore enregistré, et un
enregistrement dont le groupe pointe vers un enregistrement pas encore enregistré lèvent `InvalidScope` : videz d'abord.

Les enregistrements chargés du groupe suivent le déplacement en mémoire, si bien que le vidage suivant n'a rien à
écrire. Un enregistrement chargé dont la position a été changée sans être vidée garde ce changement, que le vidage
suivant écrit. Videz un changement de groupe avant de déplacer l'enregistrement : le déplacement lit le groupe dans
l'enregistrement en mémoire.

## Index

L'application déclare un index simple sur les colonnes du groupe et `position`, pour le tri et pour `MAX(position)`. Un
index unique ne convient pas : un déplacement décale une plage de positions en une seule instruction, ce qu'un index
unique peut refuser à mi-chemin sur certaines plateformes.
