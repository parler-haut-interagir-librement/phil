<?php

declare(strict_types=1);

/*
 * The reader of sealed values, on bytes nobody sealed: whatever the header and the envelope hold, opening throws
 * UnreadableSecret, and nothing else.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\Exception\UnreadableSecret;
use Phil\UserSecretsBundle\Domain\Credential\CredentialKind;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$envelope = new Envelope(new KeyManagementCipher(new SodiumKms(new InMemoryKeyLoader(['k1' => str_repeat('a', 32)]))), 'k1');

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($envelope): void {
    $secret = StoredSecret::seal(Owner::fromString('member-1'), SecretName::fromString('openai'), ServiceName::fromString('openai'), CredentialKind::ApiKey, new SealedSecret('k1', base64_encode($input)), null, new DateTimeImmutable('@1000'));

    try {
        $envelope->open($secret);
    } catch (UnreadableSecret) {
        return;
    }

    throw new LogicException('Bytes nobody sealed opened.');
});
$config->setMaxLen(512);
