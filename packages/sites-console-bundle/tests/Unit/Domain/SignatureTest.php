<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Signature\Base64Url;
use Phil\SitesConsoleBundle\Domain\Signature\Protocol;
use Phil\SitesConsoleBundle\Domain\Signature\SigningKey;
use Phil\SitesConsoleBundle\Domain\Signature\VerifyingKey;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Base64Url::class)]
#[CoversClass(SigningKey::class)]
#[CoversClass(VerifyingKey::class)]
#[CoversClass(Protocol::class)]
#[CoversClass(InvalidValue::class)]
final class SignatureTest extends TestCase
{
    public function testBase64UrlEncodesWithoutPaddingAndReadsKeysInEitherAlphabet(): void
    {
        self::assertSame('-_8', Base64Url::encode("\xfb\xff"));
        self::assertSame("\xfb\xff", Base64Url::decode('-_8'));
        self::assertNull(Base64Url::decode('+/8'));
        self::assertNull(Base64Url::decode('-_8='));
        self::assertNull(Base64Url::decode(''));
        self::assertSame("\xfb\xff", Base64Url::decodeKey(' +/8= '));
        self::assertSame("\xfb\xff", Base64Url::decodeKey('-_8'));
        self::assertNull(Base64Url::decodeKey(' = '));
        self::assertNull(Base64Url::decodeKey('!!'));
    }

    public function testASeedAndItsSecretKeyAreTheSameKey(): void
    {
        $fromSeed = Keys::console();
        $fromSecret = SigningKey::fromBase64($fromSeed->toBase64());

        self::assertSame(86, \strlen($fromSeed->toBase64()));
        self::assertSame($fromSeed->toBase64(), $fromSecret->toBase64());
        self::assertSame('A6EHv_POEL4dcN0Y50vAmWfk1jCbpQ1fHdyGZBJVMbg', $fromSeed->verifyingKey()->toBase64());
        self::assertSame(['secret' => '***'], $fromSeed->__debugInfo());
    }

    public function testASignatureVerifiesWithItsKeyOnlyAndForItsMessageOnly(): void
    {
        $key = Keys::console();
        $signature = $key->sign('message');

        self::assertSame(86, \strlen($signature));
        self::assertTrue($key->verifyingKey()->verifies('message', $signature));
        self::assertFalse($key->verifyingKey()->verifies('messagE', $signature));
        self::assertFalse(Keys::site()->verifyingKey()->verifies('message', $signature));
        self::assertFalse($key->verifyingKey()->verifies('message', substr($signature, 0, -2)));
        self::assertFalse($key->verifyingKey()->verifies('message', '!!'));
    }

    public function testGeneratedKeysDiffer(): void
    {
        $first = SigningKey::generate();

        self::assertNotSame($first->toBase64(), SigningKey::generate()->toBase64());
        self::assertTrue($first->verifyingKey()->verifies('x', $first->sign('x')));
    }

    public function testAPublicKeyReadsBackAndRefusesAnotherLength(): void
    {
        $public = Keys::site()->verifyingKey();

        self::assertSame($public->toBase64(), VerifyingKey::fromBase64($public->toBase64())->toBase64());

        $this->expectExceptionObject(InvalidValue::because('A public key is the base64 of the 32 bytes of an Ed25519 key.'));
        VerifyingKey::fromBase64(Keys::CONSOLE_SEED.'AA');
    }

    public function testAPublicKeyOfOtherBytesIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::because('A public key is the 32 bytes of an Ed25519 key.'));
        VerifyingKey::fromBytes(str_repeat('a', 31));
    }

    public function testAnEmptyPublicKeyIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        VerifyingKey::fromBytes('');
    }

    #[DataProvider('badPrivateKeys')]
    public function testAPrivateKeyOfAnotherLengthIsRefused(string $text): void
    {
        $this->expectExceptionObject(InvalidValue::because('A private key is the base64 of the 32 bytes of an Ed25519 seed or of the 64 bytes of its secret key.'));
        SigningKey::fromBase64($text);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badPrivateKeys(): iterable
    {
        yield 'empty' => [''];
        yield '31 bytes' => [Base64Url::encode(str_repeat('a', 31))];
        yield '33 bytes' => [Base64Url::encode(str_repeat('a', 33))];
        yield '63 bytes' => [Base64Url::encode(str_repeat('a', 63))];
        yield 'not base64' => ['***'];
    }

    public function testTheSignedMessagesAreFixed(): void
    {
        self::assertSame("phil-sites-console/1 request\nshop\nstatus\n1763632800\nNONCE\n".hash('sha256', ''), Protocol::requestMessage('shop', 'status', 1_763_632_800, 'NONCE', ''));
        self::assertSame("phil-sites-console/1 response\nshop\nNONCE\n200\n".hash('sha256', '{}'), Protocol::responseMessage('shop', 'NONCE', 200, '{}'));
        self::assertSame(['status', 'configuration', 'update', 'keys'], Protocol::ACTIONS);
    }

    public function testAnUnknownActionIsNeverSigned(): void
    {
        $this->expectExceptionObject(InvalidValue::because('The action of a request is one of status, configuration, update, keys.'));
        Protocol::requestMessage('shop', 'delete', 1, 'NONCE', '');
    }

    public function testNonces(): void
    {
        $nonce = Protocol::nonce();

        self::assertSame(32, \strlen($nonce));
        self::assertTrue(Protocol::isNonce($nonce));
        self::assertNotSame($nonce, Protocol::nonce());
        self::assertTrue(Protocol::isNonce(str_repeat('a', 22)));
        self::assertTrue(Protocol::isNonce(str_repeat('-', 64)));
        self::assertFalse(Protocol::isNonce(str_repeat('a', 21)));
        self::assertFalse(Protocol::isNonce(str_repeat('a', 65)));
        self::assertFalse(Protocol::isNonce(str_repeat('a', 22).'='));
        self::assertFalse(Protocol::isNonce(str_repeat('a', 22)."\n"));
        self::assertFalse(Protocol::isNonce('x'.str_repeat('a', 22).'/'));
    }
}
