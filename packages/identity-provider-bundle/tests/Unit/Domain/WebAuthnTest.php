<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Domain;

use Phil\IdentityProviderBundle\Application\Jose\PrivateKey;
use Phil\IdentityProviderBundle\Domain\Encoding\Base64Url;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Exception\MalformedInput;
use Phil\IdentityProviderBundle\Domain\Exception\VerificationFailed;
use Phil\IdentityProviderBundle\Domain\Jose\SigningAlgorithm;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AuthenticatorData;
use Phil\IdentityProviderBundle\Domain\WebAuthn\Cbor as CborReader;
use Phil\IdentityProviderBundle\Domain\WebAuthn\ClientData;
use Phil\IdentityProviderBundle\Domain\WebAuthn\CoseKey;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RegisteredCredential;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Domain\WebAuthn\VerifiedAssertion;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Authenticator;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\Cbor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

#[CoversClass(CborReader::class)]
#[CoversClass(AuthenticatorData::class)]
#[CoversClass(ClientData::class)]
#[CoversClass(CoseKey::class)]
#[CoversClass(RelyingParty::class)]
#[CoversClass(RegisteredCredential::class)]
#[CoversClass(VerifiedAssertion::class)]
final class WebAuthnTest extends TestCase
{
    public function testCborReadsWhatWebAuthnSends(): void
    {
        $value = ['fmt' => 'none', 1 => -7, 2 => [true, false, null, 23, 24, 255, 256, 65_536, 4_294_967_296], 'b' => Cbor::bytes("\x00\x01")];

        self::assertSame(['fmt' => 'none', 1 => -7, 2 => [true, false, null, 23, 24, 255, 256, 65_536, 4_294_967_296], 'b' => "\x00\x01"], CborReader::decode(Cbor::encode($value)));
        self::assertSame([[1, 2], 3], CborReader::decodeAt("\x82\x01\x02\x03", 0));
        self::assertSame([3, 4], CborReader::decodeAt("\x82\x01\x02\x03", 3));
        self::assertSame(-1, CborReader::decode("\x20"));
        self::assertSame(\PHP_INT_MIN, CborReader::decode("\x3B\x7F\xFF\xFF\xFF\xFF\xFF\xFF\xFF"));
    }

    #[DataProvider('malformedCbor')]
    public function testCborRefusesWhatItDoesNotRead(string $bytes, string $message): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        CborReader::decode($bytes);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedCbor(): iterable
    {
        yield 'empty' => ['', 'The CBOR item is cut short.'];
        yield 'trailing' => ["\x01\x02", 'The CBOR item is followed by more bytes.'];
        yield 'float' => ["\xF9\x00\x00", 'The CBOR simple value or float is not read.'];
        yield 'undefined' => ["\xF7", 'The CBOR simple value or float is not read.'];
        yield 'tag' => ["\xC0\x01", 'CBOR tags are not read.'];
        yield 'indefinite' => ["\x9F\x01\xFF", 'Indefinite CBOR lengths are not read.'];
        yield 'reserved' => ["\x1C", 'Indefinite CBOR lengths are not read.'];
        yield 'too large' => ["\x1B\xFF\xFF\xFF\xFF\xFF\xFF\xFF\xFF", 'The CBOR integer is too large.'];
        yield 'bytes cut' => ["\x42\x01", 'The CBOR item is cut short.'];
        yield 'huge array' => ["\x9A\xFF\xFF\xFF\xFF", 'The CBOR container announces more items than bytes left.'];
        yield 'huge map' => ["\xA2\x01", 'The CBOR container announces more items than bytes left.'];
        yield 'map of the largest count' => ["\xBB\x7F\xFF\xFF\xFF\xFF\xFF\xFF\xFF\x01\x01", 'The CBOR container announces more items than bytes left.'];
        yield 'map of three bytes for two items' => ["\xA2\x01\x01\x01", 'The CBOR container announces more items than bytes left.'];
        yield 'list key' => ["\xA1\x80\x01", 'A CBOR map is keyed by distinct integers or texts.'];
        yield 'duplicate key' => ["\xA2\x01\x01\x01\x02", 'A CBOR map is keyed by distinct integers or texts.'];
        yield 'numeric text key' => ["\xA1\x61\x31\x01", 'A CBOR map is keyed by distinct integers or texts.'];
        yield 'invalid text' => ["\x61\xFF", 'The CBOR text is not UTF-8.'];
        yield 'deep' => [str_repeat("\x81", 9)."\x01", 'The CBOR item is nested too deep or holds too many items.'];
        yield 'many items' => ["\x99\x04\x01".str_repeat("\x01", 1025), 'The CBOR item is nested too deep or holds too many items.'];
    }

