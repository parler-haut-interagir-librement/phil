<!-- phil-dev source-sha256=2054fc6068c1863d8ff851e10e3e34b30659e61acfec077854712e59f2c20ef1 -->
# phil/search-bundle

**La recherche plein texte dans vos propres ressources, tenue à jour à chaque `flush` de Doctrine.**

phil/search-bundle donne une recherche à une application Symfony. Un `DocumentProvider` qu'écrit l'application change
une sorte de ressource en documents d'un index : le texte cherché et les champs servant aux filtres. Le bundle tient
chaque index à jour à chaque `flush` de Doctrine, y cherche, et le reconstruit avec `phil:search:reindex`. Une recherche
renvoie les identifiants trouvés, les meilleurs d'abord, avec leur total et le compte de chaque valeur des facettes
demandées. Charger les ressources et les afficher reste le travail de l'application, tout comme décider de ce qui peut
être trouvé : un fournisseur qui ne renvoie aucun document pour un brouillon le garde hors de l'index.

Une recherche lit ses mots avec les synonymes de leur langue : `jdr` trouve aussi `jeu de rôle`. Les synonymes viennent
de fournisseurs derrière un seul port : les listes de `phil/search-synonyms`, gardées dans des fichiers ou par
l'administration, les formes qu'un vocabulaire rejette pour ses termes préférés, ou une source de l'application.

La page de résultats reçoit ses filtres sous forme de composant Twig dessiné avec les recettes du kit PHIL UX : une case
par valeur de chaque facette avec son compte, les valeurs choisies sous forme de liens qui les retirent une à une ou
toutes ensemble, et le nombre de résultats dans une région dynamique. Tout fonctionne avec de simples liens et un
simple formulaire ; un contrôleur Stimulus applique une case dès qu'elle est cochée et redessine la page sur place, le
focus conservé et le compte annoncé.

Il sert à un site dont les visiteurs cherchent des pages, des événements ou des produits, et dont les développeurs
veulent une seule façon de décrire ce qui peut être cherché, quel que soit ce qui fait tourner l'index.
`SearchActionSchema` donne aussi la `SearchAction` de schema.org, qui permet à un moteur de recherche d'offrir une boîte
de recherche menant à la page de résultats du site lui-même.

L'index se trouve derrière le port `SearchIndex`, et trois adaptateurs le remplissent. Celui par défaut est une table
de la base de données de l'application, par Doctrine DBAL, pour un site qui n'a besoin d'aucun moteur ; il classe selon
la fréquence des mots. Meilisearch fonctionne par son API HTTP avec le client HTTP de Symfony. Loupe garde des fichiers
SQLite sans serveur à faire tourner, par le paquet séparé `phil/search-loupe`. Meilisearch et Loupe apportent leur
propre classement et leur propre tolérance aux fautes de frappe. Changer d'adaptateur tient en une ligne de
configuration et une réindexation, puisque les fournisseurs ne changent pas.

## État

| Partie | État |
|---|---|
| `Document`, `SearchQuery`, `SearchResult` | Implémentés et testés |
| `Search` : indexer, retirer, chercher, reconstruire ; `SearchIndex`, `DocumentProvider` | Implémentés et testés |
| `DbalSearchIndex`, sa table et son écouteur de schéma | Implémentés et testés |
| `MeilisearchIndex` | Implémenté et testé |
| L'indexation à chaque `flush` ; `phil:search:reindex` | Implémentées et testées |
| Les facettes : listes de valeurs, compte par valeur, chaque facette comptée sans son propre filtre | Implémentées et testées |
| Les synonymes par langue : le port, l'expanseur, le magasin de `phil/search-synonyms`, les formes rejetées ; `phil:search:synonyms` | Implémentés et testés |
| `SearchRequest` ; le composant `PhilSearchFilters` ; le contrôleur Stimulus `filters` | Implémentés et testés, aussi dans un navigateur |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement, la configuration, les adaptateurs. |
| [Fournisseurs](doc/fr/providers.md) | Écrire un fournisseur ; ce qu'indexe un `flush`. |
| [Recherche](doc/fr/searching.md) | Les requêtes, les filtres, les facettes, les pages, le classement. |
| [Synonymes](doc/fr/synonyms.md) | Le port, ses fournisseurs, la façon dont une requête est élargie, les moteurs qui gardent des synonymes. |
| [Filtres](doc/fr/filters.md) | La page de résultats : `SearchRequest`, le composant, le contrôleur, sans JavaScript. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | La façon dont le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
$result = $search->search('pages', SearchQuery::of('jeux de rôle', ['locale' => 'fr', 'genre' => ['jdr', 'plateau']], facets: ['genre']));
// $result->ids, best first; $result->total; $result->facet('genre'), each value and its count
```

```twig
<twig:PhilSearchFilters :request="request" :result="result" :labels="{genre: 'Genre'}" results="results" />
```

```bash
bin/console phil:search:reindex
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; Doctrine DBAL 4.5 et ORM 3.7 ; l'injection de dépendances et la
configuration de Symfony 8.2. Le client HTTP de Symfony, pour Meilisearch, `phil/search-loupe`, pour Loupe,
`phil/search-synonyms`, pour les listes de synonymes, Symfony UX Twig Component et les recettes de `phil/ux-kit`, pour
les filtres, et Symfony Console sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
