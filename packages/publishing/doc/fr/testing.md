<!-- phil-dev source-sha256=9e0ce067dee140f6f28c11781d63076cab0f855ac5b4011790ae7714523eb532 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : elle construit les
deux événements avec toutes sortes d'étiquettes de langue et d'identifiants de propriétaire, acceptés et refusés, à
leurs limites de longueur. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Tester le code qui répartit les événements

Le test d'un propriétaire répartit sur un répartiteur enregistreur et relit les événements :

```php
$dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
$events = [];
$dispatcher->addListener(PagePublished::class, static function (PagePublished $event) use (&$events): void {
    $events[] = $event;
});

// ... publish a page through the owner ...

self::assertSame('news:42', $events[0]->ownerId);
```
