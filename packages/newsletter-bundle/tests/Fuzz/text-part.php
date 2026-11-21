<?php

declare(strict_types=1);

/*
 * The text part of a message drawn from HTML nobody wrote: whatever it reads, the converter gives a string without
 * tags, never throws nor warns, and folds its blank lines.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\NewsletterBundle\Application\Content\TextPart;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$converter = new TextPart();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($converter): void {
    $text = $converter->convert($input, 'UTF-8');

    if (str_contains($text, "\n\n\n") || $text !== trim($text)) {
        throw new LogicException('The text part keeps runs of blank lines or the whitespace around it.');
    }
});
$config->setMaxLen(2000);
