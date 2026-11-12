<!-- phil-dev source-sha256=adacbc317288742c2c581aac894916c465418aa65e3152ae47629c21b23462fa -->
# Racines, dimensions et chaînes de repli

Un contenu est une **racine** et ses **lignes de dimension**. La racine contient ce qui reste pareil pour chaque
locale, étape et version. Une ligne contient les valeurs d'une locale (ou d'aucune, pour la ligne partagée), d'une
étape et d'une version.

```text
News (root, id 0192f3c4-…)
├── */draft/1        the unlocalized row: values every locale shares
├── fr/draft/1       the French draft, read over */draft/1
├── en/draft/1
├── */live/1         copies made by publishing
└── fr/live/1
```

## La dimension

`Phil\ContentBundle\Domain\Dimension\DimensionKey` identifie une ligne sous sa racine. C'est une classe finale en
lecture seule à trois propriétés publiques, `?Locale $locale`, `Stage $stage` et `Version $version`, construite par
deux constructeurs nommés. `Locale`, `Version`, `FallbackChain` et `DeclaredLocales` sont les objets valeur de
`phil/locale-dimensions`, que le bundle requiert ; `DimensionKey` et `Stage` sont ceux du bundle.

```php
use Phil\ContentBundle\Domain\Dimension\{DimensionKey, Stage};
use Phil\LocaleDimensions\{Locale, Version};

$shared = DimensionKey::unlocalized(Stage::Draft, Version::first());
$french = DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first());

$french->isLocalized();              // true
$french->unlocalizedCounterpart();   // */draft/1: the row it is read over
$french->inStage(Stage::Live);       // fr/live/1: where publishing writes it
$french->atVersion(Version::of(2));  // fr/draft/2: where a fork writes it
$french->equals($shared);            // false
$french->toString();                 // "fr/draft/1"; "*/draft/1" for the shared row
```

### Invariants

- **La ligne non localisée contient les valeurs partagées**, écrites une fois au lieu d'une fois par langue, pour
  qu'elles ne puissent pas diverger : une clé de gabarit, une image d'extrait, une date. Une ligne localisée est
  toujours lue par-dessus la ligne non localisée de la même étape et de la même version. La ligne non localisée est sa
  propre contrepartie.
- **La dimension d'une ligne ne change jamais une fois construite.** Une ligne d'une autre locale, étape ou version
  est une autre ligne ; la classe de base n'a pas de mutateur pour elle.
- **Aucune collection sur la racine.** Chaque ligne pointe vers sa racine, et les lignes sont chargées par
  `DimensionContentRepository`, une dimension à la fois. Une racine qui contiendrait ses lignes chargerait chaque
  version de chaque locale pour servir une seule page, et mettrait une collection mappée dans le domaine.

## La locale

`Locale::fromString(string)` accepte **une seule graphie** : une langue de deux ou trois lettres minuscules, suivie au
besoin d'un trait de soulignement et d'une région de deux lettres majuscules (`fr`, `fr_CA`). Les lignes sont
trouvées par égalité sur cette chaîne : admettre `fr-CA` ou `fr_ca` à côté de `fr_CA` créerait des lignes qu'une
recherche manquerait. Toute autre forme lève `InvalidLocaleDimension` ; à travers le bundle — `ContentManager`, les
contrôleurs, les fonctions Twig, `DimensionLocales::parse()` — le refus parvient à l'appelant comme `InvalidDimension`,
une `DomainError`, avec l'original comme exception précédente.

| Membre | Renvoie |
|---|---|
| `$value`, `$language`, `$region` | `fr_CA`, `fr`, `CA` (`null` sans région) |
| `sharesLanguageWith(Locale)` | si les deux ont la même langue : `fr` et `fr_CA` oui, `fr_CA` et `en_CA` non |
| `toLanguageTag()` | la forme BCP 47, `fr-CA`, pour `hreflang` et `lang` |
| `equals(Locale)`, `toString()`, `__toString()` | comparaison et forme en chaîne |

Les locales qui existent relèvent de la décision de l'application (`phil_content.locales`) ; `Locale` refuse
seulement ce qui ne peut pas en être une.

## L'étape

`Stage` est une énumération typée : `Stage::Draft` (`draft`) est ce que modifient les rédacteurs, `Stage::Live`
(`live`) est ce qui est servi aux lecteurs. La publication copie une ligne de brouillon sur sa jumelle en ligne : les
deux ne partagent donc jamais une ligne, et une modification en cours n'atteint jamais un lecteur
([publication.md](publication.md)).

## La version

