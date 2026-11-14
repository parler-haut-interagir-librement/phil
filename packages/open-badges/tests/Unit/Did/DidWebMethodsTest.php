<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Tests\Unit\Did;

use DateTimeImmutable;
use LogicException;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Did\DidWebMethods;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Jwt\JwtSigner;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaKey;
use Phil\OpenBadges\Key\RsaSigner;
use Phil\OpenBadges\Verification\CredentialVerifier;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DidWebMethods::class)]
final class DidWebMethodsTest extends TestCase
{
    private const string DID = 'did:web:issuer.example.org:badges';

    private const string METHOD = self::DID.'#key-1';

    private const string ID = 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f';

    public function testAnRs256CredentialIsVerifiedWithTheKeyOfTheIssuersDocument(): void
    {
        $signer = RsaSigner::fromPem($this->rsa());
        $fetcher = new Documents([self::DID => self::document(['id' => '#key-1', 'type' => 'JsonWebKey', 'controller' => self::DID, 'publicKeyJwk' => $signer->publicJwk()])]);
        $token = new JwtSigner($signer, self::METHOD)->sign(['id' => self::ID, 'issuer' => ['id' => self::DID, 'type' => ['Profile']], 'validFrom' => '2026-11-07T00:00:00Z']);

        $verified = new CredentialVerifier(new DidWebMethods($fetcher))->verify($token, new DateTimeImmutable('2026-11-08 UTC'));

        self::assertSame(self::DID, $verified->issuer());
        self::assertSame(['https://issuer.example.org/badges/did.json'], $fetcher->fetched);
    }

    public function testAnEd25519KeyIsReadAsAMultikeyOrAJsonWebKey(): void
    {
        $signer = Ed25519Signer::fromSeed(str_repeat("\x07", 32));
        $key = $signer->verificationKey();
        self::assertInstanceOf(Ed25519Key::class, $key);
        $multikey = ['id' => self::METHOD, 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $key->toMultibase()];
        $jwk = ['id' => '#key-1', 'type' => 'JsonWebKey2020', 'controller' => self::DID, 'publicKeyJwk' => $key->toJwk()];

        foreach ([$multikey, $jwk] as $method) {
            $found = new DidWebMethods(new Documents([self::DID => self::document($method)]))->key(self::METHOD);
            self::assertInstanceOf(Ed25519Key::class, $found);
            self::assertSame($key->publicKey, $found->publicKey);
        }
    }

    public function testAMethodEmbeddedInAssertionMethodIsRead(): void
    {
        $key = Ed25519Signer::fromSeed(str_repeat("\x07", 32))->verificationKey();
        self::assertInstanceOf(Ed25519Key::class, $key);
        $document = ['id' => self::DID, 'assertionMethod' => ['#other', ['id' => '#key-1', 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $key->toMultibase()]]];

        self::assertInstanceOf(Ed25519Key::class, new DidWebMethods(new Documents([self::DID => (string) json_encode($document)]))->key(self::METHOD));
    }

    public function testTheEmbeddedMethodIsPreferredToTheListedOne(): void
    {
        $embedded = Ed25519Signer::fromSeed(str_repeat("\x07", 32))->verificationKey();
        $listed = Ed25519Signer::fromSeed(str_repeat("\x08", 32))->verificationKey();
        self::assertInstanceOf(Ed25519Key::class, $embedded);
        self::assertInstanceOf(Ed25519Key::class, $listed);
        $document = [
            'id' => self::DID,
            'verificationMethod' => [['id' => self::METHOD, 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $listed->toMultibase()]],
            'assertionMethod' => [['id' => self::METHOD, 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $embedded->toMultibase()]],
        ];
        $found = new DidWebMethods(new Documents([self::DID => (string) json_encode($document)]))->key(self::METHOD);

        self::assertInstanceOf(Ed25519Key::class, $found);
        self::assertSame($embedded->publicKey, $found->publicKey);
    }

