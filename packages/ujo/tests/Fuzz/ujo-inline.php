<?php

declare(strict_types=1);

/*
 * The inline sanitizer on inputs nobody wrote, under a ceiling of 1 KiB: a fragment up to the ceiling is sanitized
 * and converted to text by every profile without an exception, and its output passes the sanitizer again unchanged;
 * a longer one is refused by `InputTooLarge`, and by nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Ujo\Exception\InputTooLarge;
use Phil\Ujo\Html\InlineSanitizer;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$sanitizer = new InlineSanitizer(maxBytes: 1024);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($sanitizer): void {
    if (strlen($input) > 1024) {
        try {
            $sanitizer->sanitize($input);
        } catch (InputTooLarge) {
            return;
        }

        throw new LogicException('A fragment above the ceiling was read.');
    }

    $sanitizer->toText($input);

    foreach (['rich', 'plain', 'mathml', 'raw'] as $profile) {
        $clean = $sanitizer->sanitize($input, $profile);

        if (strlen($clean) <= 1024 && $sanitizer->sanitize($clean, $profile) !== $clean) {
            throw new LogicException(sprintf('The %s profile changes its own output.', $profile));
        }
    }
});
$config->setMaxLen(2048);
