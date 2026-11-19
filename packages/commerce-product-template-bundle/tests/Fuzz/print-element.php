<?php

declare(strict_types=1);

/*
 * An element of a print layout read back from JSON nobody wrote. Whatever the bytes, the array reads as an element
 * whose own array reads back equal, or is refused with TemplateRefused; nothing else is thrown.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\PrintElement;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $data = json_decode($input, true, 16, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($data)) {
        return;
    }

    try {
        $element = PrintElement::fromArray($data);
    } catch (TemplateRefused) {
        return;
    }

    if (PrintElement::fromArray($element->toArray()) !== $element) {
        throw new LogicException('An element does not read back equal from its own array.');
    }
});
