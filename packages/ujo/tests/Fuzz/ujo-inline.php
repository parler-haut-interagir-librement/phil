<?php

declare(strict_types=1);

/*
 * The inline sanitizer on inputs nobody wrote, under a ceiling of 1 KiB, a budget of 64 tags and comments and a depth
 * of 16: a fragment within them is sanitized and converted to text by every profile without an exception, and its
 * output passes the sanitizer again unchanged; a longer one is refused by `InputTooLarge`, one past a budget by
 * `InputTooComplex`, and by nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Ujo\Exception\InputTooComplex;
use Phil\Ujo\Exception\InputTooLarge;
use Phil\Ujo\Html\InlineSanitizer;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$sanitizer = new InlineSanitizer(maxBytes: 1024, maxTokens: 64, maxDepth: 16);
// Its output closes what was left open: the end tags added are read again within a budget that holds them.
$again = new InlineSanitizer(maxTokens: 64 * 2, maxDepth: 16);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($sanitizer, $again): void {
    if (strlen($input) > 1024) {
        try {
            $sanitizer->sanitize($input);
        } catch (InputTooLarge) {
            return;
        }

        throw new LogicException('A fragment above the ceiling was read.');
    }

    try {
        $sanitizer->toText($input);

        foreach (['rich', 'plain', 'mathml', 'raw'] as $profile) {
            $clean = $sanitizer->sanitize($input, $profile);

            if ($again->sanitize($clean, $profile) !== $clean) {
                throw new LogicException(sprintf('The %s profile changes its own output.', $profile));
            }
        }
    } catch (InputTooComplex $inputTooComplex) {
        if (!in_array([$inputTooComplex->budget, $inputTooComplex->limit], [['tokens', 64], ['depth', 16]], true)) {
            throw new LogicException('Only the budgets of the first reading are spent.', 0, $inputTooComplex);
        }
    }
});
$config->setMaxLen(2048);
