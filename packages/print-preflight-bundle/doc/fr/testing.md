<!-- phil-dev source-sha256=73368a632eb0b4ee9fdd9276e8d4f3422661d5d962416d59661e5c2e1ea920d8 -->
# Tests

## Les tests du bundle

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/print-preflight-bundle/tests
```

`tests/Unit` lit des documents mesurés et leurs refus champ par champ, dessine par `PdfLayout` avec de petits fichiers
PNG et TrueType écrits octet par octet, place des médias construits en mémoire, et exécute la commande avec
`CommandTester`. `tests/Functional` démarre une application avec FrameworkBundle, `phil/fonts-bundle` sur une police
des tests, un profil et une vérification qui lui est propre, et vérifie un document mesuré dont la police est trouvée
par famille. Infection tient le bundle à un score de 100 % des mutants couverts.

## Tester une application qui l'utilise

Gardez un document mesuré d'une mise en page connue comme jeu de données d'essai JSON, et vérifiez les codes du
rapport :

```php
$report = $preflighter->check($measuredDocument->fromJson(file_get_contents(__DIR__.'/fixtures/zine.json')), 'booklet');

self::assertSame([], array_map(static fn (Finding $finding): string => $finding->code, $report->errors()));
```
