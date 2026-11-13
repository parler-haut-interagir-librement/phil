<!-- phil-dev source-sha256=2f91057c4db4abaf9c25b213710fb6701c09c377d4d8e8ef8b4f1960bc9293d2 -->
# phil/number-words

**Les nombres en toutes lettres, dans toute langue qu'ICU épelle : cardinaux, ordinaux, genre et montants d'argent,
corrigés là où ICU s'écarte de l'usage.**

phil/number-words est une façade typée sur les règles d'épellation d'ICU (`NumberFormatter::SPELLOUT` de `ext-intl`).
Elle dit un cardinal seul (« vingt et un », « eins ») ou accordé au genre d'un nom (« vingt et une », « veintiún »,
« ein »), un ordinal selon son genre et son nombre (« premières »), et un montant d'argent en lettres avec ses unités
et ses sous-unités (« cent vingt dollars et cinq cents »), pour un chèque, un certificat, un contrat ou un texte lu à
voix haute.

ICU connaît les nombres de dizaines de langues ; pour le français, l'anglais, l'espagnol, l'allemand, l'italien et le
portugais, la bibliothèque ajoute ce qu'ICU omet ou rend mal : le pluriel de `cents` et de `quatre-vingts` devant
`millions`, les traits d'union du français dans l'orthographe traditionnelle ou dans les rectifications de 1990, les
traits d'union conditionnels qu'ICU place dans les longs mots, le partitif après `million` (« un million d'euros »,
« un millón de dólares »), le singulier de zéro en français et les noms des devises courantes. Rien ne se rabat en
silence : une locale qu'ICU n'épelle pas, un genre ou une forme que la langue n'a pas, un nombre trop grand pour les
lettres, chacun lève une exception qui le dit.

C'est une bibliothèque PHP simple, bâtie sur `ext-intl`.

## État

| Partie | État |
|---|---|
| Cardinaux, comptage et accord avec un genre ; ordinaux, genrés et au pluriel | Implémenté et testé |
| Montants d'argent en lettres, unités et sous-unités, pour dix devises en six langues, ou noms fournis | Implémenté et testé |
| Corrections du français : pluriels devant les grands noms, traits d'union traditionnels ou de 1990, élision de `de` | Implémenté et testé |
| Partitifs en espagnol, en italien et en portugais, traits d'union conditionnels, anglais détaillé | Implémenté et testé |
| Exceptions pour une extension, une locale, un jeu de règles ou une devise manquants, ou un nombre hors limites | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Épeler des nombres](doc/fr/spelling.md) | Cardinaux, ordinaux, montants, options, et ce que corrige chaque langue. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et son seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/number-words
```

```php
use Phil\NumberWords\Gender;
use Phil\NumberWords\NumberWords;

$words = new NumberWords('fr_CA');
$words->cardinal(21);                      // vingt et un
$words->cardinal(21, Gender::Feminine);    // vingt et une
$words->ordinal(1, Gender::Feminine);      // première
$words->amount('120.05', 'CAD');           // cent vingt dollars et cinq cents
$words->amount('200000000', 'EUR');        // deux cents millions d'euros
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-intl`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
