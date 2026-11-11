<!-- phil-dev source-sha256=55581468f6eb69d43609d7a9da974cc49d56a1acb3dd2518b8952406e4b41ccd -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste chaque découpeur sur des jetons exacts et sur sa capacité à redonner son texte ;
l'algorithme sur des scripts exacts — dont l'exemple de l'article de Myers —, sur la borne de modifications et sur la
jonction des espaces. Pour chaque paire de listes de jetons jusqu'à cinq jetons de deux lettres et trois jetons de
trois lettres, elle vérifie que le script redonne les deux listes, qu'il est normalisé et qu'il est le plus court, par
comparaison avec une plus longue sous-suite commune calculée à part. Elle teste aussi la liste de changements et ses
positions en points de code ; le HTML du texte brut et du balisage ; l'appariement des documents — conservés,
modifiés, déplacés, ajoutés, retirés, imbriqués, au-delà de la borne — et leur HTML.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/text-diff/tests
```

Infection maintient la bibliothèque à un score de 100 % de mutants couverts.

## Bancs d'essai

`TextDiffBench` compare des textes de 1, 10 et 50 Ko avec quelques modifications, des textes réécrits d'un bout à
l'autre, qui s'arrêtent à la borne de modifications, et des documents de 100 et 1 000 blocs. Depuis la racine du
monodépôt :

```sh
composer bench -- packages/text-diff/tests/Benchmark
```

En mode *worker*, `tests/Worker/worker.php` est une page que FrankenPHP sert depuis un seul processus d'une requête à
l'autre, en comparant 5 Ko de texte mot par mot et 5 Ko de HTML ; les services qu'elle construit avant sa boucle
servent chaque requête, comme dans le *worker* d'une application :

```sh
composer bench:worker phil/text-diff
```

## Tester du code qui montre des différences

La sortie est déterministe. Comparez le HTML exactement, ou comparez la liste de changements :

```php
self::assertSame('a <del>b</del><ins>c</ins>', (new TextDiff())->toHtml((new TextDiff())->words('a b', 'a c')));

$changes = (new TextDiff())->words('a b', 'a c')->changes();
self::assertSame([ChangeKind::Replacement, 'b', 'c'], [$changes[0]->kind, $changes[0]->old, $changes[0]->new]);
```

Pour les documents, vérifiez les sortes et les identifiants de `DocumentDiff::changes()` plutôt que le HTML rendu, qui
dépend du moteur de rendu.
