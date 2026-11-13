<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Tests\Integration;

use Override;
use Phil\PagedMedia\Gotenberg\Exception\RequestRefused;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\LibreOffice\LibreOfficeOptions;
use Phil\PagedMedia\Gotenberg\LibreOffice\PaperSize;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Signing\InlineCertificateSource;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthorityUrl;
use Phil\PagedMedia\Gotenberg\Stage\Attach;
use Phil\PagedMedia\Gotenberg\Stage\Autotag;
use Phil\PagedMedia\Gotenberg\Stage\Bookmark;
use Phil\PagedMedia\Gotenberg\Stage\ColorTarget;
use Phil\PagedMedia\Gotenberg\Stage\ConvertColors;
use Phil\PagedMedia\Gotenberg\Stage\Decrypt;
use Phil\PagedMedia\Gotenberg\Stage\Delete;
use Phil\PagedMedia\Gotenberg\Stage\Detach;
use Phil\PagedMedia\Gotenberg\Stage\Encrypt;
use Phil\PagedMedia\Gotenberg\Stage\ExtractAttachments;
use Phil\PagedMedia\Gotenberg\Stage\ExtractText;
use Phil\PagedMedia\Gotenberg\Stage\FlattenForm;
use Phil\PagedMedia\Gotenberg\Stage\FlattenTransparency;
use Phil\PagedMedia\Gotenberg\Stage\ImageFormat;
use Phil\PagedMedia\Gotenberg\Stage\Insert;
use Phil\PagedMedia\Gotenberg\Stage\Merge;
use Phil\PagedMedia\Gotenberg\Stage\Optimize;
use Phil\PagedMedia\Gotenberg\Stage\Redact;
use Phil\PagedMedia\Gotenberg\Stage\Region;
use Phil\PagedMedia\Gotenberg\Stage\Relationship;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Gotenberg\Stage\Render;
use Phil\PagedMedia\Gotenberg\Stage\Rotate;
use Phil\PagedMedia\Gotenberg\Stage\Select;
use Phil\PagedMedia\Gotenberg\Stage\SetBookmarks;
use Phil\PagedMedia\Gotenberg\Stage\SetMetadata;
use Phil\PagedMedia\Gotenberg\Stage\Sign;
use Phil\PagedMedia\Gotenberg\Stage\Split;
use Phil\PagedMedia\Gotenberg\Stage\Stage;
use Phil\PagedMedia\Gotenberg\Stage\StampFont;
use Phil\PagedMedia\Gotenberg\Stage\StampImage;
use Phil\PagedMedia\Gotenberg\Stage\StampPdf;
use Phil\PagedMedia\Gotenberg\Stage\StampText;
use Phil\PagedMedia\Gotenberg\Stage\Timestamp;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMedia\Tests\Unit\Fixture\PngBuilder;
use Phil\PagedMedia\Tests\Unit\Fixture\SelfSignedCertificate;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Every route against a real Gotenberg 9, given by `PHIL_GOTENBERG_URL` (`docker run --rm -p 3000:3000
 * gotenberg/gotenberg:9`, then `PHIL_GOTENBERG_URL=http://localhost:3000`); skipped without it. The timestamp test also
 * needs `PHIL_GOTENBERG_TSA`, the URL of an RFC 3161 authority Gotenberg can reach.
 */
#[CoversNothing]
final class GotenbergTest extends TestCase
{
    private Gotenberg $gotenberg;

    #[Override]
    protected function setUp(): void
    {
        $url = getenv('PHIL_GOTENBERG_URL');

        if (!\is_string($url) || '' === $url) {
            self::markTestSkipped('PHIL_GOTENBERG_URL names no Gotenberg to test against.');
        }

        $this->gotenberg = new Gotenberg(HttpClient::create(), $url, null, 120);
    }

    /**
     * @param non-empty-string $contentType
     */
    #[DataProvider('stages')]
    public function testEachStageRunsOnItsOwnRoute(Stage $stage, string $contentType): void
    {
        $result = $this->gotenberg->run(Files::of(self::pdf('report.pdf', 3)), $stage);

        self::assertStringStartsWith($contentType, $result->contentType);
        self::assertNotSame('', $result->contents());
    }