    public function testCborAcceptsEightLevelsAnd1024Items(): void
    {
        self::assertSame([[[[[[[[1]]]]]]]], CborReader::decode(str_repeat("\x81", 8)."\x01"));
        self::assertCount(1023, (array) CborReader::decode("\x99\x03\xFF".str_repeat("\x01", 1023)));
        self::assertSame(['a' => 1], CborReader::decode("\xA1\x61a\x01"));
    }

    public function testCoseKeysOfTheThreeAlgorithms(): void
    {
        $authenticator = new Authenticator();
        self::assertSame('ES256', CoseKey::decode($authenticator->coseKey())->algorithm->value);

        $ed = PrivateKey::generate(SigningAlgorithm::EdDSA)->publicJwk();
        self::assertSame('EdDSA', CoseKey::decode(Cbor::encode([1 => 1, 3 => -8, -1 => 6, -2 => Cbor::bytes(Base64Url::decode($ed['x']))]))->algorithm->value);

        $rsa = PrivateKey::generate(SigningAlgorithm::RS256)->publicJwk();
        self::assertSame('RS256', CoseKey::decode(Cbor::encode([1 => 3, 3 => -257, -1 => Cbor::bytes(Base64Url::decode($rsa['n'])), -2 => Cbor::bytes(Base64Url::decode($rsa['e']))]))->algorithm->value);
    }

    #[DataProvider('badCose')]
    public function testCoseKeysOutOfShapeAreRefused(string $cose, string $message): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        CoseKey::decode($cose);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badCose(): iterable
    {
        yield 'not a map' => [Cbor::encode(1), 'A COSE key is a map.'];
        yield 'other algorithm' => [Cbor::encode([1 => 2, 3 => -35]), 'A passkey is an ES256, RS256 or EdDSA key.'];
        yield 'string algorithm' => [Cbor::encode([1 => 2, 3 => 'ES256']), 'A passkey is an ES256, RS256 or EdDSA key.'];
        yield 'type and algorithm apart' => [Cbor::encode([1 => 1, 3 => -7]), 'A passkey is an ES256, RS256 or EdDSA key.'];
        yield 'other curve' => [Cbor::encode([1 => 2, 3 => -7, -1 => 2]), 'An EC2 passkey is on P-256.'];
        yield 'other okp curve' => [Cbor::encode([1 => 1, 3 => -8, -1 => 4]), 'An OKP passkey is on Ed25519.'];
        yield 'missing coordinate' => [Cbor::encode([1 => 2, 3 => -7, -1 => 1, -2 => Cbor::bytes(str_repeat("\x01", 32))]), 'A COSE key has the member -3.'];
        yield 'empty coordinate' => [Cbor::encode([1 => 1, 3 => -8, -1 => 6, -2 => Cbor::bytes('')]), 'A COSE key has the member -2.'];
    }

