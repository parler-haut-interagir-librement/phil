# Testing

## The package's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit suite, under `tests/Unit`, runs without a kernel, a database or a network: it builds both events with
every kind of language tag and owner identifier, accepted and refused, at their length limits. In the monorepo,
Infection holds the package at 100 % of covered mutants.

## Testing code that dispatches the events

An owner's test dispatches on a recording dispatcher and reads the events back:

```php
$dispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();
$events = [];
$dispatcher->addListener(PagePublished::class, static function (PagePublished $event) use (&$events): void {
    $events[] = $event;
});

// ... publish a page through the owner ...

self::assertSame('news:42', $events[0]->ownerId);
```
