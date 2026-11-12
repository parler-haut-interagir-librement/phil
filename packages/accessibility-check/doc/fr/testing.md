<!-- phil-dev source-sha256=b5c432b76399d2a0cd426c371f90f0ccd8e907f50a2aaf785e439d62fd14b985 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste chaque règle sur du HTML et sur des documents UJO : ce qu'elle signale, où, avec quelle
sévérité et ce qu'elle laisse de côté. Elle teste aussi les couleurs CSS et le contraste de WCAG, les étiquettes de
langue, la lecture du HTML et des documents UJO à leurs limites, les noms accessibles, la politique, les messages dans
leurs six langues, le rapport et son JSON, la contrainte, la garde de publication et la commande.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/accessibility-check/tests
```

La bibliothèque est tenue à un score de mutation couvert de 100 % par Infection. Les mutants qu'aucun test ne peut
distinguer sont nommés, avec leur raison, dans `phil-dev.php`.

## Fuzzing

La cible de *fuzzing* `html-checker` donne au vérificateur HTML des entrées que personne n'a écrites, à partir des
germes de `tests/Fuzz/corpus/`. Quoi qu'il lise, il retourne un rapport qui se sérialise en JSON, ou refuse le contenu
avec l'une de ses propres exceptions. Il ne lève jamais autre chose et n'émet aucun avertissement. À partir de la racine
du monodépôt :

```sh
composer fuzz -- html-checker
```

## Banc d'essai

`tests/Benchmark/AccessibilityCheckBench.php` vérifie un document de 50 pages — 1 500 paragraphes, 300 titres, 100
images, 50 tableaux, quelque 600 liens et encadrés colorés —, en HTML et en document UJO :

```sh
composer bench -- packages/accessibility-check/tests/Benchmark
```

La vérification lit le contenu une fois pour l'indexer, puis chaque règle lit l'index : le temps croît avec la taille du
contenu, pas avec son carré.

## Tester du code qui vérifie du contenu

Le rapport est déterministe. Comparez les règles et les clés de ses constats, ou leurs emplacements :

```php
$report = new AccessibilityChecker()->checkHtml('<img src="cat.jpg">');

self::assertFalse($report->passes());
self::assertSame(
    [['image-alt', 'image-alt.missing']],
    array_map(static fn (Finding $finding): array => [$finding->rule, $finding->message->key], $report->findings),
);
```

Une règle de l'application se teste sur un contenu qui l'enfreint et sur un autre qui la respecte, par
`HtmlTarget::parse()` ou `UjoTarget::read()` et son `checkHtml()` ou `checkUjo()`. Un formulaire qui valide avec
`AccessibleContent` se teste avec `ConstraintValidatorTestCase` de Symfony et `AccessibleContentValidator`, comme le
fait le propre test de la bibliothèque. Une garde se teste avec un `GuardEvent` construit à la main.
