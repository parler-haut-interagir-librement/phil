<?php

declare(strict_types=1);

namespace Phil\Lti\Tests\Unit\Key;

use Phil\Lti\Exception\InvalidToken;
use Phil\Lti\Key\InMemoryKeyStore;
use Phil\Lti\Key\KeySet;
use Phil\Lti\Key\RsaPublicKey;
use Phil\Lti\Tests\Fixture\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KeySet::class)]
#[CoversClass(InMemoryKeyStore::class)]
final class KeySetTest extends TestCase
{
    public function testASetIsWrittenAndReadBack(): void
    {
        $set = new KeySet([Keys::platform()->publicKey, Keys::platformNext()->publicKey]);
        $json = $set->toJson();

        self::assertStringStartsWith('{"keys":[{"kty":"RSA","alg":"RS256","use":"sig","kid":"platform-1"', $json);
        self::assertStringNotContainsString('\/', $json);
        self::assertSame($set->toArray(), KeySet::fromJson($json)->toArray());
        self::assertSame('platform-2', KeySet::fromJson($json)->find('platform-2')?->keyId);
        self::assertNull(KeySet::fromJson($json)->find('other'));
        self::assertNull(KeySet::fromJson($json)->find(null));
        self::assertSame('platform-1', new KeySet([Keys::platform()->publicKey])->find(null)?->keyId);
        self::assertNull(new KeySet([])->find(null));
    }

    public function testKeysForOtherUsesAreSkipped(): void
    {
        $jwk = Keys::platform()->publicKey->toJwk();
        $json = json_encode(['keys' => [
            ['kty' => 'EC', 'crv' => 'P-256', 'x' => 'a', 'y' => 'b'],
            ['use' => 'enc'] + $jwk,
            ['alg' => 'RS512'] + $jwk,
            'not a key',
            $jwk,
        ]], \JSON_THROW_ON_ERROR);

        self::assertCount(1, KeySet::fromJson($json)->keys);
    }

    public function testAKeyWithoutAlgorithmOrUseIsRead(): void
    {
        $jwk = Keys::platform()->publicKey->toJwk();
        unset($jwk['alg'], $jwk['use']);

        self::assertCount(1, KeySet::fromJson(json_encode(['keys' => [$jwk]], \JSON_THROW_ON_ERROR))->keys);
    }

    public function testTheCurrentKeyIsPublishedBeforeTheRetiredOnes(): void
    {
        $store = new InMemoryKeyStore(Keys::platformNext(), [Keys::platform()->publicKey]);

        self::assertSame('platform-2', $store->signingKey()->keyId());
        self::assertSame(['platform-2', 'platform-1'], array_map(static fn (RsaPublicKey $key): ?string => $key->keyId, $store->publicKeys()->keys));
        self::assertSame(['platform-2'], array_map(static fn (RsaPublicKey $key): ?string => $key->keyId, new InMemoryKeyStore(Keys::platformNext())->publicKeys()->keys));
    }

    public function testMalformedSetsAreRefused(): void
    {
        $jwk = Keys::platform()->publicKey->toJwk();
        $cases = [
            'not json' => ['{', 'The key set is not JSON.'],
            'no keys' => ['{}', 'A key set is an object whose "keys" is a list.'],
            'keys object' => ['{"keys":{"a":1}}', 'A key set is an object whose "keys" is a list.'],
            'a list' => ['[1]', 'A key set is an object whose "keys" is a list.'],
            'too deep' => ['{"keys":[[[[[[[[1]]]]]]]]}', 'The key set is not JSON.'],
            'too large' => [str_repeat(' ', KeySet::MAX_BYTES + 1), 'A key set is 262144 bytes at most.'],
            'too many' => [json_encode(['keys' => array_fill(0, KeySet::MAX_KEYS + 1, $jwk)], \JSON_THROW_ON_ERROR), 'A key set holds 32 keys at most.'],
        ];

        foreach ($cases as $name => [$json, $message]) {
            try {
                KeySet::fromJson($json);
                self::fail($name.' was read.');
            } catch (InvalidToken $invalidToken) {
                self::assertSame($message, $invalidToken->getMessage(), $name);
            }
        }

        self::assertCount(KeySet::MAX_KEYS, KeySet::fromJson(json_encode(['keys' => array_fill(0, KeySet::MAX_KEYS, $jwk)], \JSON_THROW_ON_ERROR))->keys);
        self::assertCount(0, KeySet::fromJson('{"keys":[[[[[[1]]]]]]}'.str_repeat(' ', KeySet::MAX_BYTES - 22))->keys);

        $this->expectExceptionMessageIsOrContains('A key set holds 32 keys at most.');
        new KeySet(array_fill(0, KeySet::MAX_KEYS + 1, Keys::platform()->publicKey));
    }
}
