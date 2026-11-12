<!-- phil-dev source-sha256=5f2cfe7520ee22034a8779b06a5b4f71ba2528086676a374fbac0c30f956b644 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste les textes et leurs langues, les adresses, le contact pour la rétroaction, la procédure de
recours, l'évaluation, les non-conformités, les versions de WCAG et les critères que mesure chaque cible, l'état déduit,
chaque refus d'une déclaration, les données simples écrites puis relues avec chaque refus du lecteur, les rapports relus
et la version suivante générée à partir d'un audit.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/accessibility-statement/tests
```

La bibliothèque est tenue à un score de mutation couvert de 100 % par Infection.

## Fuzzing

Deux cibles de *fuzzing* donnent aux lecteurs des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/` :

- `statement-reader` : quoi que lise `StatementReader::fromJson()`, il retourne une déclaration qui se réécrit en
  données qu'il relit à l'identique, ou refuse l'entrée avec une `InvalidStatement`. Il ne lève jamais autre chose et
  n'émet aucun avertissement ;
- `report-reader` : quoi que lise `ReportReader::fromJson()`, il retourne un rapport qui se sérialise en JSON, ou refuse
  l'entrée avec une `InvalidReport`.

```sh
composer fuzz -- statement-reader report-reader
```

## Tester avec la bibliothèque

Une déclaration est une valeur : construisez-en une dans un test, ou lisez-la à partir d'un tableau, et comparez
`toArray()`. Un test du générateur n'a pas non plus besoin de vérificateur : un `AuditResult` composé de `Report`
construits à partir de `Finding` donne les constats voulus.
