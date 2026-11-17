<!-- phil-dev source-sha256=2de5f526c9a2c43797313847be9effcfa1aed06996abd7e0ac9b4c8c54b196d5 -->
# Lire cmi5

Un cours cmi5 est décrit par une structure de cours, `cmi5.xml` : le cours, puis des blocs et des unités assignables dans
l'ordre, chacun avec une IRI comme identifiant. Une unité assignable nomme son URL de lancement — un fichier du paquet,
ou une adresse absolue — et la façon dont elle est satisfaite.

## À partir d'un paquet

```php
use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\PackageReader;

$package = new PackageReader(language: 'fr')->readFile('/path/to/course.zip');

if ($package->manifest instanceof Cmi5Course) {
    $package->manifest->children; // blocks and assignable units, in order
}
```

Un paquet qui contient `cmi5.xml` à sa racine est lu comme du cmi5, même s'il contient aussi `imsmanifest.xml`. Chaque
unité dont l'URL est relative est résolue, vérifiée pour rester dans le paquet, et notée quand son fichier est absent :
`The launch file "…" of the assignable unit "…" is not in the package.` Une unité dont l'URL sort du paquet le fait
refuser.

## Une structure de cours seule

Une plateforme peut recevoir `cmi5.xml` seul, ses unités servies d'ailleurs :

```php
$package = new PackageReader()->readStructure($xml);
```

`$package->files` est vide, et une unité qui nomme un fichier plutôt qu'une adresse absolue est notée, puisqu'aucun paquet
ne le contient.

## À partir du texte de la structure

```php
use Phil\ElearningPackages\Cmi5\Cmi5CourseReader;

$course = new Cmi5CourseReader(language: 'fr-CA')->read($xml);
```

## Ce qui est lu

| Valeur | Source |
|---|---|
| `Cmi5Course::$id`, `Cmi5Block::$id`, `Cmi5Au::$id` | L'attribut `id` : de 1 à 255 octets sans espace blanche, unique dans la structure |
| `$title`, `$description` | Le `<langstring>` de `<title>` et de `<description>` dans la langue demandée, sinon le premier ; le titre vaut par défaut l'identifiant |
| `Cmi5Course::$children`, `Cmi5Block::$children` | Les éléments `<block>` et `<au>`, dans l'ordre |
| `Cmi5Course::$hasObjectives` | Si la structure contient `<objectives>`, que la bibliothèque ne lit pas plus loin |
| `Cmi5Au::$url` | `<url>`, de 1 à 2 048 octets |
| `Cmi5Au::$moveOn` | `moveOn`, un `MoveOn` ; `NotApplicable` par défaut |
| `Cmi5Au::$masteryScore` | `masteryScore`, un décimal de 0 à 1 |
| `Cmi5Au::$launchMethod` | `launchMethod`, un `LaunchMethod` ; `AnyWindow` par défaut |
| `Cmi5Au::$launchParameters`, `$entitlementKey` | `<launchParameters>`, `<entitlementKey>` |
| `Cmi5Au::$activityType` | `activityType` |

La langue correspond par préfixe et sans tenir compte de la casse : `fr` choisit `fr-CA`. Une structure sans aucune unité
assignable, un `moveOn` ou un `launchMethod` que cmi5 ne définit pas, ou un score de maîtrise hors de 0 et de 1 est
refusé.

## `MoveOn`

Ce qui satisfait une unité, comme cmi5 le définit :

```php
use Phil\ElearningPackages\Cmi5\MoveOn;

MoveOn::CompletedAndPassed->isMetBy(completed: true, success: true);  // true
MoveOn::CompletedOrPassed->isMetBy(completed: false, success: null);  // false
MoveOn::NotApplicable->isMetBy(completed: false, success: null);      // true
```

## Ce que la bibliothèque laisse à son appelant

Elle ne lance pas les unités, ne récupère pas les jetons, et ne reçoit ni ne vérifie les énoncés xAPI : cela revient à une
plateforme, qui les construit sur ce que la bibliothèque lit.