`Version` compte à partir de un : `Version::first()`, `Version::of(int)` (zéro et les nombres négatifs lèvent
`InvalidLocaleDimension`, quelle que soit leur source), `next()`, `isAfter(Version)`, `equals(Version)`, et la propriété
publique `$number`.

**Une version publiée n'est jamais modifiée.** La modifier crée la version suivante, et la version publiée reste telle
qu'elle a été approuvée. Les règles du gel et de la création de version sont dans
[publication.md](publication.md#versions).

## Les chaînes de repli

`FallbackChain` est la liste ordonnée des locales essayées pour une locale demandée, celle-ci en premier.

```php
use Phil\LocaleDimensions\{FallbackChain, Locale};

$chain = FallbackChain::of([Locale::fromString('fr_CA'), Locale::fromString('fr')]);

$chain->requested();                                   // fr_CA
$chain->resolve([Locale::fromString('fr')]);           // fr: the first locale of the chain that has content
$chain->resolve([Locale::fromString('en')]);           // null: nothing catches the last step
$chain->leavesRequestedLanguage(Locale::fromString('fr'));   // false: same language
$chain->toString();                                    // "fr_CA → fr"
```

- **La chaîne est déclarée, jamais déduite.** Retirer la région (`fr_CA → fr`) puis se replier sur une valeur par
  défaut semble général, mais devient faux dès que deux variantes régionales d'une langue coexistent. L'application
  écrit la chaîne de chaque locale qu'elle sert sous `phil_content.locales`, et `DeclaredLocales` les construit.
- **Une chaîne épuisée ne mène à rien.** L'appelant refuse (404, `null`, `ContentNotFound`) plutôt que de servir une
  locale que personne n'a déclarée.
- **Un changement de langue est signalé, un changement de région ne l'est pas.** `leavesRequestedLanguage()` indique
  quand la locale servie est dans une autre langue que celle demandée, pour que l'application puisse le compter ou
  l'annoncer ; le contrôleur le passe au gabarit sous le nom `left_language`.
- `FallbackChain::of()` refuse une liste vide et une locale qui apparaît deux fois (`InvalidLocaleDimension`).

Une chaîne résout du contenu ; elle ne choisit pas quelle locale a été demandée. Ce choix — le `_locale` de la
requête, une négociation par `Accept-Language`, une préférence de l'utilisateur — se fait avant, dans l'application.

## Les locales déclarées

`Phil\LocaleDimensions\DeclaredLocales` est le service construit à partir de `phil_content.locales` :

| Méthode | Renvoie |
|---|---|
| `DeclaredLocales::fromArray(array)` | les locales déclarées ; lève `InvalidLocaleDimension` pour une locale mal formée, une locale de repli qui n'est pas servie, ou une chaîne qui répète une locale |
| `all()` | les locales servies, dans l'ordre de leur déclaration |
| `isServed(Locale)` | si la locale est déclarée |
| `chainFor(Locale)` | sa `FallbackChain` ; lève `InvalidLocaleDimension` quand la locale n'est pas servie |

`Phil\ContentBundle\Domain\Dimension\DimensionLocales` est ce qu'appellent le bundle et les bundles bâtis sur lui
avec une locale tapée dans une adresse, un formulaire ou une commande : `parse(string)` et
`chain(DeclaredLocales, Locale)` font le même travail et lèvent plutôt `InvalidDimension`, pour qu'un seul
`catch (DomainError)` couvre une locale refusée comme toute autre valeur refusée.

## Lire une dimension

`ContentAggregator` lit une dimension d'une racine comme une seule ligne, fusionnée à partir de la ligne non
localisée et de la ligne localisée ([pipeline.md](pipeline.md)) :

- `aggregate(ContentRoot, DimensionKey)` : pour une clé localisée, la ligne localisée fusionnée par-dessus sa ligne
  partagée. **Une locale demandée sans sa propre ligne est introuvable** (`ContentNotFound`), même quand la ligne
  partagée existe, puisque servir seulement les valeurs partagées publierait une page dans une langue que personne
  n'a écrite. L'absence de la seule ligne partagée n'est pas une erreur. Pour une clé non localisée, la ligne non
  localisée seule.
- `aggregateAlong(ContentRoot, FallbackChain, Stage, Version)` : la première locale de la chaîne qui a une ligne dans
  cette étape et cette version, fusionnée. Le `dimension()->locale` de la ligne fusionnée dit quelle locale a été
  servie.

Ce qui est servi à un lecteur, c'est `LiveContentReader::read(ContentRoot, FallbackChain)`, qui parcourt la chaîne
sur l'étape en ligne, chaque locale dans la version à partir de laquelle elle a été publiée en dernier
([publication.md](publication.md)).
