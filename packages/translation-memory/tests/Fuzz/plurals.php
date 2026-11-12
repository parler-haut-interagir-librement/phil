<?php

declare(strict_types=1);

/*
 * The plural check on messages nobody wrote: whatever the source and the target, it returns issues and never throws
 * nor warns, however the braces nest.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\TranslationMemory\Check\Checked;
use Phil\TranslationMemory\Check\Plurals;
use Phil\TranslationMemory\LanguageTag;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$check = new Plurals();
$source = LanguageTag::of('en');
$target = LanguageTag::of('pl');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($check, $source, $target): void {
    $check->check(new Checked('{n, plural, one {#} other {#}} {g, select, a {{n, plural, other {x}}} other {y}}', $input, $source, $target));
    $check->check(new Checked($input, '{n, plural, one {#} other {#}}', $source, $target));
});
$config->setMaxLen(4096);
