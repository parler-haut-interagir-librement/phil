<?php

declare(strict_types=1);

/*
 * The reader of the profiles of other issuers, on bodies nobody wrote: the key of a method is either found — an
 * Ed25519 or an RSA key — or refused with InvalidCredential; nothing else escapes, and nothing is read but the one
 * profile.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\RsaKey;
use Phil\OpenBadgesBundle\Infrastructure\Verification\RemoteVerificationMethods;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $client = new MockHttpClient(static function (string $method, string $url) use ($input): MockResponse {
        if ('https://partner.example/issuer' !== $url) {
            throw new LogicException('Only the profile of the issuer is read.');
        }

        return new MockResponse($input, ['response_headers' => ['Content-Type' => 'application/ld+json']]);
    });
    $methods = new RemoteVerificationMethods(new DidKeyMethods(), new SafeHttpClient($client, resolve: ['partner.example' => '93.184.215.14']), new NullAdapter());

    try {
        $key = $methods->key('https://partner.example/issuer#key-1');

        if (!$key instanceof Ed25519Key && !$key instanceof RsaKey) {
            throw new LogicException('A key found is an Ed25519 or an RSA key.');
        }
    } catch (InvalidCredential) {
    }
});
$config->setMaxLen(4096);
