<?php

declare(strict_types=1);

/*
 * The identifier codec on identifiers nobody wrote: whatever it reads, it decodes to non-negative integers or to
 * nothing, never throws nor warns; a canonical identifier writes back to itself, and the numbers it decodes to write
 * an identifier that decodes to them again.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ShortLinkBundle\Application\Code\SqidCodec;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$codecs = [new SqidCodec(), new SqidCodec(minLength: 8, blocklist: ['abc', 'h4ck'])];

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($codecs): void {
    foreach ($codecs as $codec) {
        $numbers = $codec->decode($input);

        foreach ($numbers as $number) {
            if ($number < 0) {
                throw new LogicException('An identifier decoded to a negative number.');
            }
        }

        if ([] === $numbers) {
            continue;
        }

        if ($codec->decode($codec->encode($numbers)) !== $numbers) {
            throw new LogicException('The numbers of an identifier do not come back from their own identifier.');
        }

        if ($codec->isCanonical($input) && $codec->encode($numbers) !== $input) {
            throw new LogicException('A canonical identifier does not write back to itself.');
        }
    }
});
$config->setMaxLen(300);
