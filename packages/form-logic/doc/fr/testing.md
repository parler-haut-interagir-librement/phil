<!-- phil-dev source-sha256=6692abb46441643337efc43e43d65c08c43676f373d6a2f0b5eda3ddaf21976c -->
# Tests

## Les tests du paquet

`tests/Unit` couvre les expressions et leurs vérifications, le lecteur — chaque opérateur, les formes décodée et JSON,
chaque limite à sa borne, ce qui est refusé — et l'écrivain, les conversions de `Values` — nombres écrits comme
JavaScript les écrit, égalité lâche et stricte, ordre des textes par unités de code UTF-16 —, l'évaluateur, le
vérificateur de types et le schéma, la logique d'un formulaire, son ordre et sa vérification, le juge et son verdict,
`JsonLogicConditions` sur les champs de `phil/forms-bundle`, et les étapes qu'écrit la page de ce bundle, lues comme une
logique dont le juge masque les champs que le bundle ne demande pas, sous l'un ou l'autre évaluateur.

`tests/js` exécute l'évaluateur, le contrôleur et les cas publiés avec l'exécuteur de tests de Node, Stimulus résolu en
*stub*, sur une petite doublure du DOM : la réponse collectée de chaque sorte de contrôle, champs masqués en cascade et affichés de
nouveau avec leurs valeurs, sorties calculées, règles illisibles et événements.

Depuis la racine du monodépôt, puis depuis le paquet :

```sh
vendor/bin/phpunit packages/form-logic/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/form-logic
```

Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Les cas communs

`tests/Fixtures/cases.json` contient les cas que passent les deux évaluateurs : sous `evaluate`, une règle, une réponse
et la valeur attendue, groupées par opérateur après un titre ; sous `refuse`, une règle et la raison pour laquelle le
lecteur la refuse. PHPUnit évalue chaque cas, puis relit la règle depuis sa forme canonique et l'évalue une seconde
fois ; Node vérifie et évalue chaque cas avec `assets/src/logic.js`. Un cas ajouté au fichier s'exécute dans les deux.

Les cas ont été rédigés pour ce paquet, d'après la description publiée de chaque opérateur de JsonLogic, et ajoutent
les règles du paquet : un texte lu comme un nombre en notation décimale seulement, des résultats non finis comme
`null`, des nombres écrits en texte, des textes ordonnés par unités de code UTF-16.

## Les cas que JsonLogic publie

`tests/Fixtures/json-logic/tests.json` est le fichier de cas de test de JsonLogic, conservé tel quel avec son avis MIT
dans `LICENSE`, à côté : depuis https://github.com/jwadhams/json-logic, branche `gh-pages`, commit `2964b8da` du
2024-07-09, SHA-256 `a202b65edda0d7ab687c758b8f10cfe9ba75561cd7166e5650710f88f26303a4`. `OfficialCasesTest` et
`tests/js/official.test.mjs` exécutent chaque cas :

- une règle du sous-ensemble donne la valeur qu'attend JsonLogic — 198 cas ;
- une règle que le lecteur refuse est listée sous `refused` dans `differences.json`, groupée par raison : les
  opérateurs hors du sous-ensemble (`cat`, `substr`, `merge`, `map`, `filter`, `reduce`, `all`, `none`, `some`, `?:`),
  une variable nommée par un nombre ou par aucun nom, un nom calculé par une opération, un `if` sans condition ni
  valeur, un `missing` qui ne nomme rien — 71 règles, dans 80 cas. Un refus non listé, ou une règle listée mais lue,
  fait échouer la suite.

Aucun cas publié n'atteint les différences sur les valeurs ; `differences.json` les liste sous `values`, chacune avec
une règle, la valeur que donne la bibliothèque et sa raison, et les deux suites vérifient cette valeur : un texte lu
comme un nombre en notation décimale seulement, deux listes jamais égales, un résultat non fini comme `null`, une
variable à `null` qui prend sa valeur par défaut, et `!=` vrai pour un champ sans valeur, comme le donnent aussi les
conditions de base de `phil/forms-bundle`.

L'archive qu'installe Composer contient `tests/`, le fichier et son avis avec lui, de sorte que les suites s'exécutent
depuis une copie installée du paquet.

## La suite du navigateur

`tests/Browser/LogicBrowserTest.php` pilote le contrôleur dans Chrome avec Panther, dans une application installée
comme le dit [Installation](installation.md#le-contrôleur-stimulus) : `BrowserKernel` mappe `assets/src` comme
`@phil/form-logic` dans AssetMapper, active le contrôleur dans `assets/controllers.json` pour StimulusBundle, et sert
le formulaire par le serveur intégré de PHP. Elle vérifie la table d'importation, les champs masqués et affichés en
cascade avec leurs contrôles désactivés et leurs valeurs relues, un contrôle que la page a désactivé et qui le reste,
une sortie calculée, un champ masqué que le navigateur n'envoie pas et que le serveur juge masqué aussi, et une règle
hors du sous-ensemble qui laisse tous les champs affichés.

Elle a besoin du chromedriver du système et de Stimulus, installé par `npm ci --ignore-scripts` dans `tests/Browser`,
et elle est ignorée sans eux. Depuis la racine du monodépôt, avec les autres suites de navigateur :

```sh
composer test:browser
```

## La cible de fuzzing

`tests/Fuzz/json-logic-reader.php` soumet le lecteur au fuzzing avec les germes de
`tests/Fuzz/corpus/json-logic-reader` : quoi qu'il lise, il donne une expression ou lance `InvalidLogic` ; une
expression écrit une règle canonique qui se relit en elle-même, et s'évalue sans rien lancer.

```sh
composer fuzz -- json-logic-reader
```

## Les bancs d'essai

`tests/Benchmark/EvaluatorBench.php` lit et évalue des règles de 250, 500 et 1 000 nœuds — comparaisons jointes par
`and`, appartenances, opérations imbriquées — et juge un formulaire de 100 étapes. Le temps croît avec les nœuds, non
avec leur carré.

```sh
composer bench -- --filter=EvaluatorBench
```
