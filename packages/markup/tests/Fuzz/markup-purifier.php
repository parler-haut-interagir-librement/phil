<?php

declare(strict_types=1);

/*
 * The purifiers on inputs nobody wrote, at each level, under a ceiling of 2 KiB: an input above it is refused whole by
 * `InputTooLarge`; below it neither throws, and what they keep never holds an event handler, a style or an address
 * that runs code, nor purified again keeps other elements. The ammonia purifier runs
 * beside the sanitizer where its extension is loaded, as in the bench-ext image of phil/dev-tools.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Dom\HTMLDocument;
use Phil\Markup\Adapter\AmmoniaPurifier;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Exception\InputTooLarge;
use Phil\Markup\Level;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$ceiling = 2048;
$purifiers = [SanitizerPurifier::defaultPolicies(maxBytes: $ceiling)];

if (AmmoniaPurifier::isAvailable()) {
    $purifiers[] = AmmoniaPurifier::defaultPolicies(minimumBytes: 0, maxBytes: $ceiling);
}

$elements = static function (string $html): array {
    $body = HTMLDocument::createFromString('<!doctype html><html><body>'.$html, \LIBXML_NOERROR)->body;
    $found = [];

    foreach ($body?->getElementsByTagName('*') ?? [] as $element) {
        foreach ($element->attributes as $attribute) {
            $name = strtolower($attribute->localName);
            $value = (string) preg_replace('/[\x00-\x20]+/', '', $attribute->value);

            if (str_starts_with($name, 'on') || 'style' === $name || 1 === preg_match('/^(?:javascript|vbscript|data):/i', $value)) {
                throw new LogicException(sprintf('The attribute "%s" was kept: %s', $name, $html));
            }
        }

        $found[] = strtolower($element->localName);
    }

    return $found;
};

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($purifiers, $elements, $ceiling): void {
    foreach ($purifiers as $purifier) {
        foreach (Level::cases() as $level) {
            if (strlen($input) > $ceiling) {
                try {
                    $purifier->purify($input, $level);
                } catch (InputTooLarge $inputTooLarge) {
                    if ($inputTooLarge->bytes !== strlen($input) || $inputTooLarge->limit !== $ceiling) {
                        throw new LogicException(sprintf('The refusal says %d bytes above %d.', $inputTooLarge->bytes, $inputTooLarge->limit), $inputTooLarge->getCode(), $inputTooLarge);
                    }

                    continue;
                }

                throw new LogicException(sprintf('An input of %d bytes was purified above the ceiling of %d.', strlen($input), $ceiling));
            }

            $once = $purifier->purify($input, $level);

            // Escaping may make the output longer than the ceiling: it is then checked only once.
            if (strlen($once) <= $ceiling && $elements($once) !== $elements($purifier->purify($once, $level))) {
                throw new LogicException(sprintf('Purified again, the HTML keeps other elements: %s', $once));
            }

            $elements($once);
        }
    }
});
$config->setMaxLen(4096);
