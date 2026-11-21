<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\CertificateStatusLists;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\Revocation;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Infrastructure\Http\RegisterController;
use Phil\CertificatesBundle\Infrastructure\Routing\RoutedCertificateUrls;
use Phil\CertificatesBundle\Tests\Functional\App\TestKernel;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\StatusListContent;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The register served by a small application: certificates awarded, found, downloaded and revoked through the
 * routes, with Doctrine storing them, its mapping valid and in step with the schema, and Gotenberg played by the HTTP
 * client.
 */
#[CoversClass(RegisterController::class)]
#[CoversClass(RoutedCertificateUrls::class)]
final class RegisterTest extends WebTestCase
{
    private KernelBrowser $client;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->service(Courses::class)->define('first-aid', 'First aid', 'The gestures that save lives.', 'Score 70 % or more.', new PassCriteria(70), 12);
        $this->service(Courses::class)->define('archery', 'Archery', 'Bows.', 'Hit the target.');
        $this->service(Courses::class)->retire('archery');
    }

    public function testTheMappingIsValidAndTheSchemaInStepWithIt(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $validator = new SchemaValidator($entityManager);

        self::assertSame([], $validator->validateMapping());
        self::assertTrue($validator->schemaInSyncWithMetadata());
    }

    public function testTheRegisterListsTheOpenCoursesAndFindsACertificateByItsCode(): void
    {
        $certificate = $this->award();

        $this->client->request('GET', '/certificates/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Certificate register');
        self::assertSelectorTextContains('ul', 'First aid');
        self::assertSelectorTextNotContains('ul', 'Archery');
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');

        $this->client->request('GET', '/certificates/?code='.strtolower($certificate->code->formatted()));
        self::assertResponseRedirects('/certificates/'.$certificate->code->value);

        $this->client->request('GET', '/certificates/?code=0000-0000-0000');
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('[role=status]', 'No certificate has the code 0000-0000-0000.');
        self::assertInputValueSame('code', '0000-0000-0000');

        $this->client->request('GET', '/certificates/?code=nonsense');
        self::assertResponseStatusCodeSame(404);
    }

    public function testACertificatesPageSaysWhetherItHolds(): void
    {
        $certificate = $this->award(listed: false);

        $this->client->request('GET', '/certificates/'.$certificate->code->value);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Certificate '.$certificate->code->formatted());
        self::assertSelectorTextContains('section.valid h2', 'This certificate is valid.');
        self::assertSelectorTextContains('dl', 'Ada Lovelace');
        self::assertSelectorTextContains('dl', '2027-11-08');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertStringContainsString('no-cache', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorExists('a[href="/certificates/'.$certificate->code->value.'/certificate.pdf"]');

        $listed = $this->award();
        $this->client->request('GET', '/certificates/'.$listed->code->value);
        self::assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        self::assertFalse($this->client->getResponse()->headers->has('X-Robots-Tag'));

        $this->service(MockClock::class, 'clock')->modify('+1 year');
        $this->client->request('GET', '/certificates/'.$listed->code->value);
        self::assertSelectorTextContains('section.expired h2', 'This certificate has expired.');

        $this->client->request('GET', '/certificates/0000000000ZZ');
        self::assertResponseStatusCodeSame(404);
    }

    public function testThePdfAndTheCredentialAreServed(): void
    {
        $certificate = $this->award();

        $this->client->request('GET', '/certificates/'.$certificate->code->value.'/certificate.pdf');
        self::assertResponseIsSuccessful();
        self::assertSame('%PDF-gotenberg', $this->client->getInternalResponse()->getContent());
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertResponseHeaderSame('Content-Disposition', 'inline; filename=certificate-'.$certificate->code->value.'.pdf');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');
        self::assertStringContainsString('no-cache', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/certificates/'.$certificate->code->value.'/credential');
        self::assertResponseIsSuccessful();
        self::assertSame($certificate->credential, $this->client->getInternalResponse()->getContent());
        self::assertResponseHeaderSame('Content-Type', 'application/vc+jwt');
        self::assertResponseHeaderSame('Content-Disposition', 'attachment; filename=credential.jwt');
        self::assertResponseHeaderSame('Access-Control-Allow-Origin', '*');

        $verified = $this->service(CredentialVerifier::class)->verify($certificate->credential);
        $subject = $verified->credential['credentialSubject'];
        self::assertIsArray($subject);
        self::assertIsArray($subject['achievement']);
        self::assertSame('https://example.com/certificates/courses/first-aid', $subject['achievement']['id']);
        self::assertSame('https://example.com/open-badges/status/certificates-0', $verified->status()?->statusListCredential);
    }

    public function testARevokedCertificateIsShownRevokedAndItsFilesAreGone(): void
    {
        $certificate = $this->award();
        $this->service(Revocation::class)->revoke($certificate->code->value, 'Fraud at the assessment.');

        $this->client->request('GET', '/certificates/'.$certificate->code->value);
        self::assertSelectorTextContains('section.revoked h2', 'This certificate was revoked.');
        self::assertSelectorTextContains('dl', 'Fraud at the assessment.');
        self::assertSelectorNotExists('a[href$="certificate.pdf"]');

        $this->client->request('GET', '/certificates/'.$certificate->code->value.'/certificate.pdf');
        self::assertResponseStatusCodeSame(410);
        $this->client->request('GET', '/certificates/'.$certificate->code->value.'/credential');
        self::assertResponseStatusCodeSame(410);

        self::assertEquals(new StatusListContent([$certificate->statusIndex]), $this->service(CertificateStatusLists::class)->find('certificates-0'));
        $this->client->request('GET', '/open-badges/status/certificates-0');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/open-badges/status/certificates-1');
        self::assertResponseIsSuccessful();
    }

    public function testACoursePageListsTheCertificatesTheirHoldersListed(): void
    {
        $clock = $this->service(MockClock::class, 'clock');

        for ($i = 0; $i < 51; ++$i) {
            $this->award(name: 'Learner '.$i);
            $clock->modify('+1 minute');
        }

        $this->award(listed: false, name: 'Hidden');

        $this->client->request('GET', '/certificates/courses/first-aid');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'First aid');
        self::assertSelectorTextContains('h2', 'To pass');
        self::assertSelectorTextContains('body', 'Score 70 % or more.');
        self::assertSelectorTextContains('body', 'A certificate is valid for 12 months.');
        self::assertSelectorTextContains('body', '51 certificates are listed.');
        self::assertSelectorTextContains('ul li:first-child', 'Learner 50');
        self::assertSelectorCount(50, 'ul li');
        self::assertSelectorTextNotContains('ul', 'Hidden');
        self::assertSelectorTextContains('nav', '1 / 2');
        self::assertSelectorExists('a[rel=next][href="/certificates/courses/first-aid?page=2"]');
        self::assertSelectorNotExists('a[rel=prev]');
        self::assertStringContainsString('max-age=300', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->client->request('GET', '/certificates/courses/first-aid?page=2');
        self::assertSelectorCount(1, 'ul li');
        self::assertSelectorTextContains('ul li', 'Learner 0');
        self::assertSelectorExists('a[rel=prev][href="/certificates/courses/first-aid?page=1"]');
        self::assertSelectorNotExists('a[rel=next]');

        $this->client->request('GET', '/certificates/courses/first-aid?page=9');
        self::assertSelectorTextContains('nav', '2 / 2');
        $this->client->request('GET', '/certificates/courses/first-aid?page=0');
        self::assertSelectorTextContains('nav', '1 / 2');

        $this->client->request('GET', '/certificates/courses/archery');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'This course no longer awards certificates.');
        self::assertSelectorTextContains('body', 'No certificate is listed.');
        self::assertSelectorNotExists('nav');

        $this->client->request('GET', '/certificates/courses/cpr');
        self::assertResponseStatusCodeSame(404);
    }

    private function award(bool $listed = true, string $name = 'Ada Lovelace'): Certificate
    {
        return $this->service(Awarding::class)->award('first-aid', $name, 'ada@example.org', new Attainment(80), $listed);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? 'test.'.$class);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