    /**
     * @return iterable<string, array{Stage, non-empty-string}>
     */
    public static function stages(): iterable
    {
        $png = InputFile::fromContents('dot.png', PngBuilder::build(2, 2, 2, 8, [str_repeat("\xCC\x00\x00", 2), str_repeat("\x00\x00\xCC", 2)]));
        $annex = self::pdf('annex.pdf', 1);

        yield 'merge' => [new Merge(), 'application/pdf'];
        yield 'split' => [Split::every(1), 'application/zip'];
        yield 'select' => [new Select('3,1'), 'application/pdf'];
        yield 'delete' => [new Delete('2'), 'application/pdf'];
        yield 'insert' => [new Insert($annex, 1), 'application/pdf'];
        yield 'rotate' => [new Rotate(90, 'odd'), 'application/pdf'];
        yield 'stamp-text' => [new StampText('DRAFT', 72, 72, 24, StampFont::HelveticaBold, '#cc0000', 0.5, 45), 'application/pdf'];
        yield 'stamp-image' => [new StampImage($png, 10, 10, 40), 'application/pdf'];
        yield 'stamp-pdf' => [new StampPdf($annex, 1, 0, 0, 0.5, 0.3), 'application/pdf'];
        yield 'set-metadata' => [new SetMetadata('Report', 'Tester'), 'application/pdf'];
        yield 'set-bookmarks' => [new SetBookmarks(new Bookmark('One', 1, [new Bookmark('Two', 2)])), 'application/pdf'];
        yield 'flatten-form' => [new FlattenForm(), 'application/pdf'];
        yield 'flatten-transparency' => [new FlattenTransparency(), 'application/pdf'];
        yield 'convert-colors' => [new ConvertColors(ColorTarget::Gray), 'application/pdf'];
        yield 'redact' => [new Redact(new Region(1, 0, 400, 400, 200)), 'application/pdf'];
        yield 'autotag' => [new Autotag(), 'application/pdf'];
        yield 'attach' => [new Attach(InputFile::fromContents('data.csv', "a,b\n"), relationship: Relationship::Data), 'application/pdf'];
        yield 'optimize' => [new Optimize(recompressImages: false), 'application/pdf'];
        yield 'encrypt' => [new Encrypt('user', 'owner'), 'application/pdf'];
        yield 'remediate' => [new Remediate([Profile::PdfA2b, Profile::PdfUa1], 'en-CA'), 'application/pdf'];
        yield 'render' => [new Render('1', 72, ImageFormat::Jpeg, 80), 'image/jpeg'];
        yield 'extract-text' => [new ExtractText(), 'text/plain'];
    }

    public function testAListRunsInOneRequestAndRemediationReportsWhatIsLeft(): void
    {
        $signer = SelfSignedCertificate::make();
        $sign = Sign::with(new InlineCertificateSource($signer->certificate, $signer->key), 'Approval', 'Montréal', name: 'Tester', pades: true);

        $result = $this->gotenberg->pipe(
            Files::of(self::pdf('1-report.pdf', 2), self::pdf('2-annex.pdf', 1)),
            new Pipeline(new Merge(), new StampText('COPY'), new Attach(InputFile::fromContents('data.csv', 'x')), new Remediate(Profile::PdfA3b), new Optimize(), $sign),
        );

        self::assertSame('1-report.pdf', $result->filename);
        self::assertSame(0, $result->residual);
        self::assertTrue($result->conforms());
        self::assertStringStartsWith('%PDF-', $result->contents());

        $merged = InputFile::fromContents('merged.pdf', $result->contents());
        self::assertSame(3, $this->gotenberg->info(Files::of($merged))['merged.pdf']->pages);
        self::assertTrue($this->gotenberg->validate(Files::of($merged), Profile::PdfA3b)['merged.pdf']->compliant);
    }

    public function testAPkcs12CertificateSigns(): void
    {
        $signer = SelfSignedCertificate::make('s3cret');

        $result = $this->gotenberg->run(Files::of(self::pdf('report.pdf', 1)), Sign::with(new InlineCertificateSource($signer->pkcs12, null, $signer->password)));

        self::assertStringContainsString('/ByteRange', $result->contents());
    }