    public function testAuthenticatorDataWithAndWithoutCredentialAndExtensions(): void
    {
        $authenticator = new Authenticator();
        $plain = AuthenticatorData::parse($authenticator->authenticatorData(0x1D));

        self::assertSame(hash('sha256', 'example.com', true), $plain->rpIdHash);
        self::assertSame(0, $plain->signCount);
        self::assertTrue($plain->has(AuthenticatorData::USER_PRESENT));
        self::assertTrue($plain->has(AuthenticatorData::USER_VERIFIED));
        self::assertTrue($plain->has(AuthenticatorData::BACKUP_ELIGIBLE));
        self::assertTrue($plain->has(AuthenticatorData::BACKED_UP));
        self::assertFalse($plain->has(AuthenticatorData::ATTESTED_CREDENTIAL));
        self::assertNull($plain->credentialId);

        $authenticator->counter = 7;
        $attested = str_repeat("\x02", 16).pack('n', 3).'abc'.$authenticator->coseKey();
        $full = AuthenticatorData::parse($authenticator->authenticatorData(0xC1, $attested.Cbor::encode(['credProps' => true])));

        self::assertSame(7, $full->signCount);
        self::assertSame(str_repeat("\x02", 16), $full->aaguid);
        self::assertSame('abc', $full->credentialId);
        self::assertSame($authenticator->coseKey(), $full->coseKey);
        self::assertSame(0, AuthenticatorData::parse($authenticator->authenticatorData(0x01, ''))->flags & 0x40);
    }

    #[DataProvider('badAuthenticatorData')]
    public function testAuthenticatorDataOutOfShapeIsRefused(string $tail, int $flags, string $message): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        AuthenticatorData::parse(str_repeat("\x00", 32).\chr($flags & 0xFF)."\x00\x00\x00\x00".$tail);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function badAuthenticatorData(): iterable
    {
        yield 'cut credential' => [str_repeat("\x00", 17), 0x41, 'The attested credential data is cut short.'];
        yield 'empty identifier' => [str_repeat("\x00", 16)."\x00\x00", 0x41, 'The credential identifier is empty, too long or cut short.'];
        yield 'long identifier' => [str_repeat("\x00", 16)."\x04\x00".str_repeat("\x00", 1024), 0x41, 'The credential identifier is empty, too long or cut short.'];
        yield 'cut identifier' => [str_repeat("\x00", 16)."\x00\x05abc", 0x41, 'The credential identifier is empty, too long or cut short.'];
        yield 'key not a map' => [str_repeat("\x00", 16)."\x00\x01a\x01", 0x41, 'The credential public key is a COSE map.'];
        yield 'bytes after' => ["\x00", 0x01, 'The authenticator data holds bytes after its last part.'];
    }

    public function testAuthenticatorDataHoldsAtLeast37Bytes(): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains('The authenticator data holds at least 37 bytes.');

