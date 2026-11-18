<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit;

use DateTimeImmutable;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Contract\Signer;
use Phil\OpenBadges\Contract\VerificationKey;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Credentials;
use Phil\OpenBadges\Encoding\Base64Url;
use Phil\OpenBadges\Endorsements;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Jwt\Algorithms;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Jwt\JwtVerifier;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaKey;
use Phil\OpenBadges\Key\RsaSigner;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Tests\Unit\Support\Signatures;
use Phil\OpenBadges\Tests\Unit\Support\TestKeys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Base64Url::class)]
#[CoversClass(Issuer::class)]
#[CoversClass(Achievement::class)]
#[CoversClass(Recipient::class)]
#[CoversClass(CredentialIssuer::class)]
#[CoversClass(JwtVerifier::class)]
#[CoversClass(JwtSigner::class)]
#[CoversClass(Credentials::class)]
#[CoversClass(Endorsements::class)]
#[CoversClass(Ed25519Signer::class)]
#[CoversClass(Ed25519Key::class)]
#[CoversClass(RsaSigner::class)]
#[CoversClass(RsaKey::class)]
#[CoversClass(Algorithms::class)]
final class CredentialIssuerTest extends TestCase
{
    private const string ID = 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f';

    public function testBase64Url(): void
    {
        self::assertSame('-_8', Base64Url::encode("\xfb\xff"));
        self::assertSame("\xfb\xff", Base64Url::decode('-_8'));
        self::assertSame('', Base64Url::decode(''));
        $this->expectExceptionObject(InvalidCredential::because('A part of the token is not URL-safe base 64.'));
        Base64Url::decode('+/8=');
    }

    public function testTheParts(): void
    {
        self::assertSame(['id' => 'https://example.org/issuer', 'type' => ['Profile'], 'name' => 'Example', 'url' => 'https://example.org', 'email' => 'badges@example.org'], new Issuer('https://example.org/issuer', 'Example', 'https://example.org', 'badges@example.org')->toArray());
        self::assertSame(['id' => 'http://example.org/issuer', 'type' => ['Profile'], 'name' => 'Example'], new Issuer('http://example.org/issuer', 'Example')->toArray());
        self::assertSame([
            'id' => 'https://example.org/badges/gold',
            'type' => ['Achievement'],
            'name' => 'Gold',
            'description' => 'Fifty comments.',
            'criteria' => ['narrative' => 'Comment on fifty news items.'],
            'image' => ['id' => 'https://example.org/gold.png', 'type' => 'Image'],
        ], new Achievement('https://example.org/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty news items.', 'https://example.org/gold.png')->toArray());
        self::assertArrayNotHasKey('image', new Achievement('https://example.org/badges/gold', 'Gold', '', 'Comment.')->toArray());

        self::assertSame(['id' => 'did:example:123'], Recipient::did('did:example:123')->toArray());
        self::assertSame('did:example:123', Recipient::did('did:example:123')->did);
        self::assertSame(['identifier' => [[
            'type' => 'IdentityObject',
            'identityHash' => 'sha256$'.hash('sha256', 'ada@example.orgsaltsalt'),
            'identityType' => 'emailAddress',
            'hashed' => true,
            'salt' => 'saltsalt',
        ]]], Recipient::email(' Ada@Example.org ', 'saltsalt')->toArray());
        self::assertNull(Recipient::email('ada@example.org', 'saltsalt')->did);
        self::assertSame('sha256$'.hash('sha256', 'élodie@example.orgsaltsalt'), Recipient::email('ÉLODIE@example.org', 'saltsalt')->toArray()['identifier'][0]['identityHash'] ?? null);
        self::assertSame('sha256$'.hash('sha256', 'https://ada.example.org/saltsalt'), Recipient::url('https://ada.example.org/', 'saltsalt')->toArray()['identifier'][0]['identityHash'] ?? null);
        self::assertSame('url', Recipient::url('https://ada.example.org/', 'saltsalt')->toArray()['identifier'][0]['identityType'] ?? null);
    }

    public function testAnIssuerIsAnUrl(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The identifier of the issuer is an absolute HTTP URL; "example.org" is not.'));
        new Issuer('example.org', 'Example');
    }

