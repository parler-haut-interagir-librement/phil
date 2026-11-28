<?php

declare(strict_types=1);

/*
 * The decoding of plaintexts nobody encoded: each input decodes to safety information that encodes and decodes the
 * same, or is refused as unreadable — never another exception, never a warning.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\ParticipantSafetyInfo\Exception\UnreadableSafetyInfo;
use Phil\ParticipantSafetyInfo\SafetyInfoCodec;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $info = SafetyInfoCodec::decode($input);
    } catch (UnreadableSafetyInfo) {
        return;
    }

    $encoded = SafetyInfoCodec::encode($info);

    if (SafetyInfoCodec::encode(SafetyInfoCodec::decode($encoded)) !== $encoded) {
        throw new LogicException('Decoded safety information does not encode the same twice.');
    }
});
