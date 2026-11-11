<?php

declare(strict_types=1);

/*
 * The computation of lengths, durations and numbers on inputs nobody wrote, the input also being the value of every
 * custom property it names: it returns a finite quantity or none, and never throws nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\UxCss\Token\Calc;
use Phil\UxCss\Token\Quantity;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $quantity = Calc::evaluate($input, static fn (string $name): string => $input);

    if ($quantity instanceof Quantity && (is_nan($quantity->value) || 1 !== preg_match('/^(?:[a-z]+|%)?$/D', $quantity->unit))) {
        throw new LogicException('A quantity computed is not a number with a unit.');
    }
});
$config->setMaxLen(1100);
