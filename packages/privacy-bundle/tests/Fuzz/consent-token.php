<?php

declare(strict_types=1);

/*
 * The reader of the consent cookie on values nobody wrote: whatever the browser sends, it reads a choice or nothing, and
 * never throws nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\PrivacyBundle\Domain\Consent\ConsentToken;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$token = new ConsentToken('fuzz');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($token): void {
    $token->decode($input);

    // Signed, so that the reading of the payload is reached as well.
    $payload = rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    $key = hash_hmac('sha256', 'phil_privacy.consent', 'fuzz', true);
    $token->decode($payload.'.'.rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $key, true)), '+/', '-_'), '='));
});