    public function testAnIssuersSiteIsAnUrl(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The identifier of the issuer\'s web site is an absolute HTTP URL; "https://" is not.'));
        new Issuer('https://example.org', 'Example', 'https://');
    }

    public function testAnIssuerHasAName(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('An issuer has a name.'));
        new Issuer('https://example.org', ' ');
    }

    public function testAnAchievementIsAnUrl(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The identifier of the achievement is an absolute HTTP URL; "https://example.org/a b" is not.'));
        new Achievement('https://example.org/a b', 'Gold', '', 'Comment.');
    }

    public function testAnAchievementUrlEndsWithTheUrl(): void
    {
        $this->expectException(InvalidBadge::class);
        new Achievement("https://example.org/gold\n", 'Gold', '', 'Comment.');
    }

    public function testAnAchievementImageIsAnUrl(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The identifier of the achievement\'s image is an absolute HTTP URL; "gold.png" is not.'));
        new Achievement('https://example.org/gold', 'Gold', '', 'Comment.', 'gold.png');
    }

    public function testAnAchievementHasAName(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The achievement "https://example.org/gold" has a name and the narrative of its criteria.'));
        new Achievement('https://example.org/gold', ' ', '', 'Comment.');
    }

    public function testAnAchievementHasCriteria(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The achievement "https://example.org/gold" has a name and the narrative of its criteria.'));
        new Achievement('https://example.org/gold', 'Gold', '', ' ');
    }

    public function testADidIsChecked(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('"did:Example:1" is not a decentralized identifier.'));
        Recipient::did('did:Example:1');
    }

    public function testADidStartsTheIdentifier(): void
    {
        $this->expectException(InvalidBadge::class);
        Recipient::did('x did:example:1');
    }

    public function testADidEndsTheIdentifier(): void
    {
        $this->expectException(InvalidBadge::class);
        Recipient::did('did:example:1 x');
    }

    public function testADidEndsWithTheIdentifier(): void
    {
        $this->expectException(InvalidBadge::class);
        Recipient::did("did:example:1\n");
    }

    public function testADidHasAnIdentifier(): void
    {
        $this->expectException(InvalidBadge::class);
        Recipient::did('did:example:');
    }

    public function testAnEmailIsChecked(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('An e-mail address holds an "@".'));
        Recipient::email('ada', 'saltsalt');
    }

    public function testARecipientUrlIsChecked(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The identifier of the recipient is an absolute HTTP URL; "ada" is not.'));
        Recipient::url('ada', 'saltsalt');
    }

    public function testASaltIsLongEnough(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The salt of a hashed identity is at least 8 characters.'));
        Recipient::email('ada@example.org', 'salt123');
    }

    public function testACredentialIsBuilt(): void
    {
        $issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), Ed25519Signer::fromSeed(str_repeat("\1", 32)));
        $credential = $issuer->credential($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto'), new DateTimeImmutable('2027-11-07 10:00:00 UTC'));

        self::assertSame([
            '@context' => ['https://www.w3.org/ns/credentials/v2', 'https://purl.imsglobal.org/spec/ob/v3p0/context-3.0.3.json'],
            'id' => self::ID,
            'type' => ['VerifiableCredential', 'OpenBadgeCredential'],
            'issuer' => ['id' => 'https://example.org/issuer', 'type' => ['Profile'], 'name' => 'Example'],
            'validFrom' => '2026-11-07T15:00:00Z',
            'name' => 'Gold',
            'credentialSubject' => ['type' => ['AchievementSubject'], 'id' => 'did:example:ada', 'achievement' => $this->achievement()->toArray()],
            'validUntil' => '2027-11-07T10:00:00Z',
        ], $credential);
        self::assertArrayNotHasKey('validUntil', $issuer->credential($this->achievement(), Recipient::did('did:example:ada'), 'https://example.org/credentials/1', new DateTimeImmutable()));
    }

    public function testACredentialIdIsChecked(): void
    {
        $issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), Ed25519Signer::fromSeed(str_repeat("\1", 32)));
        $this->expectExceptionObject(InvalidBadge::because('The identifier of a credential is a "urn:uuid:" or an absolute HTTP URL; "urn:uuid:1" is not.'));
        $issuer->credential($this->achievement(), Recipient::did('did:example:ada'), 'urn:uuid:1', new DateTimeImmutable());
    }

    public function testACredentialIdIsWhole(): void
    {
        $issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), Ed25519Signer::fromSeed(str_repeat("\1", 32)));

        foreach ([self::ID.'zz', 'x'.self::ID, "https://example.org/1\n", 'xhttps://example.org/1'] as $id) {
            try {
                $issuer->credential($this->achievement(), Recipient::did('did:example:ada'), $id, new DateTimeImmutable());
                self::fail(\sprintf('The identifier "%s" was expected to be refused.', $id));
            } catch (InvalidBadge) {
                self::assertNotSame('', $id);
            }
        }
    }

    public function testACredentialEndsAfterItStarts(): void
    {
        $issuer = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), Ed25519Signer::fromSeed(str_repeat("\1", 32)));
        $this->expectExceptionObject(InvalidBadge::because('A credential is valid until a moment after it becomes valid.'));
        $issuer->credential($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 10:00:00 UTC'), new DateTimeImmutable('2026-11-07 10:00:00 UTC'));
    }

    public function testACredentialIsSignedWithEd25519AndVerified(): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $token = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer)
            ->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 10:00:00 UTC'), new DateTimeImmutable('2027-11-07 10:00:00 UTC'));
        [$header, $payload] = explode('.', $token);

        self::assertSame('EdDSA', $signer->algorithm());
        self::assertSame(['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => Base64Url::encode(sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat("\1", 32))))], $signer->publicJwk());
        self::assertSame(['alg' => 'EdDSA', 'typ' => 'vc+jwt', 'jwk' => $signer->publicJwk()], json_decode(Base64Url::decode($header), true));
        self::assertStringNotContainsString('\/', Base64Url::decode($payload));
        self::assertStringContainsString('"name":"Médaille"', new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer)->issue(new Achievement('https://example.org/m', 'Médaille', '', 'Comment.'), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable()) |> (static fn (string $token): string => Base64Url::decode(explode('.', $token)[1])));

        $claims = new JwtVerifier(Ed25519Key::fromJwk($signer->publicJwk()))->verify($token, new DateTimeImmutable('2026-11-07 10:00:00 UTC'));
        self::assertSame('https://example.org/issuer', $claims['iss'] ?? null);
        self::assertSame(self::ID, $claims['jti'] ?? null);
        self::assertSame('did:example:ada', $claims['sub'] ?? null);
        self::assertSame(new DateTimeImmutable('2026-11-07 10:00:00 UTC')->getTimestamp(), $claims['nbf'] ?? null);
        self::assertSame(new DateTimeImmutable('2027-11-07 10:00:00 UTC')->getTimestamp(), $claims['exp'] ?? null);
        self::assertSame(['VerifiableCredential', 'OpenBadgeCredential'], $claims['type'] ?? null);
        self::assertSame($claims, new JwtVerifier($signer->verificationKey())->verify($token));
        self::assertSame($claims, new JwtVerifier($signer->verificationKey())->verify($token, new DateTimeImmutable('2027-11-07 09:59:59 UTC')));

        $hashed = new JwtVerifier($signer->verificationKey())->verify(new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer)
            ->issue($this->achievement(), Recipient::email('ada@example.org', 'saltsalt'), self::ID, new DateTimeImmutable('2026-11-07 10:00:00 UTC')));
        self::assertArrayNotHasKey('sub', $hashed);
        self::assertArrayNotHasKey('exp', $hashed);
        self::assertSame(['iss', 'jti', 'nbf'], array_values(array_intersect(array_keys($hashed), ['iss', 'jti', 'sub', 'nbf', 'exp'])));
    }

    public function testACredentialIsNotValidBeforeItStarts(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The credential is not valid yet.'));
        $this->verify($this->ed25519Token(), new DateTimeImmutable('2026-11-07 09:59:59 UTC'));
    }

    public function testACredentialExpires(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The credential has expired.'));
        $this->verify($this->ed25519Token(), new DateTimeImmutable('2027-11-07 10:00:00 UTC'));
    }

    public function testAnExpiryIsCheckedOnlyAtAMoment(): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $token = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer)
            ->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('1969-12-31 00:00:00 UTC'), new DateTimeImmutable('1970-01-01 00:00:00 UTC'));

        self::assertSame(0, new JwtVerifier($signer->verificationKey())->verify($token)['exp'] ?? null);
    }

    /**
     * @return iterable<string, array{string, string, ?string}>
     */
    public static function numericDates(): iterable
    {
        yield 'a fractional start not reached' => ['{"nbf":1800000000.5}', '@1800000000', 'The credential is not valid yet.'];
        yield 'a fractional start reached' => ['{"nbf":1800000000.5}', '@1800000001', null];
        yield 'a fractional expiry passed' => ['{"exp":1800000000.5}', '@1800000001', 'The credential has expired.'];
        yield 'a fractional expiry to come' => ['{"exp":1800000000.5}', '@1800000000', null];
        yield 'a start given as text' => ['{"nbf":"1800000000"}', '@1900000000', 'The "nbf" and "exp" of a credential are numbers of seconds.'];
        yield 'an expiry given as text' => ['{"exp":"1800000000"}', '@1700000000', 'The "nbf" and "exp" of a credential are numbers of seconds.'];
        yield 'a start given as text, read without a moment' => ['{"nbf":"soon"}', '', 'The "nbf" and "exp" of a credential are numbers of seconds.'];
    }

    /**
     * A NumericDate may carry a fraction of a second (RFC 7519, section 2): it is compared as the number it is, and a
     * value of another type is refused rather than skipped.
     */
    #[DataProvider('numericDates')]
    public function testTheStartAndTheExpiryAreNumbersOfSecondsWithOrWithoutAFraction(string $claims, string $at, ?string $refusal): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $header = Base64Url::encode('{"alg":"EdDSA"}');
        $payload = Base64Url::encode($claims);
        $token = $header.'.'.$payload.'.'.Base64Url::encode(Signatures::sign($signer, $header.'.'.$payload));

        if (null !== $refusal) {
            $this->expectExceptionObject(InvalidCredential::because($refusal));
        }

        self::assertNotSame([], new JwtVerifier($signer->verificationKey())->verify($token, '' === $at ? null : new DateTimeImmutable($at)));
    }

    public function testATokenHasThreeParts(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('A credential token has three parts separated by dots.'));
        $this->verify('a.b');
    }

    public function testATokenIsSignedWithTheKeysAlgorithm(): void
    {
        [, $payload, $signature] = explode('.', $this->ed25519Token());
        $this->expectExceptionObject(InvalidCredential::because('The credential is not signed with EdDSA.'));
        $this->verify(Base64Url::encode('{"alg":"none"}').'.'.$payload.'.'.$signature);
    }

    public function testATokenHeaderIsAnObject(): void
    {
        [, $payload, $signature] = explode('.', $this->ed25519Token());
        $this->expectExceptionObject(InvalidCredential::because('A part of the credential token is not a JSON object.'));
        $this->verify(Base64Url::encode('"EdDSA"').'.'.$payload.'.'.$signature);
    }

    public function testATokenHeaderIsJson(): void
    {
        [, $payload, $signature] = explode('.', $this->ed25519Token());
        $this->expectExceptionObject(InvalidCredential::because('A part of the credential token is not JSON.'));
        $this->verify(Base64Url::encode('{alg').'.'.$payload.'.'.$signature);
    }

    public function testATokenIsNotTooDeep(): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $header = Base64Url::encode('{"alg":"EdDSA"}');
        $payload = Base64Url::encode(str_repeat('[', 64).str_repeat(']', 64));
        $this->expectExceptionObject(InvalidCredential::because('A part of the credential token is not JSON.'));
        new JwtVerifier($signer->verificationKey())->verify($header.'.'.$payload.'.'.Base64Url::encode(Signatures::sign($signer, $header.'.'.$payload)));
    }

    public function testATokenPayloadMayNestUpToTheLimit(): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $header = Base64Url::encode('{"alg":"EdDSA"}');
        $payload = Base64Url::encode(str_repeat('[', 63).str_repeat(']', 63));
        self::assertCount(1, new JwtVerifier($signer->verificationKey())->verify($header.'.'.$payload.'.'.Base64Url::encode(Signatures::sign($signer, $header.'.'.$payload))));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function uncommonHeaders(): iterable
    {
        yield 'a critical parameter' => [['alg' => 'EdDSA', 'crit' => ['exp']]];
        yield 'claims not encoded' => [['alg' => 'EdDSA', 'b64' => true]];
    }

    /**
     * @param array<string, mixed> $header
     */
    #[DataProvider('uncommonHeaders')]
    public function testATokenMarksNoHeaderParameterCritical(array $header): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $signed = Base64Url::encode((string) json_encode($header)).'.'.Base64Url::encode('{"iss":"https://example.org/issuer"}');
        $this->expectExceptionObject(InvalidCredential::because('A credential token marks no header parameter critical and encodes its claims.'));
        new JwtVerifier($signer->verificationKey())->verify($signed.'.'.Base64Url::encode(Signatures::sign($signer, $signed)));
    }

    public function testATokenIsVerifiedWithEdDsaOrRs256Only(): void
    {
        $key = new class implements VerificationKey {
            public function algorithm(): string
            {
                return 'ES256';
            }

            public function jwk(): JWK
            {
                return new JWK(['kty' => 'EC']);
            }
        };
        $signed = Base64Url::encode('{"alg":"ES256"}').'.'.Base64Url::encode('{}');
        $this->expectExceptionObject(InvalidCredential::because('The algorithm "ES256" is not one a credential is verified with.'));
        new JwtVerifier($key)->verify($signed.'.'.Base64Url::encode('signature'));
    }

    public function testAKeyThatTheLibraryRefusesDoesNotVerify(): void
    {
        $key = new class implements VerificationKey {
            public function algorithm(): string
            {
                return 'EdDSA';
            }

            public function jwk(): JWK
            {
                return new JWK(['kty' => 'RSA', 'n' => 'AQAB', 'e' => 'AQAB']);
            }
        };
        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        new JwtVerifier($key)->verify($this->ed25519Token());
    }

    public function testASignerTheLibraryRefusesSignsNothing(): void
    {
        $ed25519 = Ed25519Signer::fromSeed(str_repeat("\1", 32));
        $signer = new readonly class($ed25519) implements Signer {
            public function __construct(private Ed25519Signer $ed25519)
            {
            }

            public function algorithm(): string
            {
                return 'RS256';
            }

            public function signatureAlgorithm(): SignatureAlgorithm
            {
                return new RS256();
            }

            public function jwk(): JWK
            {
                return $this->ed25519->jwk();
            }

            public function publicJwk(): array
            {
                return $this->ed25519->publicJwk();
            }

            public function verificationKey(): VerificationKey
            {
                return $this->ed25519->verificationKey();
            }
        };
        $this->expectExceptionObject(InvalidBadge::because('The signer cannot sign the token: Wrong key type.'));
        new JwtSigner($signer)->sign(['issuer' => 'https://example.org/issuer']);
    }

    public function testAnAlteredTokenDoesNotVerify(): void
    {
        [$header, $payload, $signature] = explode('.', $this->ed25519Token());
        $altered = Base64Url::encode(str_replace('Gold', 'Gilt', Base64Url::decode($payload)));
        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        $this->verify($header.'.'.$altered.'.'.$signature);
    }

    public function testATruncatedSignatureDoesNotVerify(): void
    {
        [$header, $payload, $signature] = explode('.', $this->ed25519Token());
        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        $this->verify($header.'.'.$payload.'.'.Base64Url::encode(substr(Base64Url::decode($signature), 1)));
    }

    public function testAnotherKeyDoesNotVerify(): void
    {
        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        new JwtVerifier(Ed25519Signer::fromSeed(str_repeat("\2", 32))->verificationKey())->verify($this->ed25519Token());
    }

    public function testAnEd25519SeedIs32Bytes(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('An Ed25519 seed is 32 bytes; this one is 31.'));
        Ed25519Signer::fromSeed(str_repeat("\1", 31));
    }

    public function testAnEd25519KeyIs32Bytes(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('An Ed25519 public key is 32 bytes; this one is 31.'));
        new Ed25519Key(str_repeat("\1", 31));
    }

    public function testAnEd25519JwkIsChecked(): void
    {
        $x = Base64Url::encode(str_repeat("\1", 32));
        self::assertSame('EdDSA', Ed25519Key::fromJwk(['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => $x])->algorithm());

        foreach ([['crv' => 'Ed25519', 'x' => $x], ['kty' => 'RSA', 'crv' => 'Ed25519', 'x' => $x], ['kty' => 'OKP', 'crv' => 'X25519', 'x' => $x], ['kty' => 'OKP', 'crv' => 'Ed25519'], ['kty' => 'OKP', 'crv' => 'Ed25519', 'x' => 3]] as $jwk) {
            try {
                Ed25519Key::fromJwk($jwk);
                self::fail('The JSON Web Key was expected to be refused.');
            } catch (InvalidBadge $refused) {
                self::assertSame('An Ed25519 JSON Web Key has the type "OKP", the curve "Ed25519" and its "x".', $refused->getMessage());
            }
        }
    }

    public function testACredentialIsSignedWithRsaAndVerified(): void
    {
        [$private, $public] = $this->rsa(2048);
        $signer = RsaSigner::fromPem($private);
        $token = new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), $signer)->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 10:00:00 UTC'));

        self::assertSame('RS256', $signer->algorithm());
        self::assertSame('RS256', $signer->verificationKey()->algorithm());
        $jwk = $signer->publicJwk();
        self::assertSame(['kty', 'n', 'e'], array_keys($jwk));
        self::assertSame('RSA', $jwk['kty']);
        self::assertSame('AQAB', $jwk['e']);
        self::assertSame(256, \strlen(Base64Url::decode($jwk['n'])));
        self::assertSame(self::ID, new JwtVerifier(RsaKey::fromPem($public))->verify($token)['jti'] ?? null);
        self::assertSame(self::ID, new JwtVerifier($signer->verificationKey())->verify($token)['jti'] ?? null);

        [$other] = TestKeys::rsa(2048, 'second');
        $this->expectExceptionObject(InvalidCredential::because('The signature of the credential does not verify.'));
        new JwtVerifier(RsaSigner::fromPem($other)->verificationKey())->verify($token);
    }

    public function testAnRsaKeyIsProtectedByItsPassphrase(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem, 'secret');
        self::assertIsString($pem);
        self::assertSame('RS256', RsaSigner::fromPem($pem, 'secret')->algorithm());

        $this->expectExceptionObject(InvalidBadge::because('The private key is not a key in PEM, or its passphrase is wrong.'));
        RsaSigner::fromPem($pem, 'wrong');
    }

    public function testAnRsaKeyHas2048Bits(): void
    {
        [$private] = $this->rsa(1024);
        $this->expectExceptionObject(InvalidBadge::because('An RSA key has 2048 to 8192 bits; this one has 1024.'));
        RsaSigner::fromPem($private);
    }

    public function testAnRsaKeyOf8192BitsIsTheLargestRead(): void
    {
        [$pem] = TestKeys::rsa(8192);

        self::assertSame(8192, RsaKey::bits(Base64Url::decode(RsaSigner::fromPem($pem)->publicJwk()['n'])));
    }

    public function testAnRsaSignerIsAnRsaKey(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The key is not an RSA private key.'));
        RsaSigner::fromPem($this->ec()[0]);
    }

    public function testAnRsaKeyIsAnRsaKey(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The key is not an RSA key.'));
        RsaKey::fromPem($this->ec()[1]);
    }

    public function testAnRsaKeyIsInPem(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The public key is not a key in PEM.'));
        RsaKey::fromPem('not a key');
    }

    public function testAnRsaSignerIsAPrivateKey(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The key is not an RSA private key.'));
        RsaSigner::fromPem($this->rsa(2048)[1]);
    }

    public function testAnRsaSignerIsAnRsaKeyInPem(): void
    {
        $this->expectExceptionObject(InvalidBadge::because('The private key is not a key in PEM, or its passphrase is wrong.'));
        RsaSigner::fromPem('not a key');
    }

    private function achievement(): Achievement
    {
        return new Achievement('https://example.org/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty news items.');
    }

    private function ed25519Token(): string
    {
        return new CredentialIssuer(new Issuer('https://example.org/issuer', 'Example'), Ed25519Signer::fromSeed(str_repeat("\1", 32)))
            ->issue($this->achievement(), Recipient::did('did:example:ada'), self::ID, new DateTimeImmutable('2026-11-07 10:00:00 UTC'), new DateTimeImmutable('2027-11-07 10:00:00 UTC'));
    }

    private function verify(string $token, ?DateTimeImmutable $at = null): void
    {
        new JwtVerifier(Ed25519Signer::fromSeed(str_repeat("\1", 32))->verificationKey())->verify($token, $at);
    }

    /**
     * @return array{string, string}
     */
    private function rsa(int $bits): array
    {
        return TestKeys::rsa($bits);
    }

    /**
     * @return array{string, string}
     */
    private function ec(): array
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $private);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($private);
        self::assertIsString($details['key']);

        return [$private, $details['key']];
    }
}
