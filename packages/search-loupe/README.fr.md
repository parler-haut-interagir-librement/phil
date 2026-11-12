<!-- phil-dev source-sha256=f28a037140e84c595d0fdab2edff50cef9383ce578b28fbeab602fc23ecdbc73 -->
# phil/search-loupe

**Une recherche tolérante aux fautes de frappe dans des fichiers SQLite pour phil/search-bundle, sans serveur à
exploiter.**

phil/search-loupe est l'adaptateur Loupe de `phil/search-bundle`. Il garde chaque index du bundle dans des fichiers
SQLite, dans un répertoire à lui, et apporte la pertinence et la tolérance aux fautes de frappe de Loupe. Seuls les
champs texte sont cherchés, et un document est trouvé quand il contient tous les mots cherchés, comme avec
l'adaptateur `dbal` du bundle. Il n'ajoute rien à l'API du bundle : les fournisseurs, les requêtes et les résultats
restent les mêmes.

Choisissez-le pour un site qui veut un meilleur classement que celui d'une table de base de données, sans exploiter ni
sécuriser un serveur Meilisearch : un hébergement mutualisé, un petit serveur, une suite de tests. Un site avec un gros
index ou plusieurs serveurs qui y écrivent convient mieux à Meilisearch, et un site aux besoins modestes convient à
l'adaptateur `dbal`, celui par défaut du bundle.

C'est un paquet à part pour que `phil/search-bundle` ne déclare aucune dépendance envers Loupe, dont les propres
dépendances comprennent un paquet sous licence réciproque ([Licences](#licences)). La seule classe, `LoupeIndex`,
implémente le port `SearchIndex` du bundle ; le bundle la prend avec `adapter: loupe`. Loupe déclare les champs d'un
index avant d'indexer. L'adaptateur les apprend donc à mesure que les documents arrivent, dans l'ordre où un
fournisseur les donne, les garde dans `phil_search.json` à côté de l'index, et remplit l'index de nouveau une fois
quand un nouveau champ apparaît. Chaque processus vérifie ce fichier avant d'utiliser l'index, et une reconstruction
ou un nouveau champ remplit un nouveau répertoire avant de basculer vers lui : l'index reste interrogeable.

## État

| Partie | État |
|---|---|
| `LoupeIndex` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, les licences, choisir l'adaptateur, le répertoire des index. |
| [Comment il indexe](doc/fr/indexing.md) | Les champs cherchés et filtrés, la reconstruction quand un champ apparaît, ce qui correspond. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, les arguments et les données qu'une application choisit ou remplace. |
| [Tests](doc/fr/testing.md) | Comment le paquet est testé. |

## Démarrage rapide

```sh
composer require phil/search-loupe
```

```yaml
# config/packages/phil_search.yaml
phil_search:
    adapter: loupe
    loupe:
        directory: '%kernel.project_dir%/var/loupe'
```

## Prérequis

PHP 8.5 ou plus récent avec `pdo_sqlite` sur SQLite 3.35 ou plus récent et `mbstring` ; Loupe 1.1 ;
`phil/search-bundle` 0.1.

## Licences

Ce paquet est sous licence MIT. Loupe l'est aussi, mais il requiert `wamania/php-stemmer`, qui requiert
`joomla/string`, sous GNU GPL 2.0 ou plus récente : une application qui installe ce paquet installe aussi celui-là.
Lisez sa licence avant de choisir cet adaptateur ; les autres adaptateurs de `phil/search-bundle` ne l'apportent pas.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
