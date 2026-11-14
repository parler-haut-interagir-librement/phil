<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Infrastructure\Http\RegisterController;
use Phil\CertificatesBundle\Infrastructure\Routing\RoutedCertificateUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\PdfStreams;
use Phil\CertificatesBundle\Tests\Unit\Support\Register;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[CoversClass(RegisterController::class)]
#[CoversClass(RoutedCertificateUrls::class)]
final class RegisterControllerTest extends TestCase
{
    private InMemoryCourses $courses;

    private InMemoryCertificates $certificates;

    private MockClock $clock;

    private RegisterController $controller;

    protected function setUp(): void
    {
        $this->courses = new InMemoryCourses();
        $this->certificates = new InMemoryCertificates();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-09 UTC'));
        $this->controller = Register::controller($this->courses, $this->certificates, $this->clock);
        $this->courses->save(Kit::firstAid());
        $retired = Course::define('archery', 'Archery', '', 'Hit.');
        $retired->retire();

        $this->courses->save($retired);
    }

    public function testTheUrlsAreAbsolute(): void
    {
        $urls = Register::urls();

        self::assertSame('https://example.com/certificates/courses/first-aid', $urls->course('first-aid'));
        self::assertSame('https://example.com/certificates/7KQ2M9XA04TZ', $urls->certificate(Kit::awarded()->code));
        self::assertSame('https://example.com/open-badges/status/certificates-2', $urls->statusEntry('certificates-2', 9)->statusListCredential);
        self::assertSame(9, $urls->statusEntry('certificates-2', 9)->index);
    }

    public function testTheRegisterListsTheOpenCourses(): void
    {
        $response = $this->controller->index(new Request());

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h1>Certificate register</h1>', (string) $response->getContent());
        self::assertStringContainsString('<a href="/certificates/courses/first-aid">First aid</a>', (string) $response->getContent());
        self::assertStringNotContainsString('Archery', (string) $response->getContent());
        self::assertStringNotContainsString('role="status"', (string) $response->getContent());
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
    }

    public function testACodeLeadsToItsCertificate(): void
    {
        $this->certificates->save(Kit::awarded());

        $response = $this->controller->index(new Request(['code' => ' 7kq2-m9xa-04tz ']));
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/certificates/7KQ2M9XA04TZ', $response->getTargetUrl());

        foreach (['0000-0000-0000', 'nonsense'] as $code) {
            $response = $this->controller->index(new Request(['code' => $code]));
            self::assertSame(404, $response->getStatusCode());
            self::assertStringContainsString('No certificate has the code '.$code.'.', (string) $response->getContent());
            self::assertStringContainsString('value="'.$code.'"', (string) $response->getContent());
            self::assertStringContainsString('First aid', (string) $response->getContent());
            self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        }

        self::assertSame(200, $this->controller->index(new Request(['code' => '  ']))->getStatusCode());
    }

