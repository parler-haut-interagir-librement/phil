<?php

declare(strict_types=1);

/*
 * The reading of claims nobody wrote, decoded from JSON: as a security event token, the token or InvalidSecurityEventToken,
 * within its bounds; as the claims of a Google ID token, an identity or Refused, its subject a printable string of at
 * most 255 bytes and its address trusted only on Google's authority. Never another exception.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderGoogleBundle\Application\GoogleClaims;
use Phil\IdentityProviderGoogleBundle\Application\GoogleSettings;
use Phil\IdentityProviderGoogleBundle\Domain\Exception\InvalidSecurityEventToken;
use Phil\IdentityProviderGoogleBundle\Domain\Risc\SecurityEventToken;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$open = new GoogleClaims(new GoogleSettings('client', requestProfile: true));
$workspace = new GoogleClaims(new GoogleSettings('client', ['example.com']));

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($open, $workspace): void {
    $claims = json_decode($input, true, 32);

    if (!is_array($claims)) {
        return;
    }

    try {
        $token = SecurityEventToken::fromClaims($claims);

        if (count($token->events) > SecurityEventToken::MAX_EVENTS || [] === $token->audiences || '' === $token->id) {
            throw new LogicException('A security event token was read out of its bounds.');
        }
    } catch (InvalidSecurityEventToken) {
    }

    foreach ([$open, $workspace] as $mapper) {
        try {
            $identity = $mapper->identity($claims);
        } catch (Refused) {
            continue;
        }

        if (1 !== preg_match('/^[\x21-\x7e]{1,255}$/D', $identity->subject)) {
            throw new LogicException('An identity was read with a subject out of its bounds.');
        }

        $domain = null === $identity->email ? '' : strtolower(substr($identity->email, (int) strrpos($identity->email, '@') + 1));
        $hostedDomain = is_string($claims['hd'] ?? null) ? strtolower($claims['hd']) : '';

        if ($identity->emailVerified && 'gmail.com' !== $domain && $hostedDomain !== $domain) {
            throw new LogicException('An address was trusted without Google being its authority.');
        }
    }
});
$config->setMaxLen(8192);
