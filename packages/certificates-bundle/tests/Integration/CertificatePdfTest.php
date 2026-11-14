<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Integration;

use Override;
use Phil\CertificatesBundle\Application\CertificatePdf;
use Phil\CertificatesBundle\Tests\Unit\Support\FixedUrls;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\Translations;
use Phil\PagedMedia\Gotenberg\Files;
use Phil\PagedMedia\Gotenberg\Gotenberg;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Stage\ExtractAttachments;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\PagedMedia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * The PDF of a certificate made by a real Gotenberg 9, given by `PHIL_GOTENBERG_URL` (`composer test:services`, or
 * `docker run --rm -p 3000:3000 gotenberg/gotenberg:9`, then `PHIL_GOTENBERG_URL=http://localhost:3000`); skipped
 * without it.
 */
#[CoversClass(CertificatePdf::class)]
final class CertificatePdfTest extends TestCase
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

    public function testTheCredentialIsAnAssociatedFileOfAPdfA3b(): void
    {
        $certificate = Kit::awarded();
        $pdf = new CertificatePdf(new PagedMedia(gotenberg: $this->gotenberg), new FixedUrls(), Translations::translator(), Kit::issuer(), PageSize::letter(), archival: true);
        $files = Files::of(InputFile::fromContents('certificate.pdf', $pdf->render($certificate, 'en')->contents));

        $attachments = $this->gotenberg->attachments($files)['certificate.pdf'];
        self::assertCount(1, $attachments);
        self::assertSame(['credential.jwt', 'application/vc+jwt', 'Data'], [$attachments[0]->name, $attachments[0]->mimeType, $attachments[0]->relationship]);
        self::assertSame($certificate->credential, $this->gotenberg->run($files, new ExtractAttachments('credential.jwt'))->contents());
        self::assertTrue($this->gotenberg->validate($files, Profile::PdfA3b)['certificate.pdf']->compliant);
    }
}
