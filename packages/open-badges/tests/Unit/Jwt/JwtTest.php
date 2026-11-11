<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Jwt;

use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Key\Ed25519Signer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JwtSigner::class)]
#[CoversClass(JwtVerifier::class)]
final class JwtTest extends TestCase
{
    public function testTheHeaderNamesTheKey(): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        [$header] = JwtVerifier::peek(new JwtSigner($signer, 'https://example.org/issuer#key-1')->sign(['id' => 'urn:x']));

        self::assertSame(['alg' => 'EdDSA', 'typ' => 'vc+jwt', 'kid' => 'https://example.org/issuer#key-1', 'jwk' => $signer->publicJwk()], $header);
    }

    public function testTheClaimsMirrorTheCredential(): void
    {
        $token = new JwtSigner(Ed25519Signer::fromSeed(str_repeat("\1", 32)))->sign([
            'id' => 'urn:x',
            'issuer' => 'https://example.org/issuer',
            'validFrom' => '2026-11-07T10:00:00.250+00:00',
            'validUntil' => '2027-11-07T10:00:00-05:00',
            'credentialSubject' => 'did:example:ada',
        ]);
        [, $claims] = JwtVerifier::peek($token);

        self::assertSame('https://example.org/issuer', $claims['iss'] ?? null);
        self::assertSame('urn:x', $claims['jti'] ?? null);
        self::assertArrayNotHasKey('sub', $claims);
        self::assertSame(1794045600, $claims['nbf'] ?? null);
        self::assertSame(1825599600, $claims['exp'] ?? null);

        [, $bare] = JwtVerifier::peek(new JwtSigner(Ed25519Signer::fromSeed(str_repeat("\1", 32)))->sign(['issuer' => ['name' => 'x'], 'credentialSubject' => ['name' => 'y'], 'validFrom' => 3]));
        self::assertSame(['issuer' => ['name' => 'x'], 'credentialSubject' => ['name' => 'y'], 'validFrom' => 3], $bare);
    }

    public function testAMomentIsOfRfc3339(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"2026-11-07" is not a moment of RFC 3339.'));
        new JwtSigner(Ed25519Signer::fromSeed(str_repeat("\1", 32)))->sign(['validFrom' => '2026-11-07']);
    }

    public function testATokenHasNoEmbeddedProof(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('A credential secured as a JSON Web Token has no embedded proof.'));
        new JwtSigner(Ed25519Signer::fromSeed(str_repeat("\1", 32)))->sign(['proof' => null]);
    }

    public function testATokenIsReadWithinItsLength(): void
    {
        $long = 'a.'.str_repeat('b', JwtVerifier::MAX_LENGTH - 3).'.';
        self::assertSame(JwtVerifier::MAX_LENGTH, \strlen($long));

        try {
            JwtVerifier::peek($long.'c');
            self::fail('A token too long was expected to be refused.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame('A credential token has three parts separated by dots.', $invalidCredential->getMessage());
        }

        try {
            new JwtVerifier(Ed25519Signer::fromSeed(str_repeat("\1", 32))->verificationKey())->verify($long.'c');
            self::fail('A token too long was expected to be refused.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame('A credential token has three parts separated by dots.', $invalidCredential->getMessage());
        }

        $this->expectExceptionObject(InvalidCredential::because('A part of the credential token is not JSON.'));
        JwtVerifier::peek(Base64Url::encode('{}').'.'.str_repeat('A', JwtVerifier::MAX_LENGTH - 5).'.');
    }

    public function testAVerifiedTokenIsReadWithinItsLength(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('A part of the token is not URL-safe base 64.'));
        new JwtVerifier(Ed25519Signer::fromSeed(str_repeat("\1", 32))->verificationKey())->verify('a.'.str_repeat('b', JwtVerifier::MAX_LENGTH - 3).'.');
    }

    public function testPeekNeedsThreeParts(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('A credential token has three parts separated by dots.'));
        JwtVerifier::peek('a.b');
    }
}
