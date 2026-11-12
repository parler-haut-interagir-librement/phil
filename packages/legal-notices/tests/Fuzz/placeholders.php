<?php

declare(strict_types=1);

/*
 * The placeholders on bodies nobody wrote: the plain text renderer and the filling of the placeholders return a string
 * for any input, never throw nor warn, and every value filled in comes out escaped.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\LegalNotices\Placeholders;
use Phil\LegalNotices\PlainTextBody;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$body = new PlainTextBody();
$placeholders = new Placeholders(['publisher_name' => '<b>"A" & B</b>', 'retention.R-1' => "90 days\nthen erased"]);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($body, $placeholders): void {
    $html = $placeholders->fill($body->toHtml('text', $input));
    Placeholders::names($input);
    $placeholders->unknown($html);

    if (str_contains($html, '<b>"A"')) {
        throw new LogicException('A value was filled in without being escaped.');
    }
});
$config->setMaxLen(4096);
