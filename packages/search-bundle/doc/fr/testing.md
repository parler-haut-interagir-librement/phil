<!-- phil-dev source-sha256=bc0220e0507b30f2bf4d6d8f9a5358433fa83c9b14c2482eb69b09acca3a5804 -->
# Tests

| Aspect | Ce qui est couvert |
|---|---|
| `Document`, `Index`, `SearchQuery`, `SearchResult`, `Phrase`, `SynonymRule` | Chaque vérification et chaque borne ; les mots séparés sur toute espace ; les listes de valeurs, les facettes, les langues, les termes ; les facettes triées et plafonnées |
| `SynonymExpander`, `RejectedFormSynonyms`, `SynonymStoreProvider` | L'expression la plus longue d'abord, les règles fusionnées, les variantes plafonnées, une langue lue une fois jusqu'à la réinitialisation, la liste que reçoit un moteur, les formes rejetées |
| `SearchRequest`, `PhilSearchFilters`, `KitComponents` | Ce que contient une chaîne de requête et ce qui est écarté, les liens qui retirent des filtres ; les facettes et les valeurs affichées, les noms des recettes |
| `DbalSearchIndex`, sa table et son écouteur | Les mots et les filtres, l'échappement, le classement, les pages, le remplacement, le retrait, le vidage, une reconstruction en une seule transaction, les noms d'index, les variantes et les expressions, les listes de valeurs, les facettes comptées sans leur propre filtre |
| `MeilisearchIndex` | Les requêtes envoyées, les attributs filtrables fusionnés avec ceux de l'index, les valeurs de filtre avec accents, barres obliques et guillemets, les listes avec `IN`, les facettes en une recherche ou dans `/multi-search`, les synonymes, une reconstruction par échange, les réponses lues, les refus, les noms d'index |
| `Search`, `IndexOnFlushListener`, `ReindexCommand`, `SynonymsCommand` | L'indexation, plusieurs ressources en un appel et leurs retraits en un appel par index, les brouillons, plusieurs fournisseurs, les reconstructions par lots et leur échec, les noms d'index, les `flush`, leurs entités indexées ensemble dans l'ordre des changements, les suppressions sous l'identifiant lu avant le `flush`, les `flush` en échec et vidés, les commandes, les synonymes lus ou donnés au moteur |
| `PhilSearchBundle` | Les trois adaptateurs, les synonymes, le composant, le répertoire du contrôleur, les réglages refusés |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, DoctrineBundle sur SQLite, des pages et des notes et
leurs fournisseurs : les pages sont trouvées au fil de leurs `flush`, de leurs changements de nom et de leurs
suppressions, puis reconstruites ; une note, dont la base de données génère l'identifiant, quitte l'index quand elle
est supprimée. Le même noyau dessine une page de résultats de jeux avec les recettes du kit PHIL UX copiées là où le
Toolkit les mettrait : ses filtres sont cochés et retirés par de simples formulaires et liens, et ses mots lisent les
synonymes d'un répertoire.

```sh
vendor/bin/phpunit packages/search-bundle/tests
cd packages/search-bundle && node --import ./tests/js/register.mjs --test tests/js
```

`tests/js` exécute le contrôleur sous l'exécuteur de tests de Node, sa dépendance homologue remplacée par un *stub*.

## Dans un navigateur

`tests/Browser` pilote le contrôleur dans un Chrome sans interface avec Panther, sur la page de résultats du noyau
fonctionnel servie par le propre serveur de PHP : une case cochée redessine la page sur place, annonce le compte et
garde le focus ; un lien qui retire un filtre est suivi sur place ; un retour en arrière redessine les filtres
précédents ; la page n'est jamais rechargée. Depuis la racine du monodépôt, avec le chromedriver du système :

```sh
composer test:browser
```

## Bancs d'essai

Les sujets phpbench de `tests/Benchmark` mesurent les chemins critiques à plusieurs tailles : l'index de la base de données, avec facettes, et l'expanseur de
synonymes, à la première recherche d'une requête et aux suivantes. À partir de la racine du monodépôt :

```sh
composer bench -- packages/search-bundle/tests/Benchmark
```
