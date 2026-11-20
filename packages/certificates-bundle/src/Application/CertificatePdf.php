<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use DateTimeImmutable;
use IntlDateFormatter;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\OpenBadges\Issuer;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Gotenberg\InputFile;
use Phil\PagedMedia\Gotenberg\Pipeline;
use Phil\PagedMedia\Gotenberg\Profile;
use Phil\PagedMedia\Gotenberg\Stage\Attach;
use Phil\PagedMedia\Gotenberg\Stage\Relationship;
use Phil\PagedMedia\Gotenberg\Stage\Remediate;
use Phil\PagedMedia\Pdf\Color;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Application\PagedMedia;
use Phil\PagedMediaBundle\Application\PrintSubject;
use Phil\QrCode\ErrorCorrection;
use Phil\QrCode\QrCode;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The PDF of a certificate, drawn with the native writer of `phil/paged-media` on a landscape page: the name printed
 * on it, the course, when it was awarded and until when it is valid, the issuer, and a QR code that opens the
 * certificate's page in the register, with its address and code written beside it.
 *
 * When `$bake` is true, the signed credential is embedded in the PDF through Gotenberg's `attach` step, as a PDF/A-3
 * associated file of relationship `Data` — `credential.jwt` or `credential.json` —, so that the file carries its own
 * proof; when `$archival` is true, the PDF is then repaired to PDF/A-3b, the profile that admits such a file; when
 * `$sign` is true, the PDF is signed last, with the certificate of `phil/paged-media-bundle`'s configuration. The texts
 * come from the translation domain `phil_certificates`.
 *
 * Each print names its subject, of the type `certificate` and the certificate's identifier, so that a listener of
 * `PrintStarting` knows what it is: a certificate is no licensed work, and an application that checks the licenses of
 * its prints adds that type to the subjects it prints unchecked.
 */
