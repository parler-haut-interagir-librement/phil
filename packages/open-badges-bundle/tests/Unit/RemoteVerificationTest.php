<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\DataIntegrity\DidKeyMethods;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Key\RsaKey;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Verification\RemoteVerificationMethods;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\FrozenClock;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\Kit;
use Phil\OpenBadgesBundle\Tests\Unit\Fixture\RsaKeys;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\SafeHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(RemoteVerificationMethods::class)]
final class RemoteVerificationTest extends TestCase
{
    private const string PARTNER = 'https://partner.example/issuer';

    private const string METHOD = self::PARTNER.'#key-1';

    private const string DID = 'did:web:partner.example';

    /** Public addresses of the documentation range, pinned so that no test resolves a name. */
    private const array RESOLVE = [
        'partner.example' => '93.184.215.14',
        'other.example' => '93.184.215.15',
        'internal.example' => '10.0.0.7',
    ];

    /** @var list<string> */
    private array $requested = [];

    /** @var list<array<array-key, mixed>> */
    private array $options = [];

    private ArrayAdapter $cache;

    #[Override]
    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
    }

    public function testATokenOfAnotherIssuerIsVerifiedByTheKeyOfItsProfileReadOnce(): void
    {
        $verifier = $this->verifier([$this->profile()]);

        self::assertSame(self::PARTNER, $verifier->verify(new Securing($this->partner(), self::METHOD, Securing::JWT, new FrozenClock())->secure($this->credential())->content)->issuer());
        self::assertSame(self::PARTNER, $verifier->verify(new Securing($this->partner(), self::METHOD, Securing::JWT, new FrozenClock())->secure($this->credential())->content)->issuer());
        self::assertSame([self::PARTNER], $this->requested);
        self::assertSame('application/ld+json, application/json;q=0.9', $this->header('accept'));
        self::assertSame('PhilOpenBadges/0.1', $this->header('user-agent'));
        self::assertTrue($this->cache->hasItem(RemoteVerificationMethods::CACHE_PREFIX.hash('sha256', self::PARTNER)));
    }

    public function testADocumentOfAnotherIssuerIsVerifiedByAJsonWebKey(): void
    {
        $profile = $this->profileDocument([['id' => self::METHOD, 'type' => 'JsonWebKey', 'controller' => self::PARTNER, 'publicKeyJwk' => $this->partner()->publicJwk()]]);
        $document = new Securing($this->partner(), self::METHOD, Securing::DATA_INTEGRITY, new FrozenClock())->secure($this->credential())->content;

        self::assertSame(self::PARTNER, $this->verifier([$this->json($profile)])->verify($document, new DateTimeImmutable('2026-12-01 UTC'))->issuer());
    }

    public function testTheKeysTheApplicationKnowsAreAskedFirst(): void
    {
        $verifier = $this->verifier([]);

        self::assertSame(Kit::ISSUER, $verifier->verify(Kit::token())->issuer());
        self::assertSame([], $this->requested);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function methodsNotRead(): iterable
    {
        yield 'a did:web with a slash' => ['did:web:partner.example/issuer#key-1'];
        yield 'a did:web with a dot-dot segment' => ['did:web:partner.example:..#key-1'];
        yield 'a did:web on an IP address' => ['did:web:93.184.215.14#key-1'];
        yield 'plain HTTP' => ['http://partner.example/issuer#key-1'];
        yield 'an IP address' => ['https://93.184.215.14/issuer#key-1'];
        yield 'not an address' => ['urn:uuid:1234#key-1'];
        yield 'a space before' => [' https://partner.example/issuer#key-1'];
    }

    #[DataProvider('methodsNotRead')]
    public function testAMethodThatIsNoHttpsAddressIsUnknown(string $method): void
    {
        try {
            $this->methods([])->key($method);
            self::fail('Unknown.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame(\sprintf('The verification method "%s" is not one the verifier knows.', $method), $invalidCredential->getMessage());
        }

        self::assertSame([], $this->requested);
    }

    public function testOnlyTheTrustedIssuersAreRead(): void
    {
        $methods = $this->methods([$this->profile()], [self::PARTNER]);

        self::assertInstanceOf(Ed25519Key::class, $methods->key(self::METHOD));

        try {
            $methods->key('https://other.example/issuer#key-1');
            self::fail('Not trusted.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame('The issuer "https://other.example/issuer" is not one the verifier trusts.', $invalidCredential->getMessage());
        }

        self::assertSame([self::PARTNER], $this->requested);
    }

    /**
     * @return iterable<string, array{MockResponse, string}>
     */
    public static function profilesRefused(): iterable
    {
        $issuer = '"https://partner.example/issuer"';

        yield 'an error' => [new MockResponse('{}', ['http_code' => 500, 'response_headers' => ['Content-Type' => 'application/json']]), 'The profile of the issuer '.$issuer.' could not be read: the answer was 500.'];
        yield 'a redirect' => [new MockResponse('', ['http_code' => 304]), 'The profile of the issuer '.$issuer.' could not be read: the answer was 304.'];
        yield 'a page' => [new MockResponse('<html></html>', ['response_headers' => ['Content-Type' => 'text/html']]), 'The profile of the issuer '.$issuer.' is not JSON within the bytes the verifier reads.'];
        yield 'not JSON' => [new MockResponse('{"id":', ['response_headers' => ['Content-Type' => 'application/json']]), 'The profile of the issuer '.$issuer.' is not JSON within the bytes the verifier reads.'];
        yield 'a string' => [new MockResponse('"https://partner.example/issuer"', ['response_headers' => ['Content-Type' => 'application/json']]), 'The profile of the issuer '.$issuer.' is not JSON within the bytes the verifier reads.'];
        yield 'too deep' => [new MockResponse(str_repeat('[', 40).str_repeat(']', 40), ['response_headers' => ['Content-Type' => 'application/json']]), 'The profile of the issuer '.$issuer.' is not JSON within the bytes the verifier reads.'];
        yield 'too large' => [new MockResponse(['{"id":"https://partner.example/issuer","name":"', str_repeat('a', 2000), '"}'], ['response_headers' => ['Content-Type' => 'application/ld+json']]), 'The profile of the issuer '.$issuer.' is not JSON within the bytes the verifier reads.'];
        yield 'another profile' => [new MockResponse('{"id":"https://other.example/issuer"}', ['response_headers' => ['Content-Type' => 'application/json']]), 'The profile read at '.$issuer.' is not the profile of that issuer: its "id" differs.'];
        yield 'no identifier' => [new MockResponse('{"name":"Partner"}', ['response_headers' => ['Content-Type' => 'application/json']]), 'The profile read at '.$issuer.' is not the profile of that issuer: its "id" differs.'];
    }

    #[DataProvider('profilesRefused')]
    public function testAProfileThatDoesNotHoldIsRefusedAndNotAskedAgainAtOnce(MockResponse $response, string $message): void
    {
        $methods = $this->methods([$response], limits: new Limits(maxBytes: 1024));

        for ($attempt = 0; $attempt < 2; ++$attempt) {
            try {
                $methods->key(self::METHOD);
                self::fail('Refused.');
            } catch (InvalidCredential $invalidCredential) {
                self::assertSame($message, $invalidCredential->getMessage());
            }
        }

        self::assertSame([self::PARTNER], $this->requested);
    }

    public function testAProfileRedirectedAwayFromHttpsIsRefused(): void
    {
        $methods = $this->methods([new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'http://partner.example/issuer']]), $this->profile()]);

        $this->expectExceptionObject(InvalidCredential::because('The profile of the issuer "https://partner.example/issuer" was redirected away from HTTPS.'));
        $methods->key(self::METHOD);
    }

    public function testAProfileRedirectedOverHttpsIsRead(): void
    {
        $methods = $this->methods([new MockResponse('', ['http_code' => 301, 'response_headers' => ['Location' => 'https://other.example/issuer']]), $this->profile()]);

        self::assertSame($this->partner()->publicJwk(), $methods->key(self::METHOD)->jwk()->all());
        self::assertSame([self::PARTNER, 'https://other.example/issuer'], $this->requested);
    }

    public function testAnIssuerThatDoesNotAnswerIsRefusedClearly(): void
    {
        $methods = $this->methods(static fn (): MockResponse => throw new TransportException('Connection refused.'));

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The profile of the issuer "https://partner.example/issuer" could not be read: ');

        $methods->key(self::METHOD);
    }

    public function testAnIssuerOnAPrivateNetworkIsNeverReached(): void
    {
        $methods = $this->methods([$this->profile()]);

        try {
            $methods->key('https://internal.example/issuer#key-1');
            self::fail('Refused.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertStringStartsWith('The profile of the issuer "https://internal.example/issuer" could not be read: ', $invalidCredential->getMessage());
            self::assertStringContainsString('private', $invalidCredential->getMessage());
        }
    }

    public function testARefusalIsKeptForItsOwnTimeAndKeysForTheirs(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $this->cache = new ArrayAdapter(clock: $clock);
        $methods = $this->methods([new MockResponse('', ['http_code' => 404]), $this->profile(), $this->profile()], ttl: 900, failureTtl: 30);

        foreach ([0, 29, 31] as $second) {
            $clock->modify(\sprintf('2026-11-08 10:00:%02d UTC', $second));

            try {
                $methods->key(self::METHOD);
            } catch (InvalidCredential) {
            }
        }

        self::assertCount(2, $this->requested);
        $clock->modify('2026-11-08 10:15:30 UTC');
        $methods->key(self::METHOD);
        self::assertCount(2, $this->requested);
        $clock->modify('2026-11-08 10:15:32 UTC');
        $methods->key(self::METHOD);
        self::assertCount(3, $this->requested);
    }

    public function testOnlyTheKeysOfTheIssuerThatAssertAreKept(): void
    {
        $key = Ed25519Key::fromMultibase($this->partner()->verificationKey()->toMultibase());
        $other = Ed25519Signer::fromSeed(str_repeat("\3", 32))->verificationKey()->toMultibase();
        $profile = $this->profileDocument([
            ['id' => self::PARTNER.'#key-1', 'type' => 'Multikey', 'publicKeyMultibase' => $key->toMultibase()],
            ['id' => self::PARTNER.'#not-asserting', 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $other],
            ['id' => self::PARTNER.'#someone-else', 'type' => 'Multikey', 'controller' => 'https://other.example/issuer', 'publicKeyMultibase' => $other],
            ['id' => 'https://other.example/issuer#key-1', 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $other],
            ['id' => self::PARTNER.'#broken', 'type' => 'Multikey', 'publicKeyMultibase' => 'z6Mk'],
            ['id' => self::PARTNER.'#bad-jwk', 'type' => 'JsonWebKey', 'publicKeyJwk' => ['kty' => 'RSA']],
            ['id' => self::PARTNER.'#nothing', 'type' => 'Multikey'],
            ['id' => 12],
            'a string',
        ]);
        $profile['assertionMethod'] = [self::PARTNER.'#key-1', ['id' => self::PARTNER.'#someone-else'], self::PARTNER.'#broken', self::PARTNER.'#bad-jwk', self::PARTNER.'#nothing', 'https://other.example/issuer#key-1'];
        $methods = $this->methods([$this->json($profile)]);

        self::assertSame($key->toMultibase(), $methods->key(self::PARTNER.'#key-1') instanceof Ed25519Key ? $methods->key(self::PARTNER.'#key-1')->toMultibase() : null);

        foreach (['#not-asserting', '#someone-else', '#broken', '#bad-jwk', '#nothing', '#key-2'] as $fragment) {
            try {
                $methods->key(self::PARTNER.$fragment);
                self::fail($fragment.' is refused.');
            } catch (InvalidCredential $invalidCredential) {
                self::assertSame(\sprintf('The profile of the issuer "%s" publishes no key "%s".', self::PARTNER, self::PARTNER.$fragment), $invalidCredential->getMessage());
            }
        }

        self::assertSame([self::PARTNER], $this->requested);
    }

    public function testWithoutAssertionMethodsEveryKeyOfTheIssuerHolds(): void
    {
        $other = Ed25519Signer::fromSeed(str_repeat("\3", 32))->verificationKey()->toMultibase();
        $methods = $this->methods([$this->json($this->profileDocument([['id' => self::PARTNER.'#key-2', 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $other]]))]);

        self::assertInstanceOf(Ed25519Key::class, $methods->key(self::PARTNER.'#key-2'));
    }

    public function testAtMostTwentyMethodsAreRead(): void
    {
        $other = Ed25519Signer::fromSeed(str_repeat("\3", 32))->verificationKey()->toMultibase();
        $methods = [];

        for ($index = 1; $index <= RemoteVerificationMethods::MAX_METHODS + 1; ++$index) {
            $methods[] = ['id' => self::PARTNER.'#key-'.$index, 'type' => 'Multikey', 'publicKeyMultibase' => $other];
        }

        $remote = $this->methods([$this->json($this->profileDocument($methods))]);

        self::assertInstanceOf(Ed25519Key::class, $remote->key(self::PARTNER.'#key-'.RemoteVerificationMethods::MAX_METHODS));
        $this->expectException(InvalidCredential::class);
        $remote->key(self::PARTNER.'#key-'.(RemoteVerificationMethods::MAX_METHODS + 1));
    }

    public function testLocalhostIsReadOnlyWhenAllowed(): void
    {
        try {
            $this->methods([])->key('https://badges.localhost/issuer#key-1');
            self::fail('Unknown.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertSame('The verification method "https://badges.localhost/issuer#key-1" is not one the verifier knows.', $invalidCredential->getMessage());
        }

        try {
            $this->methods([], allowLocalhost: true)->key('https://badges.localhost/issuer#key-1');
            self::fail('Refused by the guard.');
        } catch (InvalidCredential $invalidCredential) {
            self::assertStringStartsWith('The profile of the issuer "https://badges.localhost/issuer" could not be read: ', $invalidCredential->getMessage());
        }
    }

    public function testTheKeysAreKeptAnHourAndARefusalAMinuteByDefault(): void
    {
        $clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $this->cache = new ArrayAdapter(clock: $clock);
        $methods = $this->byDefault([new MockResponse('', ['http_code' => 404]), $this->profile(), $this->profile()]);

        foreach (['10:00:00', '10:00:59', '10:01:00'] as $time) {
            $clock->modify('2026-11-08 '.$time.' UTC');

            try {
                $methods->key(self::METHOD);
            } catch (InvalidCredential) {
            }
        }

        self::assertCount(2, $this->requested);

        $clock->modify('2026-11-08 11:00:59 UTC');
        $methods->key(self::METHOD);
        self::assertCount(2, $this->requested);

        $clock->modify('2026-11-08 11:01:00 UTC');
        $methods->key(self::METHOD);
        self::assertCount(3, $this->requested);
    }

    public function testLocalhostIsNotReadByDefault(): void
    {
        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The verification method "https://badges.localhost/issuer#key-1" is not one the verifier knows.');

        $this->byDefault([])->key('https://badges.localhost/issuer#key-1');
    }

    public function testABodyCutShortIsNotReadEvenWhenItsBytesAreAProfile(): void
    {
        $profile = new Issuer(self::PARTNER, 'Partner')->profileDocument([self::METHOD => Ed25519Key::fromMultibase($this->partner()->verificationKey()->toMultibase())]);
        $body = json_encode($profile, \JSON_THROW_ON_ERROR);
        $methods = $this->methods([new MockResponse($body.str_repeat(' ', 4096), ['response_headers' => ['Content-Type' => 'application/ld+json']])], limits: new Limits(maxBytes: 1024));

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The profile of the issuer "https://partner.example/issuer" is not JSON within the bytes the verifier reads.');

        $methods->key(self::METHOD);
    }

    public function testAProfileIsReadUpToThirtyTwoLevelsOfDepth(): void
    {
        $key = $this->partner()->verificationKey()->toMultibase();

        foreach ([32 => true, 33 => false] as $depth => $read) {
            $padding = [];

            for ($level = 3; $level < $depth; ++$level) {
                $padding = [$padding];
            }

            $profile = $this->profileDocument([['id' => self::METHOD, 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $key]]);
            $profile['padding'] = $padding;
            $this->cache = new ArrayAdapter();
            $methods = $this->methods([$this->json($profile)]);

            if ($read) {
                self::assertInstanceOf(Ed25519Key::class, $methods->key(self::METHOD));

                continue;
            }

            try {
                $methods->key(self::METHOD);
                self::fail('Too deep.');
            } catch (InvalidCredential $invalidCredential) {
                self::assertSame('The profile of the issuer "https://partner.example/issuer" is not JSON within the bytes the verifier reads.', $invalidCredential->getMessage());
            }
        }
    }

    public function testAnAssertionMethodMayBeAnObjectAndAMethodThatIsNotListedIsNotKept(): void
    {
        $key = $this->partner()->verificationKey()->toMultibase();
        $other = Ed25519Signer::fromSeed(str_repeat("\3", 32))->verificationKey()->toMultibase();
        $profile = $this->profileDocument([
            ['id' => self::PARTNER.'#key-1', 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $key],
            ['id' => self::PARTNER.'#key-2', 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $other],
        ]);
        $profile['assertionMethod'] = [['id' => self::PARTNER.'#key-1', 'type' => 'Multikey']];
        $methods = $this->methods([$this->json($profile)]);

        self::assertInstanceOf(Ed25519Key::class, $methods->key(self::PARTNER.'#key-1'));
        $this->expectException(InvalidCredential::class);
        $methods->key(self::PARTNER.'#key-2');
    }

    public function testTheIdentifierOfAMethodIsAFragmentOfTheIssuer(): void
    {
        $key = $this->partner()->verificationKey()->toMultibase();
        $methods = $this->methods([$this->json($this->profileDocument([['id' => self::PARTNER, 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $key]]))]);

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The profile of the issuer "https://partner.example/issuer" publishes no key "https://partner.example/issuer".');

        $methods->key(self::PARTNER);
    }

    public function testAMethodThatDoesNotHoldDoesNotStopTheOnesAfterIt(): void
    {
        $key = $this->partner()->verificationKey()->toMultibase();
        $methods = $this->methods([$this->json($this->profileDocument(['a string', ['id' => 12], ['id' => self::PARTNER.'#key-1', 'type' => 'Multikey', 'controller' => self::PARTNER, 'publicKeyMultibase' => $key]]))]);

        self::assertInstanceOf(Ed25519Key::class, $methods->key(self::METHOD));
    }

    public function testATokenSignedWithRs256IsVerifiedByTheRsaJsonWebKeyOfItsProfile(): void
    {
        $signer = RsaKeys::signer();
        $profile = $this->profileDocument([['id' => self::METHOD, 'type' => 'JsonWebKey', 'controller' => self::PARTNER, 'publicKeyJwk' => [...$signer->publicJwk(), 'kid' => 'key-1']]]);
        $token = new Securing($signer, self::METHOD, Securing::JWT, new FrozenClock())->secure($this->credential())->content;
        $verifier = $this->verifier([$this->json($profile)]);

        self::assertSame(self::PARTNER, $verifier->verify($token)->issuer());
        self::assertSame(self::PARTNER, $verifier->verify($token)->issuer());
        self::assertSame([self::PARTNER], $this->requested);
        self::assertInstanceOf(RsaKey::class, $this->methods([$this->json($profile)])->key(self::METHOD));
    }

    public function testAnRsaKeyTooSmallOrPrivateIsNotKept(): void
    {
        $jwk = RsaKeys::signer()->publicJwk();
        $profile = $this->profileDocument([
            ['id' => self::PARTNER.'#small', 'type' => 'JsonWebKey', 'publicKeyJwk' => ['kty' => 'RSA', 'n' => 'AQAB', 'e' => 'AQAB']],
            ['id' => self::PARTNER.'#private', 'type' => 'JsonWebKey', 'publicKeyJwk' => [...$jwk, 'd' => 'AQAB']],
            ['id' => self::PARTNER.'#key-1', 'type' => 'JsonWebKey', 'publicKeyJwk' => $jwk],
        ]);
        $methods = $this->methods([$this->json($profile)]);

        self::assertInstanceOf(RsaKey::class, $methods->key(self::PARTNER.'#key-1'));

        foreach (['#small', '#private'] as $fragment) {
            try {
                $methods->key(self::PARTNER.$fragment);
                self::fail($fragment.' is refused.');
            } catch (InvalidCredential $invalidCredential) {
                self::assertSame(\sprintf('The profile of the issuer "%s" publishes no key "%s".', self::PARTNER, self::PARTNER.$fragment), $invalidCredential->getMessage());
            }
        }
    }

    public function testACredentialOfADidWebIssuerIsVerifiedByItsDidDocumentReadOnce(): void
    {
        $method = self::DID.'#key-1';
        $document = ['@context' => ['https://www.w3.org/ns/did/v1'], 'id' => self::DID, 'verificationMethod' => [['id' => $method, 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $this->partner()->verificationKey()->toMultibase()]]];
        $verifier = $this->verifier([$this->json($document)]);
        $token = new Securing($this->partner(), $method, Securing::JWT, new FrozenClock())->secure($this->credential(self::DID))->content;

        self::assertSame(self::DID, $verifier->verify($token)->issuer());
        self::assertSame(self::DID, $verifier->verify($token)->issuer());
        self::assertSame(['https://partner.example/.well-known/did.json'], $this->requested);
        self::assertTrue($this->cache->hasItem(RemoteVerificationMethods::CACHE_PREFIX.hash('sha256', self::DID)));
    }

    public function testADidWebWithAPathIsReadAtThatPathAndItsRsaKeyHolds(): void
    {
        $did = self::DID.':badges:2026';
        $signer = RsaKeys::signer();
        $document = ['@context' => ['https://www.w3.org/ns/did/v1'], 'id' => $did, 'verificationMethod' => [['id' => $did.'#rsa', 'type' => 'JsonWebKey', 'controller' => $did, 'publicKeyJwk' => $signer->publicJwk()]], 'assertionMethod' => [$did.'#rsa']];
        $token = new Securing($signer, $did.'#rsa', Securing::JWT, new FrozenClock())->secure($this->credential($did))->content;

        self::assertSame($did, $this->verifier([$this->json($document)])->verify($token)->issuer());
        self::assertSame(['https://partner.example/badges/2026/did.json'], $this->requested);
    }

    public function testADidDocumentOfAnotherIdentifierIsRefusedWithTheAddressRead(): void
    {
        $methods = $this->methods([$this->json(['id' => 'did:web:other.example'])]);

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The profile read at "https://partner.example/.well-known/did.json" is not the profile of that issuer: its "id" differs.');

        $methods->key(self::DID.'#key-1');
    }

    public function testADidWebThatDoesNotAnswerIsNamedByItsIdentifier(): void
    {
        $methods = $this->methods([new MockResponse('', ['http_code' => 404])]);

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The profile of the issuer "did:web:partner.example" could not be read: the answer was 404.');

        $methods->key(self::DID.'#key-1');
    }

    public function testOnlyTheTrustedDidWebsAreRead(): void
    {
        $methods = $this->methods([], ['did:web:other.example']);

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The issuer "did:web:partner.example" is not one the verifier trusts.');

        $methods->key(self::DID.'#key-1');
    }

    public function testADidWebOnLocalhostIsNeverRead(): void
    {
        foreach ([false, true] as $allowLocalhost) {
            try {
                $this->methods([], allowLocalhost: $allowLocalhost)->key('did:web:badges.localhost#key-1');
                self::fail('Unknown.');
            } catch (InvalidCredential $invalidCredential) {
                self::assertSame('The verification method "did:web:badges.localhost#key-1" is not one the verifier knows.', $invalidCredential->getMessage());
            }
        }

        self::assertSame([], $this->requested);
    }

    public function testTheMethodsOfADidDocumentNamedByTheirFragmentHold(): void
    {
        $document = ['id' => self::DID, 'verificationMethod' => [['id' => '#key-1', 'type' => 'Multikey', 'controller' => self::DID, 'publicKeyMultibase' => $this->partner()->verificationKey()->toMultibase()]], 'assertionMethod' => ['#key-1']];

        self::assertInstanceOf(Ed25519Key::class, $this->methods([$this->json($document)])->key(self::DID.'#key-1'));
    }

    public function testAFragmentNotAssertedIsNotKept(): void
    {
        $document = ['id' => self::DID, 'verificationMethod' => [['id' => '#key-1', 'type' => 'Multikey', 'publicKeyMultibase' => $this->partner()->verificationKey()->toMultibase()]], 'assertionMethod' => ['#key-2']];

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains('The profile of the issuer "did:web:partner.example" publishes no key "did:web:partner.example#key-1".');

        $this->methods([$this->json($document)])->key(self::DID.'#key-1');
    }

    public function testAnRsaKeyForAnotherAlgorithmIsNotKept(): void
    {
        $profile = $this->profileDocument([['id' => self::METHOD, 'type' => 'JsonWebKey', 'publicKeyJwk' => [...RsaKeys::signer()->publicJwk(), 'alg' => 'PS256']]]);

        $this->expectException(InvalidCredential::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The profile of the issuer "%s" publishes no key "%s".', self::PARTNER, self::METHOD));

        $this->methods([$this->json($profile)])->key(self::METHOD);
    }

    public function testTheRsaKeyIsCachedReducedToItsModulusAndExponent(): void
    {
        $jwk = RsaKeys::signer()->publicJwk();
        $profile = $this->profileDocument([['id' => self::METHOD, 'type' => 'JsonWebKey', 'publicKeyJwk' => [...$jwk, 'kid' => 'key-1', 'use' => 'sig']]]);
        $this->methods([$this->json($profile)])->key(self::METHOD);

        $item = $this->cache->getItem(RemoteVerificationMethods::CACHE_PREFIX.hash('sha256', self::PARTNER));

        self::assertSame([self::METHOD => ['kty' => 'RSA', 'n' => $jwk['n'], 'e' => $jwk['e']]], $item->get());
    }

    private function partner(): Ed25519Signer
    {
        return Ed25519Signer::fromSeed(str_repeat("\2", 32));
    }

    /**
     * @return array<string, mixed>
     */
    private function credential(?string $issuer = null): array
    {
        $credential = new CredentialIssuer(new Issuer(self::PARTNER, 'Partner'), $this->partner(), self::METHOD)
            ->credential(new Achievement('https://partner.example/badges/gold', 'Gold', 'Gold.', 'Win.'), Recipient::email('ada@example.org', 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-11-01 UTC'));

        if (null !== $issuer) {
            // A did:web issuer, which the issuing side of phil/open-badges does not name itself.
            $credential['issuer'] = ['id' => $issuer, 'type' => ['Profile'], 'name' => 'Partner'];
        }

        return $credential;
    }

    /**
     * @param list<mixed> $methods
     *
     * @return array<string, mixed>
     */
    private function profileDocument(array $methods): array
    {
        return ['@context' => ['https://www.w3.org/ns/credentials/v2'], 'id' => self::PARTNER, 'type' => ['Profile'], 'name' => 'Partner', 'verificationMethod' => $methods];
    }

    private function profile(): MockResponse
    {
        return $this->json(new Issuer(self::PARTNER, 'Partner')->profileDocument([self::METHOD => Ed25519Key::fromMultibase($this->partner()->verificationKey()->toMultibase())]));
    }

    /**
     * @param array<string, mixed> $document
     */
    private function json(array $document): MockResponse
    {
        return new MockResponse(json_encode($document, \JSON_THROW_ON_ERROR), ['response_headers' => ['Content-Type' => 'application/ld+json']]);
    }

    private function header(string $name): ?string
    {
        $headers = $this->options[0]['normalized_headers'] ?? null;
        $values = \is_array($headers) ? $headers[$name] ?? null : null;
        $header = \is_array($values) ? $values[0] ?? null : null;

        return \is_string($header) ? substr($header, \strlen($name) + 2) : null;
    }

    /**
     * @param list<MockResponse>|callable(string, string, array<array-key, mixed>): MockResponse $responses
     */
    private function verifier(array|callable $responses): CredentialVerifier
    {
        return new CredentialVerifier($this->methods($responses), Kit::METHOD);
    }

    /**
     * The methods as the constructor gives them when only the three services are named.
     *
     * @param list<MockResponse> $responses
     */
    private function byDefault(array $responses): RemoteVerificationMethods
    {
        $client = new MockHttpClient(function (string $method, string $url) use (&$responses): MockResponse {
            $this->requested[] = $url;

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 599]);
        });

        return new RemoteVerificationMethods(new DidKeyMethods([Kit::METHOD => Kit::signer()->verificationKey()]), new SafeHttpClient($client, new Limits(userAgent: 'PhilOpenBadges/0.1'), self::RESOLVE), $this->cache);
    }

    /**
     * @param list<MockResponse>|callable(string, string, array<array-key, mixed>): MockResponse $responses
     * @param list<string>                                                                       $trusted
     */
    private function methods(array|callable $responses, array $trusted = [], ?Limits $limits = null, int $ttl = 3600, int $failureTtl = 60, bool $allowLocalhost = false): RemoteVerificationMethods
    {
        if (\is_array($responses)) {
            $queue = $responses;
            $responses = static function () use (&$queue): MockResponse {
                /** @var list<MockResponse> $queue */
                return array_shift($queue) ?? new MockResponse('', ['http_code' => 599]);
            };
        }

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($responses): MockResponse {
            $this->requested[] = $url;
            $this->options[] = $options;

            return $responses($method, $url, $options);
        });

        return new RemoteVerificationMethods(
            new DidKeyMethods([Kit::METHOD => Kit::signer()->verificationKey()]),
            new SafeHttpClient($client, $limits ?? new Limits(userAgent: 'PhilOpenBadges/0.1'), self::RESOLVE),
            $this->cache,
            $trusted,
            $ttl,
            $failureTtl,
            $allowLocalhost,
        );
    }
}