    public function testACertificatesPageSaysWhetherItHolds(): void
    {
        $this->certificates->save(Kit::awarded(listed: false));
        $response = $this->controller->certificate('7KQ2M9XA04TZ');
        $content = (string) $response->getContent();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h1>Certificate 7KQ2-M9XA-04TZ</h1>', $content);
        self::assertStringContainsString('<section class="valid" role="status">', $content);
        self::assertStringContainsString('<dd>Ada Lovelace</dd>', $content);
        self::assertStringContainsString('<a href="/certificates/courses/first-aid">First aid</a>', $content);
        self::assertStringContainsString('2027-11-08', $content);
        self::assertStringContainsString('href="/certificates/7KQ2M9XA04TZ/certificate.pdf"', $content);
        self::assertStringContainsString('href="/certificates/7KQ2M9XA04TZ/credential"', $content);
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));

        $this->clock->modify('+1 year');
        self::assertStringContainsString('<section class="expired" role="status">', (string) $this->controller->certificate('7KQ2M9XA04TZ')->getContent());
    }

    public function testAListedCertificateMayBeIndexed(): void
    {
        $this->certificates->save(Kit::awarded());

        self::assertFalse($this->controller->certificate('7KQ2M9XA04TZ')->headers->has('X-Robots-Tag'));
    }

    public function testARevokedCertificateShowsItsReasonAndItsFilesAreGone(): void
    {
        $certificate = Kit::awarded();
        $this->certificates->save($certificate);
        $certificate->revoke('Fraud at the assessment.', new DateTimeImmutable('2026-11-09 UTC'));

        $content = (string) $this->controller->certificate('7KQ2M9XA04TZ')->getContent();
        self::assertStringContainsString('<section class="revoked" role="status">', $content);
        self::assertStringContainsString('<dd>Fraud at the assessment.</dd>', $content);
        self::assertStringNotContainsString('certificate.pdf', $content);

        foreach (['pdf', 'credential'] as $file) {
            try {
                'pdf' === $file ? $this->controller->pdf(new Request(), '7KQ2M9XA04TZ') : $this->controller->credential('7KQ2M9XA04TZ');
                self::fail('The file is gone.');
            } catch (GoneHttpException $goneHttpException) {
                self::assertSame('The certificate 7KQ2-M9XA-04TZ is revoked.', $goneHttpException->getMessage());
            }
        }
    }

    public function testACertificateLinksToTheOneItRenewsAndTheOneThatRenewsIt(): void
    {
        $first = Kit::awarded();
        $renewal = Certificate::award('0190f1d2-3c4b-7a8e-9f00-112233445577', VerificationCode::fromString('AAAABBBBCCCC'), $first->course, 'Ada Lovelace', new DateTimeImmutable('2027-10-01 UTC'), 4, 'h.p.s', renews: $first);
        $this->certificates->save($first);
        $this->certificates->save($renewal);

        $page = (string) $this->controller->certificate('7KQ2M9XA04TZ')->getContent();
        self::assertStringContainsString('<dt>Renewed by</dt>', $page);
        self::assertStringContainsString('<dd><a href="/certificates/AAAABBBBCCCC">AAAA-BBBB-CCCC</a></dd>', $page);
        self::assertStringNotContainsString('<dt>Renews</dt>', $page);

        $page = (string) $this->controller->certificate('AAAABBBBCCCC')->getContent();
        self::assertStringContainsString('<dt>Renews</dt>', $page);
        self::assertStringContainsString('<dd><a href="/certificates/7KQ2M9XA04TZ">7KQ2-M9XA-04TZ</a></dd>', $page);
        self::assertStringNotContainsString('<dt>Renewed by</dt>', $page);
    }

    public function testACoursePageSaysWhenItEndsAndWhatItsRecertificationAsks(): void
    {
        $this->courses->save(Course::define('cpr', 'CPR', '', 'Pass.', validityMonths: 24, recertificationCredits: Credits::fromString('7.5'), endsAt: new DateTimeImmutable('2030-01-01 UTC')));

        $page = (string) $this->controller->course(new Request(), 'cpr')->getContent();

        self::assertStringContainsString('<p>This course awards certificates until 2030-01-01; none is valid past that day.</p>', $page);
        self::assertStringContainsString('<p>A certificate is renewed with 7.5 credits of continuing education gathered while it is valid.</p>', $page);

        $page = (string) $this->controller->course(new Request(), 'first-aid')->getContent();
        self::assertStringNotContainsString('until', $page);
        self::assertStringNotContainsString('continuing education', $page);
    }

    public function testAnUnknownCertificateIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('There is no certificate 0000000000ZZ.');

        $this->controller->certificate('0000000000ZZ');
    }

    public function testThePdfIsServedInlineInTheLocaleOfTheRequest(): void
    {
        $this->certificates->save(Kit::awarded());
        $request = new Request();
        $request->setLocale('fr');

        $response = $this->controller->pdf($request, '7KQ2M9XA04TZ');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('%PDF-1.7', (string) $response->getContent());
        self::assertStringContainsString('(Certificat de r', PdfStreams::of((string) $response->getContent()));
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('inline; filename=certificate-7KQ2M9XA04TZ.pdf', $response->headers->get('Content-Disposition'));
        self::assertSame('noindex', $response->headers->get('X-Robots-Tag'));
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
    }

    public function testTheCredentialIsATokenOrADocument(): void
    {
        $this->certificates->save(Kit::awarded());
        $this->certificates->save(Kit::awarded(credential: '{"proof":{}}', code: '000000000001', index: 4));

        $token = $this->controller->credential('7KQ2M9XA04TZ');
        self::assertSame('header.payload.signature', $token->getContent());
        self::assertSame('application/vc+jwt', $token->headers->get('Content-Type'));
        self::assertSame('attachment; filename=credential.jwt', $token->headers->get('Content-Disposition'));
        self::assertSame('*', $token->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('noindex', $token->headers->get('X-Robots-Tag'));

        $document = $this->controller->credential('000000000001');
        self::assertSame('{"proof":{}}', $document->getContent());
        self::assertSame('application/vc', $document->headers->get('Content-Type'));
        self::assertSame('attachment; filename=credential.json', $document->headers->get('Content-Disposition'));
    }

    public function testACoursePageListsItsCertificatesFiftyAPage(): void
    {
        $course = $this->courses->courses['first-aid'];

        for ($i = 0; $i < 51; ++$i) {
            $this->certificates->save(Kit::awarded($course, code: \sprintf('%012d', $i), index: $i));
        }

        $this->certificates->save(Kit::awarded($course, listed: false, code: '00000000ZZZZ', index: 99));

        $response = $this->controller->course(new Request(), 'first-aid');
        $content = (string) $response->getContent();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('max-age=300, public', $response->headers->get('Cache-Control'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        self::assertStringContainsString('<h1>First aid</h1>', $content);
        self::assertStringContainsString('Score 70 % or more on the final assessment.', $content);
        self::assertStringContainsString('A certificate is valid for 12 months.', $content);
        self::assertStringContainsString('51 certificates are listed.', $content);
        self::assertSame(50, substr_count($content, '<li>'));
        self::assertStringContainsString('/certificates/000000000000"', $content);
        self::assertStringNotContainsString('/certificates/000000000050"', $content);
        self::assertStringNotContainsString('00000000ZZZZ', $content);
        self::assertStringContainsString('<span>1 / 2</span>', $content);
        self::assertStringContainsString('href="/certificates/courses/first-aid?page=2" rel="next"', $content);
        self::assertStringNotContainsString('rel="prev"', $content);

        $second = (string) $this->controller->course(new Request(['page' => '2']), 'first-aid')->getContent();
        self::assertSame(1, substr_count($second, '<li>'));
        self::assertStringContainsString('/certificates/000000000050"', $second);
        self::assertStringContainsString('href="/certificates/courses/first-aid?page=1" rel="prev"', $second);
        self::assertStringNotContainsString('rel="next"', $second);

        self::assertStringContainsString('<span>2 / 2</span>', (string) $this->controller->course(new Request(['page' => '9']), 'first-aid')->getContent());
        self::assertStringContainsString('<span>1 / 2</span>', (string) $this->controller->course(new Request(['page' => '0']), 'first-aid')->getContent());
    }

    public function testACourseWithoutCertificatesSaysSo(): void
    {
        $content = (string) $this->controller->course(new Request(), 'archery')->getContent();

        self::assertStringContainsString('This course no longer awards certificates.', $content);
        self::assertStringContainsString('No certificate is listed.', $content);
        self::assertStringNotContainsString('<nav', $content);
        self::assertStringNotContainsString('valid for', $content);
    }

    public function testFiftyCertificatesTakeOnePage(): void
    {
        $course = $this->courses->courses['first-aid'];

        for ($i = 0; $i < 50; ++$i) {
            $this->certificates->save(Kit::awarded($course, code: \sprintf('%012d', $i), index: $i));
        }

        self::assertStringNotContainsString('<nav', (string) $this->controller->course(new Request(), 'first-aid')->getContent());
    }

    public function testAnUnknownCourseIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('There is no course "cpr".');

        $this->controller->course(new Request(), 'cpr');
    }
}
