<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Functional;

use DateTimeImmutable;
use Override;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Baking\PngBaker;
use Phil\OpenBadges\Baking\SvgBaker;
use Phil\OpenBadges\Contract\VerificationMethods;
use Phil\OpenBadges\CredentialIssuer;
use Phil\OpenBadges\Issuer;
use Phil\OpenBadges\Key\Ed25519Key;
use Phil\OpenBadges\Key\Ed25519Signer;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Status\StatusListIssuer;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\Securing;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Phil\OpenBadgesBundle\Infrastructure\Verification\RemoteVerificationMethods;
use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Phil\OpenBadgesBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The bundle in an application: the profile and keys served, the status lists built from the application's, and the
 * verification page on tokens, documents and baked images.
 */
#[CoversClass(PhilOpenBadgesBundle::class)]
final class OpenBadgesBundleTest extends WebTestCase
{
    private const string ISSUER = 'https://example.com/open-badges/issuer';

    /**
     * The site's address, which its status lists' URLs carry.
     */
    private const array SERVER = ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on'];

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheProfileIsServedWithItsKey(): void
    {
        $client = self::createClient(server: self::SERVER);
        $client->request('GET', '/open-badges/issuer');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/ld+json');
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');
        $profile = $this->json($client);
        self::assertSame(self::ISSUER, $profile['id'] ?? null);
        self::assertSame('Example', $profile['name'] ?? null);
        self::assertSame([['id' => self::ISSUER.'#key-1', 'type' => 'Multikey', 'controller' => self::ISSUER, 'publicKeyMultibase' => $this->signer()->verificationKey()->toMultibase()]], $profile['verificationMethod'] ?? null);
        self::assertSame(3600, $client->getResponse()->getMaxAge());

        $client->request('GET', '/open-badges/issuer', server: ['HTTP_IF_NONE_MATCH' => (string) $client->getResponse()->getEtag()]);
        self::assertResponseStatusCodeSame(304);
    }

    public function testTheKeysAreServed(): void
    {
        $client = self::createClient(server: self::SERVER);
        $client->request('GET', '/open-badges/issuer/keys');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/jwk-set+json');
        self::assertSame(['keys' => [[...$this->signer()->publicJwk(), 'kid' => self::ISSUER.'#key-1', 'alg' => 'EdDSA', 'use' => 'sig']]], $this->json($client));
    }