    public function testTheReadRoutesAnswerPerFile(): void
    {
        $attached = InputFile::fromContents('attached.pdf', $this->gotenberg->pipe(
            Files::of(self::pdf('report.pdf', 3)),
            new Pipeline(new SetMetadata('Quarterly', 'Finance'), new SetBookmarks(new Bookmark('Summary', 1, [new Bookmark('Figures', 2)])), new Attach(InputFile::fromContents('data.csv', "a,b\n"), relationship: Relationship::Data)),
        )->contents());
        $files = Files::of($attached);

        self::assertSame(3, $this->gotenberg->info($files)['attached.pdf']->pages);
        self::assertSame('Quarterly', $this->gotenberg->metadata($files)['attached.pdf']->title);
        self::assertEquals([new Bookmark('Summary', 1, [new Bookmark('Figures', 2)])], $this->gotenberg->bookmarks($files)['attached.pdf']);
        self::assertSame('data.csv', $this->gotenberg->attachments($files)['attached.pdf'][0]->name);
        self::assertSame('Data', $this->gotenberg->attachments($files)['attached.pdf'][0]->relationship);
        self::assertSame([], $this->gotenberg->form($files, null, '1')['attached.pdf']);
        self::assertFalse($this->gotenberg->validate($files, [Profile::PdfA2b, Profile::PdfUa1])['attached.pdf']->compliant);
        self::assertSame("a,b\n", $this->gotenberg->run($files, new ExtractAttachments('data.csv'))->contents());
        self::assertStringStartsWith('%PDF-', $this->gotenberg->run($files, new Detach('data.csv'))->contents());
    }

    public function testAnEncryptedPdfIsReadAndDecryptedWithItsPassword(): void
    {
        $encrypted = InputFile::fromContents('encrypted.pdf', $this->gotenberg->run(Files::of(self::pdf('report.pdf', 2)), new Encrypt('user'))->contents());

        self::assertTrue($this->gotenberg->info(Files::of($encrypted), 'user')['encrypted.pdf']->encrypted);
        $decrypted = $this->gotenberg->pipe(Files::of($encrypted), new Pipeline(new Decrypt('user'), new Optimize()));
        self::assertFalse($this->gotenberg->info(Files::of(InputFile::fromContents('decrypted.pdf', $decrypted->contents())))['decrypted.pdf']->encrypted);
    }

    public function testLibreOfficeConvertsWithItsNativeOptionsThenRunsTheStages(): void
    {
        $result = $this->gotenberg->convert(
            Files::of(InputFile::fromContents('note.txt', "Hello\n\nWorld")),
            new LibreOfficeOptions(paperSize: PaperSize::Letter, landscape: true, nativeWatermarkText: 'DRAFT', nativePdfa: Profile::PdfA2b, nativePdfua: Profile::PdfUa1),
            new Pipeline(new SetMetadata('Note')),
        );

        self::assertSame('note.txt.pdf', $result->filename);
        self::assertStringStartsWith('%PDF-', $result->contents());
    }

    public function testAStageThatFailsOnAFileIsRefused(): void
    {
        $this->expectException(RequestRefused::class);
        $this->expectExceptionMessageIsOrContains("Stage 'select' failed on 'report.pdf'");

        $this->gotenberg->run(Files::of(self::pdf('report.pdf', 1)), new Select('9'));
    }

    public function testATimestampAuthorityVouchesForThePdf(): void
    {
        $authority = getenv('PHIL_GOTENBERG_TSA');

        if (!\is_string($authority) || '' === $authority) {
            self::markTestSkipped('PHIL_GOTENBERG_TSA names no timestamp authority.');
        }

        $result = $this->gotenberg->run(Files::of(self::pdf('report.pdf', 1)), new Timestamp(new TimestampAuthorityUrl($authority)));

        self::assertStringContainsString('/ETSI.RFC3161', $result->contents());
    }

    private static function pdf(string $name, int $pages): InputFile
    {
        $document = new Document(PageSize::a5());

        for ($page = 1; $page <= $pages; ++$page) {
            $document->addPage();
            $document->text(36, 500, $name.', page '.$page, 14);
        }

        return InputFile::fromContents($name, $document->render());
    }
}
