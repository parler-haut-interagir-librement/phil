<?php

declare(strict_types=1);

/*
 * The reader of load lists, on text nobody wrote: whatever it reads, it returns lines that write back to a text read
 * again as the same lines, or throws InvalidStock, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Stockroom\Exception\InvalidStock;
use Phil\Stockroom\Item;
use Phil\Stockroom\LoadListReader;
use Phil\Stockroom\Memory\InMemoryItems;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$items = new InMemoryItems();

foreach (['foam-sword', 'lantern', 'banner', '12', 'x'] as $code) {
    $items->save(new Item('id-'.$code, $code, ucfirst($code)));
}

$reader = new LoadListReader($items);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $lines = $reader->read($input);
    } catch (InvalidStock) {
        return;
    }

    if (serialize($reader->read($reader->write($lines))) !== serialize($lines)) {
        throw new LogicException('Lines read do not write back to the same lines.');
    }
});
$config->setMaxLen(512);
