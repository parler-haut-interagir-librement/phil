<?php

declare(strict_types=1);

/*
 * The rule parser on texts nobody wrote: whatever it reads, it gives a rule whose text reads back to the same rule, or
 * refuses the text with one of the almanac's exceptions, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Almanac\Exception\AlmanacException;
use Phil\Almanac\Rule\RuleParser;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $rule = RuleParser::parse($input);
    } catch (AlmanacException) {
        // A text refused is an answer.
        return;
    }

    if ((string) RuleParser::parse((string) $rule) !== (string) $rule) {
        throw new LogicException(sprintf('"%s" does not read back to itself.', $rule));
    }
});
$config->setMaxLen(256);
