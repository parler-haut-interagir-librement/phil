<?php

declare(strict_types=1);

/*
 * Canada Post's answers as nobody wrote them: whatever JSON comes back, the reader returns quotes, a tracking number,
 * a price, a link or the carrier's messages, or refuses the answer with `CarrierRefused`, and never throws anything
 * else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\CommerceLogisticsBundle\Domain\Exception\CarrierRefused;
use Phil\CommerceLogisticsBundle\Infrastructure\CanadaPost\CanadaPostPayloads;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $answer = json_decode($input, true, 32, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
    } catch (JsonException) {
        // The carrier refuses an answer that is not JSON before reading it.
        return;
    }

    CanadaPostPayloads::messages($answer);

    foreach ([
        static fn (): array => CanadaPostPayloads::quotes($answer),
        static fn (): string => CanadaPostPayloads::tracking($answer),
        static fn (): mixed => CanadaPostPayloads::shipmentPrice($answer),
        static fn (): mixed => CanadaPostPayloads::price($answer),
        static fn (): ?string => CanadaPostPayloads::link($answer, 'label'),
    ] as $read) {
        try {
            $read();
        } catch (CarrierRefused) {
            // An answer refused whole is an answer.
        }
    }
});
$config->setMaxLen(4096);
