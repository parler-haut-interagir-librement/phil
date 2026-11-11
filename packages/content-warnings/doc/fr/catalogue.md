<!-- phil-dev source-sha256=847c6a5b46cf15103e6fb2ca58f6c960f997971953b595591067db5a53640958 -->
# Catalogue

Un `Catalogue` énumère les thèmes pour lesquels un contenu peut recevoir un avertissement, regroupés en catégories, dans
l'ordre de leur définition.

## Clés et libellés

Chaque thème et chaque catégorie a une **clé** — des lettres ASCII minuscules et des chiffres, en mots joints par un
seul trait d'union, 64 caractères au plus, `self-harm` — et un **libellé** dans une langue ou plus. `Key::isValid()`
indique si une clé est valide. Les clés sont stables : les avertissements et les préférences les nomment, alors que les
libellés peuvent être reformulés librement.

Un `LocalizedText` contient un texte par locale. Une locale est un code de langue de deux ou trois lettres minuscules,
suivi au besoin d'au plus trois sous-étiquettes, `fr_CA` ou `yue_Hant_HK` ; un trait d'union est lu comme un trait de
soulignement. Les textes sont épurés de leurs espaces de début et de fin, les textes vides sont écartés, et chacun
compte 500 caractères au plus. `in($locale, $fallbacks)` lit le texte dans la locale, puis dans sa langue (`fr` pour
`fr_CA`), puis dans le premier repli qui en a un (`['en']` par défaut), puis le premier texte fourni.

| Type | Contenu |
|---|---|
| `Category` | `key`, `label` ; `label($locale, $fallbacks)` |
| `Theme` | `key`, `category` (une clé de catégorie), `label`, `description` (éventuellement vide) ; `label()` et `description()` par locale |
| `Catalogue` | `theme($key)`, `has($key)`, `category($key)`, `themes()`, `categories()`, `themesIn($category)` |

Un catalogue refuse une clé fournie deux fois, un thème dans une catégorie qu'il n'a pas, et plus de 200 catégories ou 2
000 thèmes, avec `InvalidCatalogue`.

## La liste de base

`BaseCatalogue::load()` lit la liste fournie dans `resources/base-catalogue.php` : environ soixante-dix thèmes en douze
catégories — violence, sexualité, abus, mort et deuil, santé mentale, substances et dépendance, discrimination, corps et
médecine, phobies, animaux, sensoriel, autres thèmes —, chacun libellé en anglais, en français, en espagnol, en
allemand, en italien et en portugais. Son chargement prend bien moins d'une milliseconde ; une application la charge une
fois et conserve le catalogue, qui est immuable.

## Définitions

`CatalogueLoader::apply($definition, $onto)` construit un catalogue à partir d'une définition, ou applique la définition
à un catalogue existant et en renvoie un nouveau :

```php
$catalogue = new CatalogueLoader()->apply([
    'categories' => [
        'venues' => ['label' => ['en' => 'Venues', 'fr' => 'Lieux']],
    ],
    'themes' => [
        'crowds' => ['category' => 'venues', 'label' => ['en' => 'Crowds', 'fr' => 'Foules']],
        'gore' => ['label' => ['en' => 'Graphic gore']],
    ],
    'remove' => ['clowns'],
], BaseCatalogue::load());
```

| Clé | Effet |
|---|---|
| `replace` | `true` part d'un catalogue vide au lieu de celui qui est fourni |
| `categories` | Les catégories par clé, chacune avec son `label` : ajoutées, ou fusionnées avec une catégorie existante |
| `themes` | Les thèmes par clé, avec `category`, `label` et `description` : ajoutés, ou fusionnés avec un thème existant |
| `remove` | Les clés de thèmes retirées du catalogue |
| `remove_categories` | Les clés de catégories retirées, une fois qu'il ne reste aucun de leurs thèmes |

Les étapes s'exécutent dans cet ordre. Une nouvelle catégorie ou un nouveau thème vient après ceux qui existent ; un
élément existant garde sa place. Les libellés et les descriptions sont fusionnés par locale, et la définition
l'emporte ; la catégorie d'un thème est remplacée si elle est fournie. Un nouveau thème exige une catégorie et un
libellé.

Chaque étape est vérifiée. Une clé inconnue, une entrée qui n'est pas un tableau, un thème sans catégorie ou sans
libellé, une clé à retirer qui n'existe pas, ou une catégorie retirée alors qu'il lui reste des thèmes lève
`InvalidCatalogue`, et rien de la définition n'est appliqué.

`applyFile($path, $onto)` applique la définition que renvoie un fichier PHP ou que contient un fichier JSON, de 2 Mo au
plus ; le JSON est lu sur 16 niveaux de profondeur au plus.
