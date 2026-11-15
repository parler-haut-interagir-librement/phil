<?php

declare(strict_types=1);

/*
 * The amount a buyer chose on an order line, on text nobody wrote: whatever it reads, the option refuses it, or keeps
 * it as written, within its range, and charges exactly that amount; and nothing else throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\AmountRange;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$range = AmountRange::of(Money::of(500, Currency::of('CAD')), Money::of(5_000, Currency::of('CAD')));
$option = OptionDefinition::amount('price', 'Price', $range);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($option, $range): void {
    try {
        $value = $option->check(OptionValue::chosen($input));
    } catch (InvalidValue|InvalidConfiguration) {
        return;
    }

    $charged = $value instanceof OptionValue ? $option->supplement($value) : null;

    if ($value?->value !== $input || !$charged instanceof Money || null !== $range->refusal($charged) || (string) $charged->minorUnits !== $input) {
        throw new LogicException('An amount out of range, or other than the one written, was kept.');
    }
});
$config->setMaxLen(32);
