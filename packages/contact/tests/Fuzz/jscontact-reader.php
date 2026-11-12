<?php

declare(strict_types=1);

/*
 * The JSContact reader on texts nobody wrote: whatever it reads, it gives a card whose JSContact reads back to the
 * same JSContact, or refuses the text with one of the library's exceptions, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Contact\Exception\ContactException;
use Phil\Contact\JSContactReader;
use Phil\Contact\JSContactWriter;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new JSContactReader(maxBytes: 4_096, region: 'CA');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        $json = JSContactWriter::toJson($reader->read($input));
    } catch (ContactException) {
        // A text refused is an answer.
        return;
    }

    if (JSContactWriter::toJson($reader->read($json)) !== $json) {
        throw new LogicException(sprintf('"%s" does not read back to itself.', $json));
    }
});
$config->setMaxLen(4_096);
