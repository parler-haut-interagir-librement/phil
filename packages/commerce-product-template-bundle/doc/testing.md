# Testing

## The module's tests

`tests/Unit` runs without a kernel, on in-memory repositories: a generation creates the twelve products of a shirt
design in two languages, runs again without a change, updates a price and a tax category, withdraws a language
dropped and restores it, refuses a SKU of another group, a product outside any group, a change of kind or currency,
and leaves the catalogue untouched when it refuses. The options of a product are checked with their conditions and a
design's replacing a template's; mockups are composed from PNG images drawn by GD and compared pixel by pixel, named
by their content, composed once and again only when an image changes. Options are written to JSON and read back
equal; every array no code wrote is refused. Production composes the print files of a small shirt at 72 dpi — fixed
objects, two dice of objects, a tombstone, an epitaph in a test font, a glowing ink —, checks them pixel by pixel,
groups the units printed alike, writes nothing more when run again, and refuses an order not placed or not drawn, a
draw that does not read, a text that does not fit and a missing image or font; the Messenger handler retries what may
succeed later and nothing else. Print elements read back from JSON equal, and dice read and write one character per
die.

`tests/Functional` boots a kernel with the core, the catalogue and the module on an in-memory SQLite database: a
template and a design are stored, generated, flushed, generated again without a change, their mockups written to a
temporary directory, their page read, and an order line configured with a template's option charged by the core; a
paid order of a loot box shirt is produced by the message its purchase dispatches, its files written to a private
directory.

`tests/js` runs the Stimulus controller under Node's test runner, its peer dependency resolved to a stub:

```sh
node --import ./tests/js/register.mjs --test tests/js
```

From the monorepo root:

```sh
vendor/bin/phpunit packages/commerce-product-template-bundle/tests
```

The module is held to 100 % covered mutation score by Infection.

## Testing an application that generates

Generate against the application's own test database, flush, and assert on the report rather than on rows:

```php
$report = $generation->generate('NAQ001', 'unisex');
$entityManager->flush();

self::assertTrue($generation->generate('NAQ001', 'unisex')->changedNothing());
```

For mockups, point `images_directory` at a temporary directory and write small PNG images there.

For production, point `production.directory` at a temporary directory, configure a small font under
`production.fonts`, give the zones of a test template a low resolution so files compose fast, and assert on the
returned `ProductionOrder` rather than on bytes.
