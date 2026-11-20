<?php

declare(strict_types=1);

/*
 * The dice of a draw nobody wrote: the first byte gives the number of dice, each of the next bytes the faces of one,
 * and the rest is the draw. Whatever the bytes, a draw reads as one result per die, each from 1 to its faces, written
 * back one character per die and read back equal, or is refused with InvalidValue; nothing else is thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceProductTemplateBundle\Domain\ValueObject\DiceCode;
use Phil\DddBundle\Domain\Exception\InvalidValue;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    if ('' === $input) {
        return;
    }

    $dice = 1 + ord($input[0]) % 8;
    $sides = array_map(static fn (string $byte): int => 2 + ord($byte[0]) % 19, str_split(str_pad(substr($input, 1, $dice), $dice, "\x04")));
    $draw = substr($input, 1 + $dice);

    try {
        $code = DiceCode::read($draw, $sides);
    } catch (InvalidValue) {
        return;
    }

    if (count($code->results) !== $dice || strlen($code->code()) !== $dice) {
        throw new LogicException('A draw read as another number of dice.');
    }

    foreach ($code->results as $index => $result) {
        if ($result > $sides[$index]) {
            throw new LogicException('A result goes past its die.');
        }
    }

    if (DiceCode::read($code->code(), $sides)->results !== $code->results) {
        throw new LogicException('The dice written one character each do not read back equal.');
    }
});
