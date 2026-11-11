<?php

declare(strict_types=1);

/*
 * The HTML reader on inputs nobody wrote: a reader of a written format never throws, whatever it reads — its inline
 * drawings purified and given to a store that takes them all.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Markup\Contract\ExtractedElementStore;
use Phil\Markup\Reader\Html\HtmlReader;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$reader = new HtmlReader(store: new class implements ExtractedElementStore {
    public function store(string $content, string $mediaType, string $name): string
    {
        return '/media/'.$name;
    }
});
$config->setTarget(static function (string $input) use ($reader): void {
    $reader->read($input);
});
$config->setMaxLen(4096);
