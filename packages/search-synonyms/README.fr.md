<!-- phil-dev source-sha256=db463483ca9d65cc300fa6a0fb3cafd3b977ca6f8827f5329d932f6dbeb6bfb0 -->
# phil/search-synonyms

**Synonymes de recherche par langue : règles d'équivalence et à sens unique, validées, lues et écrites au format de
synonymes de Solr ou en JSON, gardées dans des fichiers ou dans le magasin propre à l'application.**

phil/search-synonyms est une bibliothèque PHP ordinaire. Elle contient les synonymes que la recherche d'un site lit
dans chaque langue : des expressions équivalentes, dont chacune trouve les autres — `jdr, jeu de rôle` —, et des règles
à sens unique, dont le côté gauche est cherché comme le côté droit — `donjon => donjon, dungeon`. Chaque expression est
gardée comme une recherche compare les mots, en minuscules, avec une seule espace entre les mots, et chaque règle est
vérifiée à sa construction : une expression trop longue, une règle dont un côté est vide, une règle qui ne change rien
sont refusées.

Les règles d'une langue sont lues et écrites au format de synonymes de Solr — le fichier texte que de nombreux moteurs
chargent — ou en document JSON adapté à une exportation ou à un formulaire d'administration ; un fichier qui n'est pas
une liste de règles est refusé avec le numéro de sa ligne. Un validateur relève ce qui est permis mais probablement une
erreur, avant qu'un administrateur enregistre une liste : une expression à gauche de deux règles, ou une règle à sens
unique qui mène à une expression qu'une autre règle réécrit.

Les listes sont gardées derrière le port `SynonymStore` : un répertoire de fichiers, un par langue, écrits de façon
atomique ; la mémoire ; ou une table de l'application. `phil/search-bundle` lit un magasin par son port de synonymes,
de sorte qu'une recherche de `jdr` trouve aussi `jeu de rôle`.

## État

| Partie | État |
|---|---|
| `SynonymSet`, `SynonymList`, `InvalidSynonyms` | Implémentés et testés |
| `SolrSynonymsReader`, `SolrSynonymsWriter`, `JsonSynonyms` | Implémentés, testés et soumis au fuzzing |
| `SynonymValidator`, `Violation` | Implémentés et testés |
| `SynonymStore`, `FileSynonymStore`, `InMemorySynonymStore` | Implémentés et testés |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Règles](doc/fr/rules.md) | Les règles d'équivalence et à sens unique, les expressions, les limites, le validateur. |
| [Formats](doc/fr/formats.md) | Le format de synonymes de Solr et le document JSON, lus et écrits. |
| [Magasins](doc/fr/stores.md) | Fichiers, mémoire, magasin de l'application ; avec `phil/search-bundle`. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter, et quand c'est stable. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et ses cibles de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Phil\SearchSynonyms\Infrastructure\Format\SolrSynonymsReader;
use Phil\SearchSynonyms\Infrastructure\Store\FileSynonymStore;

$list = new SolrSynonymsReader()->read("jdr, jeu de rôle\ndonjon => donjon, dungeon\n", 'fr');
$store = new FileSynonymStore(__DIR__.'/config/synonyms');
$store->save($list);            // config/synonyms/fr.txt
$store->get('fr-CA')->sets;     // the two rules
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
