<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use Jose\Component\Core\JWK;
use OpenSSLAsymmetricKey;
use Phil\IdentityProviderBundle\Application\Jose\Jws;
use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Encoding\Der;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AuthenticatorData;
use Phil\IdentityProviderBundle\Domain\WebAuthn\Cbor as CborReader;
use Phil\IdentityProviderBundle\Domain\WebAuthn\ClientData;
use Phil\IdentityProviderBundle\Domain\WebAuthn\CoseKey;
use Phil\IdentityProviderBundle\Domain\WebAuthn\PublicKey;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Authenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Cbor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The edges of every bound the readers of the wire formats keep: the byte that is the last one allowed, and the one
 * after it.
 */
#[CoversClass(Der::class)]
#[CoversClass(Jws::class)]
#[CoversClass(PrivateKey::class)]
#[CoversClass(PublicKey::class)]
#[CoversClass(AuthenticatorData::class)]
#[CoversClass(CborReader::class)]
#[CoversClass(ClientData::class)]
#[CoversClass(CoseKey::class)]
#[CoversClass(RelyingParty::class)]
final class LimitsTest extends TestCase
{
    private const string SHORT_X = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgvYj9P1iTuLaWefKG
        SwXKtvbpgLno8H3nOWWelMagaL2hRANCAAQALNdfCD/IFSR2KuIIMbONtg264UrP
        ne9OKIfc8mI916fiUv2sJNhffmbS6esY+pt9P3Osb4iWx1rHfhGYjQYC
        -----END PRIVATE KEY-----
        PEM;

    private const string SHORT_Y = <<<'PEM'
        -----BEGIN PRIVATE KEY-----
        MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQguPdqt0vT5fSer81D
        FmcIqcHY4anqzdLlsvSGMd74JcKhRANCAASuG5iZMEdxX+j85zSXKlQZAFR5lcH0
        nXhkxXVeynviAQAgwtm969mxG5yPDy3QW8rymx+Sql0L8/Dc2530qegD
        -----END PRIVATE KEY-----
        PEM;

    public function testAP256KeyNeedsTwoCoordinatesOf32Bytes(): void
    {
        self::assertStringStartsWith("-----BEGIN PUBLIC KEY-----\n", Der::p256PublicKey(str_repeat("\x01", 32), str_repeat("\x02", 32)));

        foreach ([[31, 32], [33, 32], [32, 31], [32, 33]] as [$x, $y]) {
            try {
                Der::p256PublicKey(str_repeat("\x01", $x), str_repeat("\x02", $y));
                self::fail(\sprintf('%d and %d bytes are accepted.', $x, $y));
            } catch (MalformedInput $malformedInput) {
                self::assertSame('A P-256 coordinate holds 32 bytes.', $malformedInput->getMessage());
            }
        }
    }

    public function testAnIntegerIsPaddedOnlyWhenItsFirstBitIsSet(): void
    {
        self::assertStringContainsString("\x02\x82\x01\x00\x7F", $this->der(Der::rsaPublicKey(str_repeat("\x7F", 256), "\x03")));
        self::assertStringContainsString("\x02\x82\x01\x01\x00\x80", $this->der(Der::rsaPublicKey("\x80".str_repeat("\x00", 255), "\x03")));
    }

    public function testTheLeadingZeroBytesOfAnIntegerAreDropped(): void
    {
        self::assertStringContainsString("\x02\x82\x01\x00\x7F", $this->der(Der::rsaPublicKey("\x00\x00".str_repeat("\x7F", 256), "\x03")));
    }

    public function testAnEmptyIntegerIsZero(): void
    {
        self::assertStringEndsWith("\x02\x01\x00", $this->der(Der::rsaPublicKey(str_repeat("\x7F", 256), '')));
    }

