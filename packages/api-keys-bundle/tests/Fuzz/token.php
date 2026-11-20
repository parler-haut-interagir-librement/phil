<?php

declare(strict_types=1);

/*
 * The reader of tokens, on text nobody wrote: whatever it reads, it returns a token that writes back to the same text,
 * or throws MalformedToken, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ApiKeysBundle\Domain\Exception\MalformedToken;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$format = new TokenFormat('acme');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($format): void {
    try {
        $token = $format->parse($input);
    } catch (MalformedToken) {
        return;
    }

    if ($format->format($token->id, $token->secret) !== $input) {
        throw new LogicException('A token read does not write back to the same text.');
    }
});
$config->setMaxLen(128);
