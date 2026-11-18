<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Jwt;

use DateTimeImmutable;
use Phil\OpenBadges\Encoding\Multibase;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Jwt\Algorithms;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaKey;
use Phil\OpenBadges\Tests\Unit\Key\Ed25519KeyTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tokens issued by version 0.1 of the library, kept in `tests/Fixtures`: an `EdDSA` token signed with the key pair of
 * the Data Integrity EdDSA test vectors, and an `RS256` token with its public key. They verify, and the `EdDSA` one is
 * signed again byte for byte, since Ed25519 signatures are deterministic.
 */
#[CoversClass(JwtSigner::class)]
#[CoversClass(JwtVerifier::class)]
#[CoversClass(Algorithms::class)]
final class PublishedTokensTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../../Fixtures/';

    public function testAnEdDsaTokenOfVersion01Verifies(): void
    {
        $key = Ed25519Key::fromMultibase(Ed25519KeyTest::PUBLIC);
        $claims = new JwtVerifier($key)->verify($this->fixture('ed25519-0.1.jwt'), new DateTimeImmutable('2026-11-15T00:00:00Z'));

        self::assertSame($this->credential(), array_diff_key($claims, array_flip(['iss', 'jti', 'sub', 'nbf', 'exp'])));
        self::assertSame(['https://example.org/issuer', 'urn:uuid:3f6b0a52-6a1e-4d55-9a7e-0c2f0f6c1a11', 'did:example:ada', 1793491200, 2109110400], [$claims['iss'], $claims['jti'], $claims['sub'], $claims['nbf'], $claims['exp']]);
    }

    public function testAnEdDsaTokenIsSignedAsVersion01SignedIt(): void
    {
        $signer = Ed25519Signer::fromSeed(substr(Multibase::decode(Ed25519KeyTest::SECRET), 2));

        self::assertSame($this->fixture('ed25519-0.1.jwt'), new JwtSigner($signer, 'https://example.org/issuer#key-1')->sign($this->credential()));
    }

    public function testAnRs256TokenOfVersion01Verifies(): void
    {
        $key = RsaKey::fromPem($this->fixture('rs256-0.1.pub.pem'));
        $claims = new JwtVerifier($key)->verify($this->fixture('rs256-0.1.jwt'), new DateTimeImmutable('2026-11-15T00:00:00Z'));

        self::assertSame($this->credential(), array_diff_key($claims, array_flip(['iss', 'jti', 'sub', 'nbf', 'exp'])));
        self::assertSame('https://example.org/issuer#key-2', JwtVerifier::peek($this->fixture('rs256-0.1.jwt'))[0]['kid'] ?? null);
        self::assertSame($key->toJwk(), JwtVerifier::peek($this->fixture('rs256-0.1.jwt'))[0]['jwk'] ?? null);
    }

    public function testATokenOfVersion01DoesNotVerifyWithTheOtherKey(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The credential is not signed with EdDSA.'));
        new JwtVerifier(Ed25519Key::fromMultibase(Ed25519KeyTest::PUBLIC))->verify($this->fixture('rs256-0.1.jwt'));
    }

    public function testATamperedTokenOfVersion01DoesNotVerify(): void
    {
        [$header, , $signature] = explode('.', $this->fixture('ed25519-0.1.jwt'));
        [, $payload] = explode('.', new JwtSigner(Ed25519Signer::fromSeed(str_repeat("\1", 32)))->sign([...$this->credential(), 'id' => 'urn:x']));

        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        new JwtVerifier(Ed25519Key::fromMultibase(Ed25519KeyTest::PUBLIC))->verify($header.'.'.$payload.'.'.$signature);
    }

    /**
     * @return array<string, mixed>
     */
    private function credential(): array
    {
        return [
            '@context' => ['https://www.w3.org/ns/credentials/v2', 'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json'],
            'id' => 'urn:uuid:3f6b0a52-6a1e-4d55-9a7e-0c2f0f6c1a11',
            'type' => ['VerifiableCredential', 'OpenBadgeCredential'],
            'issuer' => ['id' => 'https://example.org/issuer', 'type' => ['Profile'], 'name' => 'Example issuer'],
            'validFrom' => '2026-11-01T00:00:00Z',
            'validUntil' => '2036-11-01T00:00:00Z',
            'credentialSubject' => [
                'id' => 'did:example:ada',
                'type' => ['AchievementSubject'],
                'achievement' => ['id' => 'https://example.org/achievements/1', 'type' => ['Achievement'], 'name' => 'Example achievement', 'criteria' => ['narrative' => 'Shown.']],
            ],
        ];
    }

    private function fixture(string $name): string
    {
        $contents = file_get_contents(self::FIXTURES.$name);
        self::assertIsString($contents);

        // Each file starts with a line of comment, which says what key it holds or signed it.
        $contents = preg_replace('/^#.*\n/', '', $contents) ?? '';

        return str_ends_with($name, '.jwt') ? trim($contents) : $contents;
    }
}