    public function testAStatusListIsServedSecured(): void
    {
        $client = self::createClient(server: self::SERVER);
        $client->request('GET', '/open-badges/status/main');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/vc');
        self::assertSame(300, $client->getResponse()->getMaxAge());
        $verified = $this->verifier()->verify((string) $client->getResponse()->getContent(), new DateTimeImmutable());
        $issuer = self::getContainer()->get(StatusListIssuer::class);
        $urls = self::getContainer()->get(StatusListUrls::class);
        self::assertInstanceOf(StatusListIssuer::class, $issuer);
        self::assertInstanceOf(StatusListUrls::class, $urls);
        self::assertSame('https://example.com/open-badges/status/main', $verified->credential['id'] ?? null);
        self::assertTrue($issuer->applies($urls->entry('main', 7), $verified->credential));
        self::assertFalse($issuer->applies($urls->entry('main', 8), $verified->credential));

        $client->request('GET', '/open-badges/status/missing');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/open-badges/status/-bad');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAStatusListIsATokenWhenTheIssuerUsesTokens(): void
    {
        $client = self::createClient(['environment' => 'jwt'], self::SERVER);
        $client->request('GET', '/open-badges/status/paused');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/vc+jwt');
        $verified = $this->verifier()->verify((string) $client->getResponse()->getContent());
        self::assertSame('jwt', $verified->securedAs);
        $subject = $verified->credential['credentialSubject'] ?? null;
        self::assertIsArray($subject);
        self::assertSame('suspension', $subject['statusPurpose'] ?? null);
    }

    public function testTheVerificationPageShowsAValidCredential(): void
    {
        $client = self::createClient(server: self::SERVER);
        $crawler = $client->request('GET', '/open-badges/verify');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertSelectorTextContains('h1', 'Verify a badge');

        $client->submit($crawler->selectButton('Verify')->form(['credential' => $this->secured(), 'identity' => 'ada@example.org']));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section.holds h2', 'This credential is valid.');
        self::assertSelectorTextContains('section', 'Gold');
        self::assertSelectorTextContains('section', 'Fifty comments.');
        self::assertSelectorTextContains('section', 'Example');
        self::assertSelectorTextContains('section', 'a Data Integrity proof');
        self::assertSelectorTextContains('section', 'Neither revoked nor suspended');
        self::assertSelectorTextContains('section', 'Issued to the identity given');
    }

    public function testTheVerificationPageShowsARevokedCredential(): void
    {
        $client = self::createClient(server: self::SERVER);
        $client->request('POST', '/open-badges/verify', ['credential' => $this->token(7), 'identity' => 'bob@example.org']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('section.fails h2', 'This credential is not valid.');
        self::assertSelectorTextContains('section', 'Revoked or suspended');
        self::assertSelectorTextContains('section', 'Not issued to the identity given');
        self::assertSelectorTextContains('section', 'a JSON Web Token');
    }

    public function testTheVerificationPageReadsBakedImages(): void
    {
        $client = self::createClient(server: self::SERVER);
        $png = $this->file('badge.png', PngBaker::bake(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIW2NgUPr/HwADaAIhG61j/AAAAABJRU5ErkJggg==', true), $this->token()));
        $client->request('POST', '/open-badges/verify', files: ['image' => $png]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section', 'Not published by this site');

        $svg = $this->file('badge.svg', SvgBaker::bake('<svg xmlns="http://www.w3.org/2000/svg"/>', $this->token()));
        $client->request('POST', '/open-badges/verify', files: ['image' => $svg]);
        self::assertResponseIsSuccessful();

        $client->request('POST', '/open-badges/verify', files: ['image' => $this->file('plain.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('section p', 'The image holds no baked credential.');

        $client->request('POST', '/open-badges/verify', files: ['image' => $this->file('bad.png', "\x89PNG nope")]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('section p', 'The image is not a PNG of at most 16777216 bytes.');
    }

    public function testTheVerificationPageRefusesWhatDoesNotVerify(): void
    {
        $client = self::createClient(server: self::SERVER);

        foreach (['' => 'Paste a credential or give a baked image.', 'hello' => 'A credential is a compact JSON Web Token or the JSON of a document with its proof.'] as $credential => $reason) {
            $client->request('POST', '/open-badges/verify', ['credential' => $credential]);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('section.fails h2', 'This credential does not verify.');
            self::assertSelectorTextContains('section p', $reason);
        }

        $client->request('POST', '/open-badges/verify', ['credential' => str_repeat('a', 2_097_153)]);
        self::assertSelectorTextContains('section p', 'A credential is at most 2097152 bytes.');
    }

    public function testTheChecksOfThePageCountAgainstTheConfiguredLimiter(): void
    {
        $client = self::createClient(['environment' => 'limited'], self::SERVER);
        $client->disableReboot();

        $pool = self::getContainer()->get('cache.badge_checks');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();

        for ($i = 0; $i < 3; ++$i) {
            $client->request('GET', '/open-badges/verify');
            self::assertResponseIsSuccessful();
        }

        for ($i = 0; $i < 2; ++$i) {
            $client->request('POST', '/open-badges/verify', ['credential' => 'hello']);
            self::assertResponseStatusCodeSame(422);
        }

        $client->request('POST', '/open-badges/verify', ['credential' => 'hello']);
        self::assertResponseStatusCodeSame(429);
        self::assertResponseHasHeader('Retry-After');
    }

    public function testACredentialOfAnIssuerNotTrustedIsRefusedWithoutReadingItsProfile(): void
    {
        $client = self::createClient(['environment' => 'limited'], self::SERVER);
        $client->disableReboot();

        $pool = self::getContainer()->get('cache.badge_checks');
        self::assertInstanceOf(CacheItemPoolInterface::class, $pool);
        $pool->clear();
        self::assertInstanceOf(RemoteVerificationMethods::class, self::getContainer()->get('test.'.VerificationMethods::class));

        $other = Ed25519Signer::fromSeed(str_repeat("\2", 32));
        $token = new CredentialIssuer(new Issuer('https://other.example/issuer', 'Other'), $other, 'https://other.example/issuer#key-1')
            ->issue(new Achievement('https://other.example/badges/gold', 'Gold', 'Gold.', 'Win.'), Recipient::email('ada@example.org', 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-01-01 UTC'));

        $client->request('POST', '/open-badges/verify', ['credential' => $token]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('section p', 'The issuer "https://other.example/issuer" is not one the verifier trusts.');
    }

    public function testWithoutALimiterTheChecksAreNotCounted(): void
    {
        $client = self::createClient(server: self::SERVER);

        for ($i = 0; $i < 4; ++$i) {
            $client->request('POST', '/open-badges/verify', ['credential' => 'hello']);
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function testTheVerificationPageSpeaksFrench(): void
    {
        $client = self::createClient(server: self::SERVER);
        $client->request('GET', '/open-badges/verify', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr']);
        self::assertResponseIsSuccessful();
    }

    public function testTheVerifierTrustsTheConfiguredKeys(): void
    {
        self::bootKernel();
        $partner = Ed25519Key::fromMultibase('z6MkrJVnaZkeFzdQyMZu1cgjg7k1pZZ6pvBQ7XJPt4swbTQ2');
        $methods = self::getContainer()->get(VerificationMethods::class);

        self::assertInstanceOf(VerificationMethods::class, $methods);
        $found = $methods->key('did:web:partner.example#key-1');
        self::assertInstanceOf(Ed25519Key::class, $found);
        self::assertSame($partner->publicKey, $found->publicKey);
        self::assertInstanceOf(Securing::class, self::getContainer()->get(Securing::class));
    }

    private function signer(): Ed25519Signer
    {
        return Ed25519Signer::fromSeed(TestKernel::SEED);
    }

    private function verifier(): CredentialVerifier
    {
        $verifier = self::getContainer()->get(CredentialVerifier::class);
        self::assertInstanceOf(CredentialVerifier::class, $verifier);

        return $verifier;
    }

    private function credentials(): CredentialIssuer
    {
        $issuer = self::getContainer()->get(CredentialIssuer::class);
        self::assertInstanceOf(CredentialIssuer::class, $issuer);

        return $issuer;
    }

    private function achievement(): Achievement
    {
        return new Achievement('https://example.com/badges/gold', 'Gold', 'Fifty comments.', 'Comment on fifty items.');
    }

    private function token(?int $revocationIndex = null): string
    {
        $urls = self::getContainer()->get(StatusListUrls::class);
        self::assertInstanceOf(StatusListUrls::class, $urls);

        return $this->credentials()->issue($this->achievement(), Recipient::email('ada@example.org', 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-01-01 UTC'), status: null === $revocationIndex ? null : $urls->entry('main', $revocationIndex));
    }

    private function secured(): string
    {
        $urls = self::getContainer()->get(StatusListUrls::class);
        $securing = self::getContainer()->get(Securing::class);
        self::assertInstanceOf(StatusListUrls::class, $urls);
        self::assertInstanceOf(Securing::class, $securing);

        return $securing->secure($this->credentials()->credential($this->achievement(), Recipient::email('ada@example.org', 'saltsalt'), 'urn:uuid:3f6f2c1e-8a4b-4c1d-9e2f-5a6b7c8d9e0f', new DateTimeImmutable('2026-01-01 UTC'), status: $urls->entry('main', 8)))->content;
    }

    private function file(string $name, string $content): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.bin2hex(random_bytes(6)).'-'.$name;
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(KernelBrowser $client): array
    {
        $decoded = json_decode((string) $client->getResponse()->getContent(), true, 64, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
