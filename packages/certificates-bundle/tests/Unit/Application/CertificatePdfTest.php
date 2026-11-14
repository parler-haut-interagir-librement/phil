<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use Closure;
use Phil\CertificatesBundle\Application\CertificatePdf;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\PdfStreams;
use Phil\CertificatesBundle\Tests\Unit\Support\QrReader;
use Phil\CertificatesBundle\Tests\Unit\Support\RecordedEvents;
use Phil\CertificatesBundle\Tests\Unit\Support\Translations;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\Event\PrintStarting;
use Phil\PagedMediaBundle\Application\Exception\NotConfigured;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PrintSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(CertificatePdf::class)]
final class CertificatePdfTest extends TestCase
{
    private string $sent = '';

    public function testTheCertificateIsDrawnAsTheReferenceSays(): void
    {
        $document = $this->pdf()->document(Kit::awarded(), 'en');

        self::assertSame(1, $document->pageCount());
        self::assertStringEqualsFile(__DIR__.'/../Fixture/certificate.txt', PdfStreams::of($document->render()));
    }

    public function testTheQrCodeReadsBackAsTheAddressOfTheCertificateInTheRegister(): void
    {
        $streams = PdfStreams::of($this->pdf()->document(Kit::awarded(), 'en')->render());
        self::assertSame('https://example.com/certificates/7KQ2M9XA04TZ', QrReader::fromStreams($streams)->text());

        $other = PdfStreams::of($this->pdf(PageSize::a4())->document(Kit::awarded(code: 'Z9Y8X7W6V5T4'), 'en')->render());
        self::assertSame('https://example.com/certificates/Z9Y8X7W6V5T4', QrReader::fromStreams($other)->text());
    }

    public function testEachPrintNamesTheCertificateAsItsSubject(): void
    {
        $events = new RecordedEvents();
        $pdf = new CertificatePdf(new PagedMedia(gotenberg: new Gotenberg($this->client(), 'http://gotenberg:3000'), events: $events), new FixedUrls(), Translations::translator('en'), Kit::issuer(), PageSize::letter(), bake: false);

        $pdf->render(Kit::awarded());

        self::assertEquals([new PrintStarting(new PrintSubject('certificate', '0190f1d2-3c4b-7a8e-9f00-112233445566'))], $events->events);
        self::assertSame('certificate', CertificatePdf::PRINT_SUBJECT);
    }

    public function testThePageIsLandscapeAndTheMetadataNameTheCertificate(): void
    {
        $pdf = $this->pdf(PageSize::a4())->document(Kit::awarded(), 'en')->render();

        self::assertStringContainsString('/MediaBox [0 0 842 595]', $pdf);
        self::assertStringContainsString('/Title '.Metadata::text('Certificate of completion — First aid').' ', $pdf);
        self::assertStringContainsString('/Author (Example Academy)', $pdf);
        self::assertStringContainsString('/Subject (Ada Lovelace)', $pdf);
        self::assertStringContainsString('/CreationDate (D:20261108100000', $pdf);
    }

    public function testTheTextsFollowTheLocaleOfTheTranslatorUnlessOneIsGiven(): void
    {
        $french = PdfStreams::of($this->pdf(translator: 'fr')->render(Kit::awarded())->contents);

        self::assertStringContainsString('(Certificat de r', $french);
        self::assertStringContainsString('le 8 novembre 2026)', $french);
        self::assertStringContainsString('(Valide jusqu', $french);
        self::assertStringContainsString('(Ce certificat atteste que)', $french);
        self::assertStringContainsString('(Ada Lovelace)', PdfStreams::of($this->pdf(translator: 'fr')->render(Kit::awarded(), 'en')->contents));
        self::assertStringContainsString('(Awarded on November 8, 2026)', PdfStreams::of($this->pdf(translator: 'fr')->render(Kit::awarded(), 'en')->contents));
    }

    public function testALongCourseNameTakesTwoLinesAtMostAndAPermanentCertificateNoEnd(): void
    {
        $course = Course::define('long', str_repeat('Advanced cardiopulmonary resuscitation ', 4), '', 'Pass.');
        $streams = PdfStreams::of($this->pdf()->document(Kit::awarded($course), 'en')->render());

        self::assertSame(2, substr_count($streams, '/F1 20 Tf'));
        self::assertStringContainsString('BT /F1 20 Tf 199.28 322 Td (Advanced cardiopulmonary resuscitation) Tj ET', $streams);
        self::assertStringContainsString('BT /F1 20 Tf 199.28 292 Td (Advanced cardiopulmonary resuscitation) Tj ET', $streams);
        self::assertStringContainsString(' 256 Td (Awarded on', $streams);
        self::assertStringNotContainsString('Valid until', $streams);
    }

