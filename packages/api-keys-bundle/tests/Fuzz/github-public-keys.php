<?php

declare(strict_types=1);

/*
 * The reader of the document GitHub publishes its keys in, on text nobody wrote: whatever it reads, it returns at most
 * 32 keys within bounds, each one OpenSSL reads as a public key, or throws UnreadableReport, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception\UnreadableReport;
use Phil\ApiKeysBundle\Infrastructure\SecretScanning\GitHub\GitHubPublicKeys;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    try {
        $keys = GitHubPublicKeys::parse($input);
    } catch (UnreadableReport) {
        return;
    }

    if (count($keys) > GitHubPublicKeys::MAX_KEYS) {
        throw new LogicException('The document gives more keys than its bound.');
    }

    foreach ($keys as $identifier => $pem) {
        if (strlen((string) $identifier) > GitHubPublicKeys::MAX_IDENTIFIER_LENGTH || !openssl_pkey_get_public($pem) instanceof OpenSSLAsymmetricKey) {
            throw new LogicException('A key passes its bounds or does not read.');
        }
    }
});
$config->setMaxLen(4096);
