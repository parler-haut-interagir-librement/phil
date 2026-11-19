<?php

declare(strict_types=1);

/*
 * The price a buyer chose, the value of the `price` option of a `pay_what_you_want` line, on text nobody wrote:
 * whatever it reads, the option refuses it, or keeps it as minor units within the product's bounds, written as given
 * and charged as written; and nothing else throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceCatalogBundle\Application\PriceRuleSellables;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Product\ProductKind;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$zine = Product::create('ZINE', ProductKind::Digital, 'Zine', Money::of(500, Currency::of('CAD')), 'books');
$zine->payWhatYouWant(Money::of(800, Currency::of('CAD')), Money::of(5_000, Currency::of('CAD')));
$options = new PriceRuleSellables(new readonly class($zine) implements ProductRepository {
    public function __construct(private Product $zine)
    {
    }

    public function find(string $sku): ?Product
    {
        return $sku === $this->zine->sku ? $this->zine : null;
    }

    public function add(Product $product): void
    {
    }
})->optionsOf(PriceRuleSellables::reference($zine), Currency::of('CAD'));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($options): void {
    try {
        $configuration = $options->check(LineConfiguration::of([PriceRuleSellables::PRICE_OPTION => OptionValue::chosen($input)]));
    } catch (InvalidValue|InvalidConfiguration) {
        return;
    }

    $kept = $configuration->value(PriceRuleSellables::PRICE_OPTION)?->value;
    $supplements = $options->supplements($configuration);
    $charged = $supplements[0][1] ?? null;

    if ($kept !== $input || 1 !== count($supplements) || !$charged instanceof Money || (string) $charged->minorUnits !== $input
        || $charged->minorUnits < 500 || $charged->minorUnits > 5_000) {
        throw new LogicException('A chosen price out of bounds, or not as written, was kept.');
    }
});
$config->setMaxLen(32);
