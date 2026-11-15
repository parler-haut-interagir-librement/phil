<?php

declare(strict_types=1);

/*
 * The signed links of the newsletter on tokens nobody wrote: whatever it reads, the verifier gives a link or refuses
 * with LinkRefused, never throws anything else nor warns, and never accepts a token it did not sign.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Symfony\Component\Clock\MockClock;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$links = new SignedLinks('a-key-of-sixteen-bytes-at-least', new MockClock('2026-11-13 10:00:00 UTC'));
$signed = [];

foreach (LinkPurpose::cases() as $purpose) {
    $signed[$links->sign($purpose, 'subscriber', 'extra')] = true;
}

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($links, $signed): void {
    foreach (LinkPurpose::cases() as $purpose) {
        try {
            $links->verify($input, $purpose);
        } catch (LinkRefused) {
            continue;
        }

        if (!isset($signed[$input])) {
            throw new LogicException('A token the newsletter did not sign was accepted.');
        }
    }
});
$config->setMaxLen(600);
