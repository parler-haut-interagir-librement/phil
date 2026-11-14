<?php

declare(strict_types=1);

/*
 * The opening of sealed credentials on values nobody wrote: whatever a row of the application's table holds, it opens
 * as credentials or is refused with UnexpectedValueException or InvalidValue, never another error nor a warning. Each
 * input is also sealed as the plain text of a value, so that the reading of what a valid seal holds is reached.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyDatabaseBundle\Infrastructure\Credentials\CredentialSeal;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$key = str_repeat('k', \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);
$seal = new CredentialSeal($key);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($seal, $key): void {
    $nonce = str_repeat("\0", \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $sealed = CredentialSeal::PREFIX.sodium_bin2base64($nonce.sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($input, CredentialSeal::PREFIX.'tenant_fuzz', $nonce, $key), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

    foreach ([$input, CredentialSeal::PREFIX.$input, $sealed] as $value) {
        try {
            $seal->open('tenant_fuzz', $value);
        } catch (UnexpectedValueException|InvalidValue) {
        }
    }
});
