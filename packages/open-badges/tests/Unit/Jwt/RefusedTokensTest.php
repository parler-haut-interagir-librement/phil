<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Jwt;

use DateTimeImmutable;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Jwt\Algorithms;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaSigner;
use Phil\OpenBadges\Tests\Unit\Support\Signatures;
use Phil\OpenBadges\Verification\CredentialVerifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every token the verifier refuses: an algorithm the key does not pin, `none`, a confusion of algorithms, an unknown
 * key identifier, a token used before its start or after its end.
 */
#[CoversClass(JwtVerifier::class)]
#[CoversClass(JwtSigner::class)]
#[CoversClass(Algorithms::class)]
final class RefusedTokensTest extends TestCase
{
    private const string ISSUER = 'https://example.org/issuer';

    private const string METHOD = self::ISSUER.'#key-1';

    private static ?RsaSigner $rsa = null;

    private static ?RsaSigner $otherRsa = null;

    /**
     * @return iterable<string, array{string}>
     */
    public static function foreignAlgorithms(): iterable
    {
        yield 'none' => ['none'];
        yield 'an HMAC' => ['HS256'];
        yield 'another asymmetric algorithm' => ['ES256'];
        yield 'a case variant' => ['eddsa'];
        yield 'RSA-PSS' => ['PS256'];
    }

    #[DataProvider('foreignAlgorithms')]
    public function testAnEd25519KeyPinsEdDsa(string $algorithm): void
    {
        $signer = $this->ed25519();
        $signed = $this->part(['alg' => $algorithm]).'.'.$this->part(['iss' => self::ISSUER]);

        $this->assertRefused('The credential is not signed with EdDSA.', $signer->verificationKey(), $signed.'.'.Base64Url::encode(Signatures::sign($signer, $signed)));
    }

    public function testATokenWithoutAlgorithmIsRefused(): void
    {
        $signer = $this->ed25519();
        $signed = $this->part(['typ' => 'vc+jwt']).'.'.$this->part(['iss' => self::ISSUER]);

        $this->assertRefused('The credential is not signed with EdDSA.', $signer->verificationKey(), $signed.'.'.Base64Url::encode(Signatures::sign($signer, $signed)));
    }

    public function testAnUnsignedTokenIsRefused(): void
    {
        $signed = $this->part(['alg' => 'none']).'.'.$this->part(['iss' => self::ISSUER]);

        $this->assertRefused('The credential is not signed with RS256.', $this->rsa()->verificationKey(), $signed.'.');
        $this->assertRefused('The credential is not signed with EdDSA.', $this->ed25519()->verificationKey(), $signed.'.');
    }

    public function testAnRsaPublicKeyIsNeverAnHmacSecret(): void
    {
        $key = $this->rsa()->verificationKey();
        $signed = $this->part(['alg' => 'HS256']).'.'.$this->part(['iss' => self::ISSUER]);
        $publicJwk = json_encode($key->toJwk(), \JSON_THROW_ON_ERROR);

        $this->assertRefused('The credential is not signed with RS256.', $key, $signed.'.'.Base64Url::encode(hash_hmac('sha256', $signed, $publicJwk, true)));
    }

    public function testAnEd25519TokenIsNotVerifiedWithAnRsaKey(): void
    {
        $token = new JwtSigner($this->ed25519())->sign(['issuer' => self::ISSUER]);

        $this->assertRefused('The credential is not signed with RS256.', $this->rsa()->verificationKey(), $token);
    }

    public function testAnRsaTokenIsNotVerifiedWithAnEd25519Key(): void
    {
        $token = new JwtSigner($this->rsa())->sign(['issuer' => self::ISSUER]);

        $this->assertRefused('The credential is not signed with EdDSA.', $this->ed25519()->verificationKey(), $token);
    }

    public function testATokenSignedByAnotherKeyOfTheSameAlgorithmIsRefused(): void
    {
        $rsa = new JwtSigner($this->otherRsa())->sign(['issuer' => self::ISSUER]);
        $ed25519 = new JwtSigner(Ed25519Signer::fromSeed(str_repeat("\2", 32)))->sign(['issuer' => self::ISSUER]);

        $this->assertRefused('The signature of the credential does not verify.', $this->rsa()->verificationKey(), $rsa);
        $this->assertRefused('The signature of the credential does not verify.', $this->ed25519()->verificationKey(), $ed25519);
    }

