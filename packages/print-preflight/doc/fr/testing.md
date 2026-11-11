<!-- phil-dev source-sha256=cd48b15880234f1c43502e2b93bab3488a2f628eefd57f40c1afd001cf338df3 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` construit ses documents à partir du modèle, sans fichier : les unités et leurs conversions, la
tolérance à ses limites exactes, chaque validation et son message, chaque règle de chaque vérification avec la phrase,
la gravité, la page, le sujet et les paramètres exacts qu'elle signale, les limites des règles de placement sur chaque
bord au dixième de point près, les gravités et les règles ignorées du profil, l'ordre du rapport et une vérification
ajoutée par l'application.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/print-preflight/tests
```

La bibliothèque est tenue à un score de mutation couvert de 100 % par Infection.

## Tester du code qui exécute une vérification

Construisez le modèle directement, avec des nombres ronds en points, et comparez des codes plutôt que des phrases quand
le test porte sur les règles qui se déclenchent :

```php
use Phil\PrintPreflight\Document;
use Phil\PrintPreflight\Finding;
use Phil\PrintPreflight\Page;
use Phil\PrintPreflight\Preflight;
use Phil\PrintPreflight\Size;

$report = new Preflight()->check(new Document([new Page(Size::pt(500, 700))]));

self::assertSame(['bleed.too-small'], array_map(static fn (Finding $finding): string => $finding->code, $report->findings));
```

La vérification est déterministe : le même document et le même profil donnent le même rapport, dans le même ordre.