    public function testOtherMethodsGoToTheMethodsGiven(): void
    {
        $fetcher = new Documents([]);
        $key = Ed25519Signer::fromSeed(str_repeat("\x07", 32))->verificationKey();
        self::assertInstanceOf(Ed25519Key::class, $key);
        $didKey = 'did:key:'.$key->toMultibase();

        self::assertInstanceOf(Ed25519Key::class, new DidWebMethods($fetcher)->key($didKey));
        self::assertSame($key, new DidWebMethods($fetcher, new DidKeyMethods(['https://example.org/issuer#key-1' => $key]))->key('https://example.org/issuer#key-1'));
        self::assertSame([], $fetcher->fetched);

        $this->expectExceptionObject(InvalidCredential::because('The verification method "https://example.org/issuer#key-1" is not one the verifier knows.'));
        new DidWebMethods($fetcher)->key('https://example.org/issuer#key-1');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedDocuments(): iterable
    {
        $key = Ed25519Signer::fromSeed(str_repeat("\x07", 32))->verificationKey();
        Assert::assertInstanceOf(Ed25519Key::class, $key);
        $method = ['id' => '#key-1', 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $key->toMultibase()];
        $notListed = \sprintf('The DID document of "%s" lists no verification method "%s" for its assertions.', self::DID, self::METHOD);
        $notItsDocument = \sprintf('The document served for "%s" is not its DID document.', self::DID);
        $oneKey = 'A verification method gives its key as one "publicKeyMultibase" or one "publicKeyJwk".';

        yield 'not JSON' => ['{', \sprintf('The DID document of "%s" is not JSON of 16 levels at most.', self::DID)];
        yield 'too deep' => [str_repeat('[', 17).str_repeat(']', 17), \sprintf('The DID document of "%s" is not JSON of 16 levels at most.', self::DID)];
        yield 'too large' => [str_pad('{}', DidWebMethods::MAX_BYTES + 1), 'A DID document is 262144 bytes at most.'];
        yield 'a list' => ['[]', $notItsDocument];
        yield 'a text' => ['"did"', $notItsDocument];
        yield 'another identifier' => [(string) json_encode(['@context' => ['https://www.w3.org/ns/did/v1'], 'id' => 'did:web:other.example.org', 'verificationMethod' => [$method], 'assertionMethod' => ['#key-1']]), $notItsDocument];
        yield 'no identifier' => [(string) json_encode(['verificationMethod' => [$method], 'assertionMethod' => ['#key-1']]), $notItsDocument];
        yield 'not asserted' => [(string) json_encode(['id' => self::DID, 'verificationMethod' => [$method], 'authentication' => ['#key-1']]), $notListed];
        yield 'asserted but not listed' => [(string) json_encode(['id' => self::DID, 'assertionMethod' => ['#key-1']]), $notListed];
        yield 'asserted as another' => [(string) json_encode(['id' => self::DID, 'verificationMethod' => [$method], 'assertionMethod' => ['#key-2', 7]]), $notListed];
        yield 'an assertion that is not a list' => [(string) json_encode(['id' => self::DID, 'verificationMethod' => [$method], 'assertionMethod' => ['a' => '#key-1']]), $notListed];
        yield 'methods that are not a list' => [(string) json_encode(['id' => self::DID, 'verificationMethod' => ['a' => $method], 'assertionMethod' => ['#key-1']]), $notListed];
        yield 'a method without identifier' => [(string) json_encode(['id' => self::DID, 'verificationMethod' => [['controller' => self::DID]], 'assertionMethod' => ['#key-1', ['type' => 'Multikey']]]), $notListed];
        yield 'another controller' => [self::document(['controller' => 'did:web:attacker.example'] + $method), \sprintf('The verification method "%s" is not controlled by "%s".', self::METHOD, self::DID)];
        yield 'no controller' => [self::document(array_diff_key($method, ['controller' => true])), \sprintf('The verification method "%s" is not controlled by "%s".', self::METHOD, self::DID)];
        yield 'no key' => [self::document(array_diff_key($method, ['publicKeyMultibase' => true])), $oneKey];
        yield 'two keys' => [self::document($method + ['publicKeyJwk' => $key->toJwk()]), $oneKey];
        yield 'a key that is not text' => [self::document(['publicKeyMultibase' => 7] + $method), $oneKey];
        yield 'a private key' => [self::document(array_diff_key($method, ['publicKeyMultibase' => true]) + ['publicKeyJwk' => $key->toJwk() + ['d' => 'secret']]), 'A DID document publishes public keys only; this one publishes a private key.'];
        yield 'a private RSA key' => [self::document(array_diff_key($method, ['publicKeyMultibase' => true]) + ['publicKeyJwk' => ['kty' => 'RSA', 'n' => 'AQAB', 'e' => 'AQAB', 'p' => 'x']]), 'A DID document publishes public keys only; this one publishes a private key.'];
        yield 'a private RSA factor' => [self::document(array_diff_key($method, ['publicKeyMultibase' => true]) + ['publicKeyJwk' => ['kty' => 'RSA', 'n' => 'AQAB', 'e' => 'AQAB', 'q' => 'x']]), 'A DID document publishes public keys only; this one publishes a private key.'];
        yield 'an elliptic curve key' => [self::document(array_diff_key($method, ['publicKeyMultibase' => true]) + ['publicKeyJwk' => ['kty' => 'EC', 'crv' => 'P-256', 'x' => 'a', 'y' => 'b']]), 'A verification method holds an Ed25519 or an RSA JSON Web Key.'];
        yield 'a weak RSA key' => [self::document(array_diff_key($method, ['publicKeyMultibase' => true]) + ['publicKeyJwk' => ['kty' => 'RSA', 'n' => 'AQAB', 'e' => 'AQAB']]), \sprintf('The key of the verification method "%s" is refused: The modulus of an RSA JSON Web Key has 2048 to 8192 bits; this one has 17.', self::METHOD)];
        yield 'an X25519 key' => [self::document(array_diff_key($method, ['publicKeyMultibase' => true]) + ['publicKeyJwk' => ['kty' => 'OKP', 'crv' => 'X25519', 'x' => 'AQAB']]), \sprintf('The key of the verification method "%s" is refused: An Ed25519 JSON Web Key has the type "OKP", the curve "Ed25519" and its "x".', self::METHOD)];
    }

    #[DataProvider('refusedDocuments')]
    public function testADocumentThatDoesNotGiveTheKeyIsRefused(string $document, string $reason): void
    {
        $this->expectExceptionObject(InvalidCredential::because($reason));
        new DidWebMethods(new Documents([self::DID => $document]))->key(self::METHOD);
    }

    public function testAMethodNamesAKeyOfItsDocument(): void
    {
        $fetcher = new Documents([]);

        try {
            new DidWebMethods($fetcher)->key(self::DID);
            self::fail('A method without fragment was read.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame(\sprintf('The verification method "%s" names no key of its DID document.', self::DID), $invalidCredential->getMessage());
        }

        try {
            new DidWebMethods($fetcher)->key(self::DID.'#');
            self::fail('A method with an empty fragment was read.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame(\sprintf('The verification method "%s#" names no key of its DID document.', self::DID), $invalidCredential->getMessage());
        }

        try {
            new DidWebMethods($fetcher)->key('did:web:127.0.0.1#key-1');
            self::fail('A method of an IP address was read.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertStringStartsWith('"did:web:127.0.0.1" names no host a did:web resolves', $invalidCredential->getMessage());
        }

        self::assertSame([], $fetcher->fetched);
    }

    public function testTheMethodIsLookedUpWholeAndAsAFragment(): void
    {
        $key = Ed25519Signer::fromSeed(str_repeat("\x07", 32))->verificationKey();
        self::assertInstanceOf(Ed25519Key::class, $key);
        $document = ['id' => self::DID, 'verificationMethod' => [
            ['id' => self::DID.'#key-2', 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $key->toMultibase()],
        ], 'assertionMethod' => ['#key-2']];

        self::assertInstanceOf(Ed25519Key::class, new DidWebMethods(new Documents([self::DID => (string) json_encode($document)]))->key(self::DID.'#key-2'));
        self::assertInstanceOf(RsaKey::class, new DidWebMethods(new Documents([self::DID => self::document(['id' => '#key-1', 'controller' => self::DID, 'publicKeyJwk' => RsaSigner::fromPem($this->rsa())->publicJwk()])]))->key(self::METHOD));
    }

    public function testTheServiceKeepsNothingBetweenTwoCalls(): void
    {
        $key = Ed25519Signer::fromSeed(str_repeat("\x07", 32))->verificationKey();
        self::assertInstanceOf(Ed25519Key::class, $key);
        $fetcher = new Documents([self::DID => self::document(['id' => '#key-1', 'controller' => self::DID, 'publicKeyMultibase' => $key->toMultibase()])]);
        $methods = new DidWebMethods($fetcher);

        $methods->key(self::METHOD);
        $methods->key(self::METHOD);

        self::assertCount(2, $fetcher->fetched);
        self::assertSame(262_144, DidWebMethods::MAX_BYTES);
    }

    /**
     * @param array<string, mixed> $method
     */
    private static function document(array $method): string
    {
        return (string) json_encode(['@context' => ['https://www.w3.org/ns/did/v1'], 'id' => self::DID, 'verificationMethod' => [$method], 'assertionMethod' => ['#key-1']]);
    }

    private function rsa(): string
    {
        static $pem = null;

        if (null === $pem) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
            if (false === $key) {
                throw new LogicException();
            }

            openssl_pkey_export($key, $pem);
        }

        return \is_string($pem) ? $pem : throw new LogicException();
    }
}
