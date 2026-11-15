<?php

declare(strict_types=1);

/*
 * The claims of a launch and the content items of a deep linking response, on JSON nobody wrote: `LaunchMessage`
 * returns a message that writes back to claims it reads again the same, or throws InvalidToken; the content items are
 * read or refused with InvalidToken or InvalidData. Nothing else escapes.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\Lti\DeepLinking\ContentItems;
use Phil\Lti\Exception\InvalidData;
use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Message\Claim;
use Phil\Lti\Message\LaunchMessage;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $claims = json_decode($input, true, 32, \JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return;
    }

    if (!is_array($claims)) {
        return;
    }

    try {
        $message = LaunchMessage::fromClaims($claims);

        if (LaunchMessage::fromClaims($message->toClaims())->toClaims() !== $message->toClaims()) {
            throw new LogicException('A message does not read back the claims it writes.');
        }
    } catch (InvalidToken|InvalidData) {
    }

    try {
        ContentItems::fromClaims($claims, Claim::CONTENT_ITEMS);
    } catch (InvalidToken|InvalidData) {
    }
});
$config->setMaxLen(8192);