    public function testATokenIsAtMost8192Characters(): void
    {
        $keys = static fn (): null => null;

        try {
            Jws::decode(str_repeat('a', 8192), $keys);
            self::fail('Accepted.');
        } catch (MalformedInput $malformedInput) {
            self::assertSame('A compact JSON Web Signature has three parts.', $malformedInput->getMessage());
        }

        try {
            Jws::decode(str_repeat('a', 8193), $keys);
            self::fail('Accepted.');
        } catch (MalformedInput $malformedInput) {
            self::assertSame('The token is too long.', $malformedInput->getMessage());
        }
    }

    public function testTheHeaderOfAToken(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::EdDSA, 'kid-1');
        $header = json_decode(Base64Url::decode(explode('.', Jws::encode(['sub' => 'a'], $key))[0]), true);
        self::assertSame(['alg' => 'EdDSA', 'kid' => 'kid-1', 'typ' => 'JWT'], $header);

        $header = json_decode(Base64Url::decode(explode('.', Jws::encode(['sub' => 'a'], $key, ['typ' => 'at+jwt']))[0]), true);
        self::assertSame(['alg' => 'EdDSA', 'kid' => 'kid-1', 'typ' => 'at+jwt'], $header);
    }

    public function testAClaimIsWrittenWithoutEscapingSlashesNorLetters(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::EdDSA);
        $payload = Base64Url::decode(explode('.', Jws::encode(['iss' => 'https://example.com/a', 'name' => 'Émile'], $key))[1]);

        self::assertSame('{"iss":"https://example.com/a","name":"Émile"}', $payload);
    }

    public function testTheJsonOfATokenIsAtMost16LevelsDeepAndAnObject(): void
    {
        $secret = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $key = PrivateKey::fromMaterial(SigningAlgorithm::EdDSA, 'kid', Base64Url::encode($secret));
        $keys = static fn (string $kid): ?JWK => 'kid' === $kid ? new JWK($key->publicJwk()) : null;
        $token = static function (string $claims) use ($secret): string {
            $input = Base64Url::encode('{"alg":"EdDSA","kid":"kid"}').'.'.Base64Url::encode($claims);

            return $input.'.'.Base64Url::encode(sodium_crypto_sign_detached($input, $secret));
        };

        self::assertIsArray(Jws::decode($token(str_repeat('{"a":', 15).'1'.str_repeat('}', 15)), $keys));
        self::assertSame([], Jws::decode($token('[]'), $keys));

        foreach ([str_repeat('{"a":', 16).'1'.str_repeat('}', 16), '[1]', '"text"'] as $claims) {
            try {
                Jws::decode($token($claims), $keys);
                self::fail('Accepted.');
            } catch (MalformedInput) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testASignatureThatIsNotTheKeysIsRefused(): void
    {
        $key = PrivateKey::generate(SigningAlgorithm::EdDSA, 'kid');
        $other = PrivateKey::generate(SigningAlgorithm::EdDSA, 'kid');

        $this->expectException(VerificationFailed::class);

        Jws::decode(Jws::encode(['a' => 1], $other), static fn (): JWK => new JWK($key->publicJwk()));
    }

    public function testAKeyKeepsTheIdentifierItIsGiven(): void
    {
        self::assertSame('mine', PrivateKey::generate(SigningAlgorithm::ES256, 'mine')->kid);
        self::assertNotSame(PrivateKey::generate(SigningAlgorithm::ES256)->kid, PrivateKey::generate(SigningAlgorithm::ES256)->kid);
    }

    public function testACoordinateWithLeadingZerosIsPaddedBack(): void
    {
        // Two keys of P-256 made once for this test: the first coordinate of the first one, the second of the other, is
        // 31 bytes long, as one key in 128 has.
        foreach ([self::SHORT_X, self::SHORT_Y] as $pem) {
            $key = openssl_pkey_get_private($pem);
            self::assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
            $details = openssl_pkey_get_details($key);
            self::assertIsArray($details);
            self::assertIsArray($details['ec']);
            self::assertIsString($details['ec']['x']);
            self::assertIsString($details['ec']['y']);
            self::assertContains(31, [\strlen($details['ec']['x']), \strlen($details['ec']['y'])]);

            $jwk = PrivateKey::fromMaterial(SigningAlgorithm::ES256, 'k', $pem)->publicJwk();
            self::assertSame(32, \strlen(Base64Url::decode($jwk['x'])));
            self::assertSame(32, \strlen(Base64Url::decode($jwk['y'])));
        }
    }

    public function testAKeyOfAnotherTypeIsNotTakenForTheAlgorithm(): void
    {
        $rsa = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $rsa);
        openssl_pkey_export($rsa, $rsaPem);
        \assert(\is_string($rsaPem));

        foreach ([[SigningAlgorithm::ES256, $rsaPem], [SigningAlgorithm::RS256, $this->ecPem()]] as [$algorithm, $pem]) {
            try {
                PrivateKey::fromMaterial($algorithm, 'k', $pem);
                self::fail('Accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('The material is not a private key of %s.', $algorithm->value), $invalidValue->getMessage());
            }
        }

        self::assertSame('RS256', PrivateKey::fromMaterial(SigningAlgorithm::RS256, 'k', $rsaPem)->publicJwk()['alg']);
    }

    public function testAnRsaModulusHasAtLeast2048BitsAndTheExponentAtMost8Bytes(): void
    {
        self::assertSame(SigningAlgorithm::RS256, PublicKey::rsa(str_repeat("\x01", 256), "\x01\x00\x01")->algorithm);
        self::assertSame(SigningAlgorithm::RS256, PublicKey::rsa(str_repeat("\x01", 256), str_repeat("\x01", 8))->algorithm);

        foreach ([[255, 3], [256, 9], [256, 0]] as [$modulus, $exponent]) {
            try {
                PublicKey::rsa(str_repeat("\x01", $modulus), str_repeat("\x01", $exponent));
                self::fail('Accepted.');
            } catch (MalformedInput) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheLeadingZeroBytesOfAnRsaKeyAreNotCounted(): void
    {
        self::assertSame(SigningAlgorithm::RS256, PublicKey::rsa("\x00".str_repeat("\x01", 256), "\x00\x01\x00\x01")->algorithm);
        self::assertSame(SigningAlgorithm::RS256, PublicKey::rsa(str_repeat("\x01", 256), "\x00".str_repeat("\x01", 8))->algorithm);

        foreach ([["\x00".str_repeat("\x01", 255), "\x03"], [str_repeat("\x01", 256), "\x00"]] as [$modulus, $exponent]) {
            try {
                PublicKey::rsa($modulus, $exponent);
                self::fail('Accepted.');
            } catch (MalformedInput) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testACoseKeyIsReadFromItsMap(): void
    {
        $key = new Authenticator()->coseKey();
        $map = CborReader::decode($key);
        self::assertIsArray($map);

        self::assertSame(SigningAlgorithm::ES256, CoseKey::toPublicKey($map)->algorithm);
        self::assertSame(SigningAlgorithm::ES256, CoseKey::decode($key)->algorithm);
    }

    public function testACborIntegerMayBeZeroOnEightBytes(): void
    {
        self::assertSame(0, CborReader::decode("\x1B".str_repeat("\x00", 8)));
    }

    public function testACborIntegerIsBigEndian(): void
    {
        self::assertSame(258, CborReader::decode("\x19\x01\x02"));
        self::assertSame(16_909_060, CborReader::decode("\x1A\x01\x02\x03\x04"));
        self::assertSame(72_623_859_790_382_856, CborReader::decode("\x1B\x01\x02\x03\x04\x05\x06\x07\x08"));
        self::assertSame(-259, CborReader::decode("\x39\x01\x02"));

        foreach (["\x1B\x80".str_repeat("\x00", 7), "\x1B".str_repeat("\xFF", 8)] as $bytes) {
            try {
                CborReader::decode($bytes);
                self::fail('Accepted.');
            } catch (MalformedInput $malformedInput) {
                self::assertSame('The CBOR integer is too large.', $malformedInput->getMessage());
            }
        }
    }

    public function testACborMapIsNestedAtMostEightLevels(): void
    {
        $nested = static fn (int $levels, string $inner): string => str_repeat("\x81", $levels).$inner;

        foreach ([[7, true], [8, false]] as [$levels, $accepted]) {
            try {
                CborReader::decode($nested($levels, "\xA1\x01\x01"));
                self::assertTrue($accepted, 'Accepted beyond the depth.');
            } catch (MalformedInput $malformedInput) {
                self::assertFalse($accepted, $malformedInput->getMessage());
            }
        }
    }

    public function testACborMapKeyThatIsNestedTooDeepIsReportedAsSuch(): void
    {
        // A map at depth 8 whose key is an empty array: the key is read at depth 9.
        try {
            CborReader::decode(str_repeat("\x81", 8)."\xA1\x80\x01");
            self::fail('Accepted.');
        } catch (MalformedInput $malformedInput) {
            self::assertSame('The CBOR item is nested too deep or holds too many items.', $malformedInput->getMessage());
        }

        // The same map one level up: the key is read at depth 8 and refused as a key.
        try {
            CborReader::decode(str_repeat("\x81", 7)."\xA1\x80\x01");
            self::fail('Accepted.');
        } catch (MalformedInput $malformedInput) {
            self::assertSame('A CBOR map is keyed by distinct integers or texts.', $malformedInput->getMessage());
        }
    }

    public function testTheClientDataIsAtMost4096BytesAndFourLevelsDeep(): void
    {
        $json = static fn (int $length): string => str_pad('{"type":"t","challenge":"c","origin":"o"}', $length, ' ');

        self::assertSame('t', ClientData::parse($json(4096))->type);

        try {
            ClientData::parse($json(4097));
            self::fail('Accepted.');
        } catch (MalformedInput $malformedInput) {
            self::assertSame('The client data is too long.', $malformedInput->getMessage());
        }

        self::assertSame('t', ClientData::parse('{"type":"t","challenge":"c","origin":"o","x":{"y":{"z":1}}}')->type);
        $this->expectException(MalformedInput::class);

        ClientData::parse('{"type":"t","challenge":"c","origin":"o","x":{"y":{"z":{"w":1}}}}');
    }

    public function testTheClientDataNeedsItsThreeMembers(): void
    {
        foreach (['{"challenge":"c","origin":"o"}', '{"type":"t","origin":"o"}', '{"type":"t","challenge":"c"}', '{"type":1,"challenge":"c","origin":"o"}', '"text"', '5'] as $json) {
            try {
                ClientData::parse($json);
                self::fail($json);
            } catch (MalformedInput $malformedInput) {
                self::assertSame('The client data has a type, a challenge and an origin.', $malformedInput->getMessage());
            }
        }
    }

    public function testTheAuthenticatorDataKeepsTheCounterOfFourBytes(): void
    {
        $data = AuthenticatorData::parse(str_repeat("\x01", 32)."\x01\x00\x00\x01\x02");
        self::assertSame(258, $data->signCount);

        $data = AuthenticatorData::parse(str_repeat("\x01", 32)."\x01\x00\x00\x00\x00");
        self::assertSame(0, $data->signCount);

        self::assertSame(16_909_060, AuthenticatorData::parse(str_repeat("\x01", 32)."\x01\x01\x02\x03\x04")->signCount);
        self::assertSame(4_294_967_295, AuthenticatorData::parse(str_repeat("\x01", 32)."\x01\xFF\xFF\xFF\xFF")->signCount);
    }

    public function testACredentialIdentifierThatFillsTheRestLeavesNoKey(): void
    {
        $bytes = str_repeat("\x01", 32)."\x41\x00\x00\x00\x00".str_repeat("\x02", 16)."\x00\x04abcd";

        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains('The CBOR item is cut short.');

        AuthenticatorData::parse($bytes);
    }

    public function testAnAssertionIsOfTheChallengeAndTheOriginItIsGiven(): void
    {
        $relyingParty = new RelyingParty('example.com', 'Example', ['https://example.com']);
        $authenticator = new Authenticator();
        $coseKey = $authenticator->coseKey();
        [$clientData, $authData, $signature] = $authenticator->assertion('challenge');

        self::assertSame(1, $relyingParty->verifyAssertion($clientData, $authData, $signature, 'challenge', $coseKey, 0)->signCount);

        $this->expectException(VerificationFailed::class);
        $this->expectExceptionMessageIsOrContains('The client data signs another challenge.');

        $relyingParty->verifyAssertion($clientData, $authData, $signature, 'other', $coseKey, 0);
    }

    public function testARegistrationIsOfTheRelyingPartyAndOfAPresentUser(): void
    {
        $relyingParty = new RelyingParty('example.com', 'Example', ['https://example.com']);

        foreach ([
            [new Authenticator(rpId: 'other.example'), 'The response is for another relying party.', $relyingParty],
            [new Authenticator(flags: 0x00), 'The user was not present.', $relyingParty],
            [new Authenticator(flags: 0x01), 'The user was not verified.', new RelyingParty('example.com', 'Example', ['https://example.com'], true)],
        ] as [$authenticator, $message, $party]) {
            $response = json_decode($authenticator->register('challenge'), true);
            self::assertIsArray($response);
            self::assertIsArray($response['response']);
            self::assertIsString($response['response']['clientDataJSON']);
            self::assertIsString($response['response']['attestationObject']);
            $clientData = Base64Url::decode($response['response']['clientDataJSON']);
            $attestation = Base64Url::decode($response['response']['attestationObject']);

            try {
                $party->verifyRegistration($clientData, $attestation, 'challenge');
                self::fail($message);
            } catch (VerificationFailed $verificationFailed) {
                self::assertSame($message, $verificationFailed->getMessage());
            }
        }
    }

    public function testAnAttestationObjectIsAtMost16384Bytes(): void
    {
        $relyingParty = new RelyingParty('example.com', 'Example', ['https://example.com']);
        $clientData = new Authenticator()->clientData('webauthn.create', 'challenge');

        foreach ([[RelyingParty::MAX_ATTESTATION_OBJECT, 'The CBOR item is followed by more bytes.'], [RelyingParty::MAX_ATTESTATION_OBJECT + 1, 'The attestation object is too long.']] as [$length, $message]) {
            try {
                $relyingParty->verifyRegistration($clientData, "\x00".str_repeat("\x00", $length - 1), 'challenge');
                self::fail($message);
            } catch (MalformedInput $malformedInput) {
                self::assertSame($message, $malformedInput->getMessage());
            }
        }
    }

    public function testARegistrationWithoutACredentialIsRefused(): void
    {
        $relyingParty = new RelyingParty('example.com', 'Example', ['https://example.com']);
        $authenticator = new Authenticator();
        $authData = hash('sha256', 'example.com', true)."\x01\x00\x00\x00\x00";
        $attestation = Cbor::encode(['fmt' => 'none', 'attStmt' => [], 'authData' => Cbor::bytes($authData)]);

        $this->expectException(VerificationFailed::class);
        $this->expectExceptionMessageIsOrContains('The registration carries no credential.');

        $relyingParty->verifyRegistration($authenticator->clientData('webauthn.create', 'challenge'), $attestation, 'challenge');
    }

    public function testTheOptionsOfASignInListTheCredentialsAllowed(): void
    {
        $options = new RelyingParty('example.com', 'Example', ['https://example.com'])->requestOptions('challenge', ['a', 'b']);

        self::assertSame([['type' => 'public-key', 'id' => 'a'], ['type' => 'public-key', 'id' => 'b']], $options['allowCredentials']);
    }

    private function ecPem(): string
    {
        $key = openssl_pkey_new(['private_key_type' => \OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->assertInstanceOf(OpenSSLAsymmetricKey::class, $key);
        openssl_pkey_export($key, $pem);
        \assert(\is_string($pem));

        return $pem;
    }

    private function der(string $pem): string
    {
        $der = base64_decode(implode('', \array_slice(explode("\n", trim($pem)), 1, -1)), true);
        self::assertIsString($der);

        return $der;
    }
}
