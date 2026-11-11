# Testing

## The library's tests

The suite `tests/Unit` builds a shirt template — versions, sizes and colours, an excluded combination, two faces —
and a design in two languages, and checks every product of its plan: SKUs, prices, channels, the artwork chosen on
each zone, names, attributes and photos. Every refusal is checked with its message, at and past each limit. Templates
and designs are written to JSON and read back equal.

From the monorepo root:

```sh
vendor/bin/phpunit packages/commerce-product-template/tests
```

The library is held to 100 % covered mutation score by Infection.

## Fuzz targets

`sku-pattern` parses patterns nobody wrote and renders those it accepts; `template-array` reads templates from JSON
nobody wrote. Each accepts the input or refuses it with `InvalidDefinition`, and nothing else:

```sh
composer fuzz
```

## Benchmark

`tests/Benchmark/VariantPlannerBench.php` plans a template of 3 versions, 18 sizes and 50 colours for a design in two
languages, 5 400 products, and reads a template back from its array.

## Testing code that plans

Build small templates in a fixture and compare the SKUs a plan gives, rather than counting products:

```php
$skus = array_map(static fn (Variant $variant): string => $variant->sku, new VariantPlanner()->plan($template, $design));

self::assertSame(['NAQ001FOMBK', 'NAQ001FOMPK'], $skus);
```