        AuthenticatorData::parse(str_repeat("\x00", 36));
    }

    public function testACredentialIdentifierOf1023BytesIsAccepted(): void
    {
        $authenticator = new Authenticator(credentialId: str_repeat('i', 1023));
        $data = AuthenticatorData::parse($authenticator->authenticatorData(0x41, str_repeat("\x00", 16)."\x03\xFF".str_repeat('i', 1023).$authenticator->coseKey()));

        self::assertSame(1023, \strlen((string) $data->credentialId));
    }

    public function testClientDataChecks(): void
    {
        $data = ClientData::parse('{"type":"webauthn.get","challenge":"abc","origin":"https://example.com"}');
        $data->assert('webauthn.get', 'abc', ['https://example.com']);
        self::assertFalse($data->crossOrigin);

        foreach ([
            ['webauthn.create', 'abc', ['https://example.com'], 'another ceremony'],
            ['webauthn.get', 'abd', ['https://example.com'], 'another challenge'],
            ['webauthn.get', 'abc', ['https://evil.example'], 'not allowed'],
        ] as [$type, $challenge, $origins, $message]) {
            try {
                $data->assert($type, $challenge, $origins);
                self::fail($message);
            } catch (VerificationFailed $verificationFailed) {
                self::assertStringContainsString($message, $verificationFailed->getMessage());
            }
        }

        $this->expectException(VerificationFailed::class);
        ClientData::parse('{"type":"webauthn.get","challenge":"abc","origin":"https://example.com","crossOrigin":true}')->assert('webauthn.get', 'abc', ['https://example.com']);
    }

    #[DataProvider('badClientData')]
    public function testClientDataOutOfShapeIsRefused(string $json, string $message): void
    {
        $this->expectException(MalformedInput::class);
        $this->expectExceptionMessageIsOrContains($message);

        ClientData::parse($json);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badClientData(): iterable
    {
        yield 'too long' => [str_repeat(' ', 4097), 'The client data is too long.'];
        yield 'not json' => ['{', 'The client data is not JSON.'];
        yield 'too deep' => ['{"a":{"b":{"c":{"d":1}}}}', 'The client data is not JSON.'];
        yield 'missing origin' => ['{"type":"webauthn.get","challenge":"abc"}', 'The client data has a type, a challenge and an origin.'];
        yield 'not an object' => ['"abc"', 'The client data has a type, a challenge and an origin.'];
    }

    public function testTheOptionsOfBothCeremonies(): void
    {
        $party = new RelyingParty('example.com', 'Example', ['https://example.com']);

        self::assertSame([
            'challenge' => 'c',
            'rp' => ['id' => 'example.com', 'name' => 'Example'],
            'user' => ['id' => Base64Url::encode('USER'), 'name' => 'ada@example.com', 'displayName' => 'Ada'],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -8], ['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'timeout' => 300_000,
            'excludeCredentials' => [['type' => 'public-key', 'id' => 'abc']],
            'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'preferred'],
            'attestation' => 'none',
        ], $party->creationOptions('c', 'USER', 'ada@example.com', 'Ada', ['abc']));
        self::assertSame(['challenge' => 'c', 'rpId' => 'example.com', 'timeout' => 300_000, 'allowCredentials' => [], 'userVerification' => 'required'], new RelyingParty('example.com', 'Example', ['https://example.com'], true)->requestOptions('c'));
        self::assertSame(43, \strlen(RelyingParty::challenge()));
        self::assertNotSame(RelyingParty::challenge(), RelyingParty::challenge());
    }

    #[DoesNotPerformAssertions]
    public function testARelyingPartyHasAnIdentifierANameAndAnOrigin(): void
    {
        foreach ([['', 'n', ['o']], ['i', '', ['o']], ['i', 'n', []]] as [$id, $name, $origins]) {
            try {
                new RelyingParty($id, $name, $origins);
                self::fail('Accepted.');
            } catch (InvalidValue) {
            }
        }
    }

    public function testARegistrationAndASignIn(): void
    {
        $party = new RelyingParty('example.com', 'Example', ['https://example.com']);
        $authenticator = new Authenticator(flags: 0x1D);
        $response = json_decode($authenticator->register('chal'), true);
        self::assertIsArray($response);
        self::assertIsArray($response['response']);
        self::assertIsString($response['response']['clientDataJSON']);
        self::assertIsString($response['response']['attestationObject']);
        $credential = $party->verifyRegistration(Base64Url::decode($response['response']['clientDataJSON']), Base64Url::decode($response['response']['attestationObject']), 'chal');

        self::assertSame($authenticator->credentialId, $credential->credentialId);
        self::assertSame($authenticator->coseKey(), $credential->coseKey);
        self::assertSame(0, $credential->signCount);
        self::assertTrue($credential->userVerified);
        self::assertTrue($credential->backupEligible);
        self::assertTrue($credential->backedUp);
        self::assertSame(16, \strlen($credential->aaguid));

        [$clientData, $authData, $signature] = $authenticator->assertion('next');
        $assertion = $party->verifyAssertion($clientData, $authData, $signature, 'next', $credential->coseKey, 0);

        self::assertSame(1, $assertion->signCount);
        self::assertTrue($assertion->userVerified);
        self::assertTrue($assertion->backedUp);
    }

    public function testAnAuthenticatorWithoutCounterSignsInAgain(): void
    {
        $party = new RelyingParty('example.com', 'Example', ['https://example.com']);
        $authenticator = new Authenticator(counts: false, flags: 0x01);
        [$clientData, $authData, $signature] = $authenticator->assertion('c');
        $assertion = $party->verifyAssertion($clientData, $authData, $signature, 'c', $authenticator->coseKey(), 0);

        self::assertSame(0, $assertion->signCount);
        self::assertFalse($assertion->userVerified);
        self::assertFalse($assertion->backedUp);
    }

    public function testAClonedAuthenticatorIsRefused(): void
    {
        $party = new RelyingParty('example.com', 'Example', ['https://example.com']);
        $authenticator = new Authenticator();
        [$clientData, $authData, $signature] = $authenticator->assertion('c');

        foreach ([1, 2] as $stored) {
            try {
                $party->verifyAssertion($clientData, $authData, $signature, 'c', $authenticator->coseKey(), $stored);
                self::fail('Accepted a counter that did not move forward.');
            } catch (VerificationFailed $verificationFailed) {
                self::assertStringContainsString('cloned', $verificationFailed->getMessage());
            }
        }

        $counterless = new Authenticator(counts: false);
        [$clientData, $authData, $signature] = $counterless->assertion('c');
        $this->expectException(VerificationFailed::class);
        $party->verifyAssertion($clientData, $authData, $signature, 'c', $counterless->coseKey(), 3);
    }

    public function testASignInIsRefusedForEachBrokenProof(): void
    {
        $party = new RelyingParty('example.com', 'Example', ['https://example.com'], true);
        $authenticator = new Authenticator(flags: 0x05);
        [$clientData, $authData, $signature] = $authenticator->assertion('c');
        $cases = [
            'signature' => [$clientData, $authData, strrev($signature), 'does not verify'],
            'relying party' => [$clientData, hash('sha256', 'evil.example', true).substr($authData, 32), $signature, 'another relying party'],
            'not present' => [$clientData, substr($authData, 0, 32)."\x04".substr($authData, 33), $signature, 'not present'],
            'not verified' => [$clientData, substr($authData, 0, 32)."\x01".substr($authData, 33), $signature, 'not verified'],
        ];

        foreach ($cases as $case => [$client, $data, $sig, $message]) {
            try {
                $party->verifyAssertion($client, $data, $sig, 'c', $authenticator->coseKey(), 0);
                self::fail($case);
            } catch (VerificationFailed $verificationFailed) {
                self::assertStringContainsString($message, $verificationFailed->getMessage(), $case);
            }
        }
    }

    public function testARegistrationIsRefusedForEachBrokenPart(): void
    {
        $party = new RelyingParty('example.com', 'Example', ['https://example.com']);
        $authenticator = new Authenticator();
        $clientData = $authenticator->clientData('webauthn.create', 'c');
        $attested = random_bytes(16).pack('n', 3).'abc'.$authenticator->coseKey();

        foreach ([
            'too long' => [str_repeat("\x00", 16_385), MalformedInput::class],
            'not a map' => [Cbor::encode([1]), MalformedInput::class],
            'no format' => [Cbor::encode(['authData' => Cbor::bytes($authenticator->authenticatorData(0x41, $attested))]), MalformedInput::class],
            'no credential' => [Cbor::encode(['fmt' => 'none', 'attStmt' => [], 'authData' => Cbor::bytes($authenticator->authenticatorData(0x01))]), VerificationFailed::class],
            'bad key' => [Cbor::encode(['fmt' => 'none', 'attStmt' => [], 'authData' => Cbor::bytes($authenticator->authenticatorData(0x41, random_bytes(16).pack('n', 3).'abc'.Cbor::encode([1 => 2, 3 => -35])))]), MalformedInput::class],
        ] as $case => [$object, $exception]) {
            try {
                $party->verifyRegistration($clientData, $object, 'c');
                self::fail($case);
            } catch (MalformedInput|VerificationFailed $refused) {
                self::assertInstanceOf($exception, $refused, $case);
            }
        }

        $this->expectException(VerificationFailed::class);
        $party->verifyRegistration($authenticator->clientData('webauthn.get', 'c'), Cbor::encode(['fmt' => 'none', 'attStmt' => [], 'authData' => Cbor::bytes($authenticator->authenticatorData(0x41, $attested))]), 'c');
    }
}
