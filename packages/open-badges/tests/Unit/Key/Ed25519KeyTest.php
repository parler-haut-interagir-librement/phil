<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Key;

use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Encoding\Multibase;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Tests\Unit\Support\Signatures;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ed25519Key::class)]
#[CoversClass(Ed25519Signer::class)]
final class Ed25519KeyTest extends TestCase
{
    /**
     * The key pair of the test vectors of the Data Integrity EdDSA cryptosuites, published with them: test key, public,
     * never trust it. The tokens of `tests/Fixtures` are signed with it, so it stays the same.
     */
    public const string PUBLIC = 'z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2';

    public const string SECRET = 'z3u2en7t5LR2WtQH5PfFqMqwVHBeXouLzo6haApm8XHqvjxq';

    public function testAKeyIsAMultikey(): void
    {
        $signer = Ed25519Signer::fromSeed(substr(Multibase::decode(self::SECRET), 2));
        $key = $signer->verificationKey();

        self::assertSame(self::PUBLIC, $key->toMultibase());
        self::assertSame($key->publicKey, Ed25519Key::fromMultibase(self::PUBLIC)->publicKey);
        self::assertSame($key->toJwk(), $signer->publicJwk());
        self::assertSame($key->publicKey, Ed25519Key::fromJwk($key->toJwk())->publicKey);
        self::assertTrue(Signatures::verifies(Ed25519Key::fromMultibase(self::PUBLIC), 'data', Signatures::sign($signer, 'data')));
        self::assertFalse(Signatures::verifies(Ed25519Key::fromMultibase(self::PUBLIC), 'other', Signatures::sign($signer, 'data')));
        $private = $signer->jwk()->all();
        ksort($private);
        self::assertSame(['crv' => 'Ed25519', 'd' => Base64Url::encode(substr(Multibase::decode(self::SECRET), 2)), 'kty' => 'OKP', 'x' => $key->toJwk()['x']], $private);
        self::assertSame($key->toJwk(), $key->jwk()->all());
        self::assertSame('EdDSA', $signer->signatureAlgorithm()->name());
    }

    public function testAMultikeyIsInBase58(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('An Ed25519 Multikey is encoded in base 58, starting with "z".'));
        Ed25519Key::fromMultibase('u7QE');
    }

    public function testAMultikeyIsAnEd25519Key(): void
    {
        $key = str_repeat("\1", 32);

        foreach (["\xec\x01".$key, "\xed\x02".$key, "\xed\x01".$key."\0", "\xed\x01".substr($key, 1), "\x00\xed\x01".$key] as $bytes) {
            try {
                Ed25519Key::fromMultibase(Multibase::base58($bytes));
                self::fail('The Multikey was expected to be refused.');
            } catch (InvalidCredential $refused) {
                self::assertSame('An Ed25519 Multikey is the prefix 0xed01 and 32 bytes.', $refused->getMessage());
            }
        }
    }
}
