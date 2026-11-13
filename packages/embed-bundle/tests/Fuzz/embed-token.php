<?php

declare(strict_types=1);

/*
 * The token decoder on texts nobody signed: whatever it reads, it gives claims or refuses the token, and never throws
 * anything else. A token it accepts is one of its keys, so a forged one that passes is a failure.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\EmbedBundle\Application\EmbedTokenCodec;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbedToken;
use Phil\EmbedBundle\Domain\ValueObject\SigningKey;
use Symfony\Component\Clock\MockClock;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$codec = new EmbedTokenCodec([SigningKey::of('k', str_repeat('s', 32)), SigningKey::of('2026-11', str_repeat('t', 32))], new MockClock('@1000'), true);
/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($codec): void {
    try {
        $codec->decode($input);
    } catch (InvalidEmbedToken) {
        return;
    }

    // Only a token signed by one of the keys reads; the corpus holds none, so a mutation that reads is a forgery.
    throw new LogicException('An unsigned token was accepted: '.$input);
});
$config->setMaxLen(2100);
