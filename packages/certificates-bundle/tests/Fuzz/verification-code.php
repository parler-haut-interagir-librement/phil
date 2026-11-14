<?php

declare(strict_types=1);

/*
 * The reader of verification codes and of the names of status lists, on text nobody wrote: a code it reads writes back
 * as its formatted self, which reads back to the same code; a list name it places gives a range of the layout's size
 * whose first index the layout locates in that very list; anything else throws InvalidValue, or gives no range.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CertificatesBundle\Application\StatusListLayout;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$layout = new StatusListLayout('certificates', 1024);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($layout): void {
    try {
        $code = VerificationCode::fromString($input);

        if (1 !== preg_match('/^'.VerificationCode::PATTERN.'$/D', $code->value) || VerificationCode::fromString($code->formatted())->value !== $code->value) {
            throw new LogicException('A verification code read does not write back to itself.');
        }
    } catch (InvalidValue) {
    }

    $range = $layout->range($input);

    if (null !== $range && ($range[1] - $range[0] !== $layout->size || $layout->locate($range[0]) !== [$input, 0])) {
        throw new LogicException('A list placed by the layout does not hold the indexes it gives.');
    }
});
$config->setMaxLen(64);