    public function testAnRsaTokenVerifies(): void
    {
        $token = new JwtSigner($this->rsa(), self::METHOD)->sign(['issuer' => self::ISSUER]);

        self::assertSame(self::ISSUER, new JwtVerifier($this->rsa()->verificationKey())->verify($token)['iss'] ?? null);
        self::assertSame('RS256', JwtVerifier::peek($token)[0]['alg'] ?? null);
    }

    public function testAnEmptySignatureIsRefused(): void
    {
        $token = new JwtSigner($this->ed25519())->sign(['issuer' => self::ISSUER]);

        $this->assertRefused('The signature of the credential does not verify.', $this->ed25519()->verificationKey(), substr($token, 0, (int) strrpos($token, '.') + 1));
    }

    public function testAnUnknownKeyIdentifierIsRefused(): void
    {
        $token = new JwtSigner($this->ed25519(), self::ISSUER.'#key-2')->sign(['issuer' => self::ISSUER]);
        $verifier = new CredentialVerifier(new DidKeyMethods([self::METHOD => $this->ed25519()->verificationKey()]), self::METHOD);

        $this->expectExceptionObject(InvalidCredential::because(\sprintf('The verification method "%s" is not one the verifier knows.', self::ISSUER.'#key-2')));
        $verifier->verify($token);
    }

    public function testTheKeyIdentifierIsAString(): void
    {
        $signer = $this->ed25519();
        $signed = $this->part(['alg' => 'EdDSA', 'kid' => ['https://example.org/issuer#key-1']]).'.'.$this->part(['issuer' => self::ISSUER, 'iss' => self::ISSUER]);
        $verifier = new CredentialVerifier(new DidKeyMethods([self::METHOD => $signer->verificationKey()]));

        $this->expectExceptionObject(InvalidCredential::because('The token names no key, and the verifier has no default one.'));
        $verifier->verify($signed.'.'.Base64Url::encode(Signatures::sign($signer, $signed)));
    }

    public function testTheValidityIsCheckedAtItsBounds(): void
    {
        $signer = $this->ed25519();
        $key = $signer->verificationKey();
        $token = new JwtSigner($signer)->sign(['issuer' => self::ISSUER, 'validFrom' => '2026-11-07T10:00:00Z', 'validUntil' => '2027-11-07T10:00:00Z']);

        $this->assertRefused('The credential is not valid yet.', $key, $token, new DateTimeImmutable('2026-11-07 09:59:59 UTC'));
        self::assertSame(1794045600, new JwtVerifier($key)->verify($token, new DateTimeImmutable('2026-11-07 10:00:00 UTC'))['nbf'] ?? null);
        self::assertSame(1825581600, new JwtVerifier($key)->verify($token, new DateTimeImmutable('2027-11-07 09:59:59 UTC'))['exp'] ?? null);
        $this->assertRefused('The credential has expired.', $key, $token, new DateTimeImmutable('2027-11-07 10:00:00 UTC'));
        self::assertCount(6, new JwtVerifier($key)->verify($token));
    }

    private function assertRefused(string $message, VerificationKey $key, string $token, ?DateTimeImmutable $at = null): void
    {
        try {
            new JwtVerifier($key)->verify($token, $at);
            self::fail(\sprintf('The token was expected to be refused with "%s".', $message));
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame($message, $invalidCredential->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $value
     */
    private function part(array $value): string
    {
        return Base64Url::encode(json_encode($value, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));
    }

    private function ed25519(): Ed25519Signer
    {
        return Ed25519Signer::fromSeed(str_repeat("\1", 32));
    }

    private function rsa(): RsaSigner
    {
        return self::$rsa ??= $this->newRsa();
    }

    private function otherRsa(): RsaSigner
    {
        return self::$otherRsa ??= $this->newRsa();
    }

    private function newRsa(): RsaSigner
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $private);
        self::assertIsString($private);

        return RsaSigner::fromPem($private);
    }
}