final readonly class CertificatePdf
{
    public const string DOMAIN = 'phil_certificates';

    /** The type of the `PrintSubject` each print of a certificate names. */
    public const string PRINT_SUBJECT = 'certificate';

    /** The side of the QR code, in points: an inch and a half. */
    private const float QR_SIDE = 108;

    private const float MARGIN = 48;

    public function __construct(
        private PagedMedia $pagedMedia,
        private CertificateUrls $urls,
        private TranslatorInterface $translator,
        private Issuer $issuer,
        private PageSize $pageSize,
        private StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        private StandardFont|TrueTypeFont $boldFont = StandardFont::HelveticaBold,
        private bool $bake = true,
        private bool $sign = false,
        private bool $archival = false,
    ) {
    }

    public function render(Certificate $certificate, ?string $locale = null): Output
    {
        $stages = [];

        if ($this->bake) {
            $stages[] = $this->attachment($certificate);
        }

        if ($this->archival) {
            $stages[] = new Remediate(Profile::PdfA3b, str_replace('_', '-', $locale ?? $this->translator->getLocale()));
        }

        $pipeline = [] === $stages ? null : new Pipeline(...$stages);

        return $this->pagedMedia->render($this->document($certificate, $locale), 'certificate-'.$certificate->code->value.'.pdf', $this->pagedMedia->finish($pipeline, $this->sign), new PrintSubject(self::PRINT_SUBJECT, $certificate->id));
    }

    /**
     * The certificate drawn, before the credential is embedded and the file signed.
     */
    public function document(Certificate $certificate, ?string $locale = null): Document
    {
        $page = $this->pageSize->landscape();
        $locale ??= $this->translator->getLocale();
        $url = $this->urls->certificate($certificate->code);
        $document = new Document($page, new Metadata(
            title: $this->trans('pdf.title', [], $locale).' — '.$certificate->course->name,
            author: $this->issuer->name,
            subject: $certificate->recipientName,
            createdAt: $certificate->awardedAt,
        ));
        $document->addPage();

        $width = $page->width;
        $height = $page->height;

        $document->rectangle(24, 24, $width - 48, $height - 48, stroke: Color::gray(60), lineWidth: 2);
        $document->rectangle(32, 32, $width - 64, $height - 64, stroke: Color::gray(150), lineWidth: 0.5);

        $y = $height - 130;
        $this->centered($document, $width, $y, $this->trans('pdf.title', [], $locale), 30, $this->boldFont);
        $this->centered($document, $width, $y -= 50, $this->trans('pdf.awarded_to', [], $locale), 14, $this->font);
        $this->centered($document, $width, $y -= 40, $certificate->recipientName, 26, $this->boldFont);
        $this->centered($document, $width, $y -= 40, $this->trans('pdf.completed', [], $locale), 14, $this->font);

        foreach (\array_slice(Document::wrap($certificate->course->name, $width - 2 * self::MARGIN - 2 * self::QR_SIDE, 20, $this->boldFont), 0, 2) as $line) {
            $this->centered($document, $width, $y -= 30, $line, 20, $this->boldFont);
        }

        $this->centered($document, $width, $y -= 36, $this->trans('pdf.awarded_on', ['%date%' => $this->date($certificate->awardedAt, $locale)], $locale), 12, $this->font);

        if ($certificate->validUntil instanceof DateTimeImmutable) {
            $this->centered($document, $width, $y - 18, $this->trans('pdf.valid_until', ['%date%' => $this->date($certificate->validUntil, $locale)], $locale), 12, $this->font);
        }

        $document->text(self::MARGIN + 12, self::MARGIN + 30, $this->trans('pdf.issued_by', ['%issuer%' => $this->issuer->name], $locale), 12, $this->font);
        $document->text(self::MARGIN + 12, self::MARGIN + 12, $this->trans('pdf.code', ['%code%' => $certificate->code->formatted()], $locale), 9, $this->font, Color::gray(80));
        $document->textRight($width - self::MARGIN - self::QR_SIDE - 12, self::MARGIN + 30, $this->trans('pdf.verify', [], $locale), 9, $this->font, Color::gray(80));
        $document->textRight($width - self::MARGIN - self::QR_SIDE - 12, self::MARGIN + 12, $url, 9, $this->font, Color::gray(80));
        $this->qrCode($document, QrCode::encode($url, ErrorCorrection::Medium), $width - self::MARGIN - self::QR_SIDE, self::MARGIN);

        return $document;
    }

    /**
     * The QR code with its quiet zone of four modules, each run of dark modules in a row drawn as one rectangle.
     */
    private function qrCode(Document $document, QrCode $code, float $x, float $y): void
    {
        $module = self::QR_SIDE / ($code->size() + 8);
        $document->rectangle($x, $y, self::QR_SIDE, self::QR_SIDE, fill: Color::gray(255));
        $black = Color::black();

        foreach ($code->rows() as $row => $modules) {
            $top = $y + self::QR_SIDE - ($row + 5) * $module;
            preg_match_all('/1+/', $modules, $runs, \PREG_OFFSET_CAPTURE);

            foreach ($runs[0] as [$run, $column]) {
                $document->rectangle($x + ($column + 4) * $module, $top, \strlen($run) * $module, $module, fill: $black);
            }
        }
    }

    private function attachment(Certificate $certificate): Attach
    {
        $name = $certificate->isToken() ? 'credential.jwt' : 'credential.json';

        return new Attach(InputFile::fromContents($name, $certificate->credential), $name, $certificate->isToken() ? 'application/vc+jwt' : 'application/vc', Relationship::Data);
    }

    private function centered(Document $document, float $width, float $y, string $text, float $size, StandardFont|TrueTypeFont $font): void
    {
        $document->text(($width - $font->width($text, $size)) / 2, $y, $text, $size, $font);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function trans(string $key, array $parameters, string $locale): string
    {
        return $this->translator->trans($key, $parameters, self::DOMAIN, $locale);
    }

    private function date(DateTimeImmutable $date, string $locale): string
    {
        return (string) new IntlDateFormatter($locale, IntlDateFormatter::LONG, IntlDateFormatter::NONE)->format($date);
    }
}
