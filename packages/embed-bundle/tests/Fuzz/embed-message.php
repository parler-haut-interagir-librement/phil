<?php

declare(strict_types=1);

/*
 * The frame protocol's reference on texts nobody wrote: whatever it reads, it gives a message or refuses it, and never
 * throws anything else; a message it gives writes back to a text it reads again as the same message.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\EmbedBundle\Domain\Exception\InvalidEmbedMessage;
use Phil\EmbedBundle\Domain\ValueObject\EmbedMessage;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $message = EmbedMessage::fromJson($input);
    } catch (InvalidEmbedMessage) {
        return;
    }

    $again = EmbedMessage::fromJson(json_encode($message->toArray(), \JSON_THROW_ON_ERROR));

    if ($again->toArray() !== $message->toArray()) {
        throw new LogicException('A message does not read back as itself: '.$input);
    }
});
$config->setMaxLen(1100);