    public function testTheRenderedFileIsTheDocumentWhenNothingIsEmbedded(): void
    {
        $output = $this->pdf()->render(Kit::awarded(), 'en');

        self::assertSame('certificate-7KQ2M9XA04TZ.pdf', $output->filename);
        self::assertSame('application/pdf', $output->contentType);
        self::assertStringStartsWith('%PDF-1.7', $output->contents);
    }

    public function testTheCredentialIsEmbeddedAsTheDataOfThePdf(): void
    {
        $output = $this->pdf(bake: true)->render(Kit::awarded(), 'en');

        self::assertSame('%PDF-gotenberg', $output->contents);
        self::assertStringContainsString('filename="credential.jwt"', $this->sent);
        self::assertStringContainsString('header.payload.signature', $this->sent);
        self::assertStringContainsString('filename="certificate-7KQ2M9XA04TZ.pdf"', $this->sent);
        self::assertStringContainsString('"op":"attach"', $this->sent);
        self::assertStringContainsString('"name":"credential.jwt","mimeType":"application/vc+jwt","relationship":"Data"', $this->sent);

        $this->pdf(bake: true)->render(Kit::awarded(credential: '{"proof":{}}'), 'en');

        self::assertStringContainsString('"name":"credential.json","mimeType":"application/vc","relationship":"Data"', $this->sent);
        self::assertStringContainsString('{"proof":{}}', $this->sent);
    }

    public function testTheCredentialIsEmbeddedAndThePdfNotSignedByDefault(): void
    {
        $pdf = new CertificatePdf(new PagedMedia(gotenberg: new Gotenberg($this->client(), 'http://gotenberg:3000')), new FixedUrls(), Translations::translator(), Kit::issuer(), PageSize::letter());

        self::assertSame('%PDF-gotenberg', $pdf->render(Kit::awarded(), 'en')->contents);
        self::assertStringContainsString('"op":"attach"', $this->sent);
        self::assertStringNotContainsString('"op":"sign"', $this->sent);
    }

    public function testThePdfIsNotRepairedToPdfAByDefault(): void
    {
        $pdf = new CertificatePdf(new PagedMedia(gotenberg: new Gotenberg($this->client(), 'http://gotenberg:3000')), new FixedUrls(), Translations::translator(), Kit::issuer(), PageSize::letter());

        $pdf->render(Kit::awarded(), 'en');

        self::assertStringNotContainsString('"op":"remediate"', $this->sent);
    }

    public function testACourseNameThatFitsTheLineBetweenTheMarginsTakesOne(): void
    {
        $course = Course::define('cpr', 'Advanced cardiopulmonary resuscitation, adults', '', 'Pass.');
        $streams = PdfStreams::of($this->pdf()->document(Kit::awarded($course), 'en')->render());

        self::assertSame(1, substr_count($streams, '/F1 20 Tf'));
        self::assertStringContainsString(' 322 Td (Advanced cardiopulmonary resuscitation, adults) Tj ET', $streams);
    }

    public function testTheCredentialIsEmbeddedThenThePdfRepairedToPdfA3bInTheLocaleGiven(): void
    {
        $this->pdf(bake: true, archival: true)->render(Kit::awarded(), 'fr_CA');

        self::assertMatchesRegularExpression('/"op":"attach".*"op":"remediate"/s', $this->sent);
        self::assertStringContainsString('"profile":"PDF/A-3b"', $this->sent);
        self::assertStringContainsString('"language":"fr-CA"', $this->sent);
    }

    public function testThePdfIsRepairedInTheLocaleOfTheTranslatorEvenWithoutTheCredential(): void
    {
        $this->pdf(translator: 'fr', archival: true)->render(Kit::awarded());

        self::assertStringNotContainsString('"op":"attach"', $this->sent);
        self::assertStringContainsString('"op":"remediate"', $this->sent);
        self::assertStringContainsString('"language":"fr"', $this->sent);
    }

    public function testThePdfIsSignedWhenAsked(): void
    {
        $this->expectException(NotConfigured::class);

        $this->pdf(sign: true)->render(Kit::awarded(), 'en');
    }

    private function pdf(?PageSize $size = null, bool $bake = false, bool $sign = false, string $translator = 'en', bool $archival = false): CertificatePdf
    {
        $client = $this->client();

        return new CertificatePdf(new PagedMedia(gotenberg: new Gotenberg($client, 'http://gotenberg:3000')), new FixedUrls(), Translations::translator($translator), Kit::issuer(), $size ?? PageSize::letter(), bake: $bake, sign: $sign, archival: $archival);
    }

    private function client(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options): ResponseInterface {
            $this->sent = '';
            $body = $options['body'] ?? null;

            while ($body instanceof Closure && \is_string($chunk = $body(8192)) && '' !== $chunk) {
                $this->sent .= $chunk;
            }

            return new MockResponse('%PDF-gotenberg', ['response_headers' => ['Content-Type' => 'application/pdf']]);
        });
    }
}
