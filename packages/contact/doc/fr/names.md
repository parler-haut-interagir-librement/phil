<!-- phil-dev source-sha256=30db4d1118d23e8dca3f1869b2efe9ddc8f36cb57a1ac6543450de486d26d74c -->
# Noms de personnes

Une `ContactCard` garde le nom d'une personne en un seul texte, `name`, et peut aussi le garder en ses parties,
`structuredName`, un `PersonName`. Les parties permettent d'écrire et de trier le nom comme le fait sa culture :
`Charles de Gaulle` se trie sous *Gaulle* en français, `Herman Van Rompuy` sous *Van Rompuy* en néerlandais de
Belgique, et `Bartók Béla` s'écrit avec le nom de famille en premier en hongrois.

```php
$name = PersonName::of(given: 'Charles', family: 'Gaulle', particle: 'de', culture: 'fr');

$name->display();   // "Charles de Gaulle"
$name->sortKey();   // "Gaulle, Charles de"

$card = ContactCard::of(structuredName: $name);
$card->name;        // "Charles de Gaulle"
```

## Les parties

| Partie | Exemple | Remarque |
|---|---|---|
| `given` | `Ada` | Le prénom |
| `additional` | `['Augusta']` | Les prénoms du milieu, au plus `PersonName::MAX_ADDITIONAL` (10) |
| `particle` | `de`, `van der`, `d'` | Accompagne un nom de famille ; une particule élidée, qui se termine par `'` ou `’`, s'écrit collée au nom |
| `family` | `Gaulle` | Le nom de famille, sans sa particule |
| `secondFamily` | `Márquez` | Un second nom de famille, comme en espagnol |
| `honorific` | `Dr.` | Écrit par `formal()` |
| `generation` | `Jr.`, `III` | Écrit après le nom |
| `credential` | `PhD` | Écrit par `formal()`, après une virgule |
| `culture` | `fr-CA` | Une étiquette de langue BCP 47 ; `_` est lu comme `-` |
| `order` | `NameOrder::FamilyFirst` | L'emporte sur l'ordre de la culture ; aucun par défaut |

`PersonName::of()` rogne chaque texte ; un texte vide n'en est pas un. Un nom a un prénom ou un nom de famille, chaque
partie compte 255 caractères ou moins et ne contient aucun caractère de contrôle, et une étiquette de langue a une
langue de deux ou trois lettres suivie de parties d'une à huit lettres ou chiffres, 35 caractères en tout.
`InvalidContact` est levée sinon. Sur une fiche, la forme d'affichage du nom est le `name` de la fiche quand on ne lui
en donne pas, et elle compte aussi 255 caractères ou moins.

## Écrire et trier

| Méthode | Donne | `Charles de Gaulle` en français |
|---|---|---|
| `display()` | Le nom tel qu'on l'écrit tous les jours, génération comprise | `Charles de Gaulle` |
| `formal()` | Avec le titre honorifique et le titre de compétence | `Gén. Charles de Gaulle, PhD` |
| `familyName()` | Le nom de famille tel qu'écrit, particule et second nom de famille compris | `de Gaulle` |
| `surname()` | Le nom de famille avec sa particule, sans le second nom de famille | `de Gaulle` |
| `sortFamily()` | Le nom de famille sous lequel une liste se trie | `Gaulle` |
| `sortKey()` | Le texte sous lequel une liste se trie | `Gaulle, Charles de` |
| `separator()` | Ce qui sépare les parties | une espace |
| `displayOrder()` | L'ordre dans lequel le nom s'écrit | `NameOrder::GivenFirst` |
| `equals()` | Si deux noms ont les mêmes parties | |

Prénom d'abord, `display()` écrit le prénom, les prénoms additionnels et le nom de famille ; nom de famille d'abord, il
écrit le nom de famille, les prénoms additionnels et le prénom, comme le vietnamien écrit `Võ Nguyên Giáp`. Les noms
chinois, japonais et coréens écrits dans leurs propres caractères sont joints sans espace, `毛泽东`, et avec une espace
quand ils sont romanisés, `Mao Zedong`.

`sortKey()` met d'abord le nom de famille trié, puis le prénom et les prénoms additionnels, puis ce que le tri laisse
de côté de la particule : `Gogh, Vincent van`. Ce que la clé garde d'une particule commence par une majuscule, comme
l'imprime une liste triée : `Du Bellay, Joachim`. Une culture qui trie par le prénom le met en premier : `Giáp, Võ
Nguyên`. Triez les clés avec un collationneur de la langue du lecteur, `Collator::compare()` de `ext-intl` : la clé
ordonne les parties, le collationneur ordonne les lettres.

Chaque méthode prend en argument facultatif une `NameConvention`, qui l'emporte sur la culture du nom.

## Les conventions de la bibliothèque

`NameConventions::for($culture)` donne la convention d'une culture, selon sa langue et, pour le néerlandais, son pays :

| Culture | Ordre | Clé de tri | Exemple |
|---|---|---|---|
| Chinois, japonais, coréen | Nom de famille d'abord, caractères propres joints | Nom de famille | `毛泽东` → `毛, 泽东` |
| Hongrois | Nom de famille d'abord | Nom de famille | `Bartók Béla` → `Bartók, Béla` |
| Vietnamien | Nom de famille d'abord | Prénom | `Võ Nguyên Giáp` → `Giáp, Võ Nguyên` |
| Islandais | Prénom d'abord | Prénom | `Björk Guðmundsdóttir` → `Björk, Guðmundsdóttir` |
| Néerlandais des Pays-Bas | Prénom d'abord | Sans la particule | `Vincent van Gogh` → `Gogh, Vincent van` |
| Allemand, portugais | Prénom d'abord | Sans la particule | `Ursula von der Leyen` → `Leyen, Ursula von der` |
| Français | Prénom d'abord | Sans un `de` ou un `d'` initial | `Jean de La Fontaine` → `La Fontaine, Jean de` |
| Espagnol | Prénom d'abord | Sans un `de` initial | `Bartolomé de las Casas` → `Las Casas, Bartolomé de` |
| Toute autre, néerlandais de Belgique compris | Prénom d'abord | Avec la particule | `Herman Van Rompuy` → `Van Rompuy, Herman` |

Un nom sans culture suit la dernière ligne. Une application qui connaît mieux une culture construit sa propre
`NameConvention` — son ordre, les premiers mots d'une particule retirés de la clé de tri, si toute la particule est
retirée, si les noms se trient par le prénom, si les noms en caractères chinois, japonais ou coréens sont joints — et la
donne aux méthodes.

## Dans les autres formats

| Format | Le nom structuré |
|---|---|
| vCard | `N`, avec le second nom de famille et la génération de la RFC 9554 quand le nom les a, `LANGUAGE` tiré de la culture et `SORT-AS` quand la culture trie le nom autrement qu'il est écrit |
| schema.org | `givenName`, `additionalName`, `familyName`, `honorificPrefix` et `honorificSuffix` (génération et titre de compétence) du `Person` |
| JSContact | Les composantes de `name`, son `sortAs` et le `language` de la fiche — voir [JSContact](jscontact.md) |
| Stockage | La clé `structuredName` — voir [Stockage](storage.md) |
