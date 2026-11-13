<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\LibreOffice;

use BackedEnum;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Gotenberg\Profile;
use ReflectionObject;
use SensitiveParameter;

/**
 * The options of a LibreOffice conversion, by Gotenberg's names; an option left unset keeps LibreOffice's default. A
 * name that starts with `native` asks LibreOffice itself; a stage does the same job on the PDF afterwards, the same way
 * for every route: `nativeWatermarkText` has `stamp-text`, `nativePdfa` has `remediate`.
 */
final readonly class LibreOfficeOptions
{
    private const array RESOLUTIONS = [75, 150, 300, 600, 1200];

    private const array NATIVE_PDFA = [Profile::PdfA1b, Profile::PdfA2b, Profile::PdfA3b, Profile::PdfA4];

    /**
     * @param string|null       $password                 opens a password-protected document
     * @param float|string|null $paperWidth               inches, or a number with a unit: `210mm`, `21cm`, `595pt`, `794px`, `50pc`, `8.5in`
     * @param string|null       $nativePages              pages to export, in LibreOffice's syntax: `1-4`, `1,3,5-7`
     * @param bool|null         $singlePage               puts each sheet of a spreadsheet on one page
     * @param int|null          $nativeQuality            the JPEG quality of the images, from 1 to 100
     * @param int|null          $nativeMaxImageResolution 75, 150, 300, 600 or 1200 DPI
     * @param string|null       $nativeWatermarkColor     `#rrggbb`
     * @param Profile|null      $nativePdfa               PDF/A-1b, PDF/A-2b, PDF/A-3b or PDF/A-4
     * @param Profile|null      $nativePdfua              PDF/UA-1
     * @param int|null          $openBookmarkLevels       from 1 to 10, or -1 for all
     */
    public function __construct(
        #[SensitiveParameter]
        public ?string $password = null,
        public ?PaperSize $paperSize = null,
        public float|string|null $paperWidth = null,
        public float|string|null $paperHeight = null,
        public ?bool $landscape = null,
        public ?string $nativePages = null,
        public ?bool $singlePage = null,
        public ?bool $skipEmptyPages = null,
        public ?bool $updateIndexes = null,
        public ?bool $exportFormFields = null,
        public ?bool $allowDuplicateFieldNames = null,
        public ?bool $nativeBookmarks = null,
        public ?bool $exportBookmarksToPdfDestination = null,
        public ?bool $exportPlaceholders = null,
        public ?bool $exportNotes = null,
        public ?bool $exportNotesInMargin = null,
        public ?bool $exportNotesPages = null,
        public ?bool $exportOnlyNotesPages = null,
        public ?bool $exportHiddenSlides = null,
        public ?bool $useTransitionEffects = null,
        public ?bool $convertOooTargetToPdfTarget = null,
        public ?bool $exportLinksRelativeFsys = null,
        public ?bool $addOriginalDocumentAsStream = null,
        public ?int $nativeQuality = null,
        public ?bool $nativeLosslessImageCompression = null,
        public ?bool $nativeReduceImageResolution = null,
        public ?int $nativeMaxImageResolution = null,
        public ?string $nativeWatermarkText = null,
        public ?string $nativeWatermarkColor = null,
        public ?int $nativeWatermarkFontSize = null,
        public ?float $nativeWatermarkRotation = null,
        public ?string $nativeWatermarkFont = null,
        public ?string $nativeTiledWatermarkText = null,
        public ?Profile $nativePdfa = null,
        public ?Profile $nativePdfua = null,
        public ?bool $nativeTaggedPdf = null,
        public ?InitialView $initialView = null,
        public ?int $initialPage = null,
        public ?Magnification $magnification = null,
        public ?int $zoom = null,
        public ?PageLayout $pageLayout = null,
        public ?int $openBookmarkLevels = null,
        public ?bool $resizeWindowToInitialPage = null,
        public ?bool $centerWindow = null,
        public ?bool $openInFullScreenMode = null,
        public ?bool $displayPDFDocumentTitle = null,
        public ?bool $hideViewerMenubar = null,
        public ?bool $hideViewerToolbar = null,
        public ?bool $hideViewerWindowControls = null,
    ) {
        $this->checkLength('paperWidth', $paperWidth);
        $this->checkLength('paperHeight', $paperHeight);

        if (!$paperSize instanceof PaperSize && (null === $paperWidth) !== (null === $paperHeight)) {
            throw InvalidRequest::because('Without a paper size, a paper width goes with a paper height.');
        }

        $this->checkRange('nativeQuality', $nativeQuality, 1, 100);
        $this->checkRange('nativeWatermarkFontSize', $nativeWatermarkFontSize, 1, \PHP_INT_MAX);
        $this->checkRange('initialPage', $initialPage, 1, \PHP_INT_MAX);
        $this->checkRange('zoom', $zoom, 1, \PHP_INT_MAX);

        if (null !== $nativeMaxImageResolution && !\in_array($nativeMaxImageResolution, self::RESOLUTIONS, true)) {
            throw InvalidRequest::because(\sprintf('nativeMaxImageResolution is 75, 150, 300, 600 or 1200; %d is not.', $nativeMaxImageResolution));
        }

        if (null !== $openBookmarkLevels && -1 !== $openBookmarkLevels) {
            $this->checkRange('openBookmarkLevels', $openBookmarkLevels, 1, 10);
        }

        if (null !== $nativeWatermarkText && null !== $nativeTiledWatermarkText) {
            throw InvalidRequest::because('A conversion takes nativeWatermarkText or nativeTiledWatermarkText, not both.');
        }

        if (null !== $nativeWatermarkColor && (7 !== \strlen($nativeWatermarkColor) || '#' !== $nativeWatermarkColor[0] || !ctype_xdigit(ltrim($nativeWatermarkColor, '#')))) {
            throw InvalidRequest::because(\sprintf('nativeWatermarkColor is written #rrggbb; "%s" is not.', $nativeWatermarkColor));
        }

        if ($nativePdfa instanceof Profile && !\in_array($nativePdfa, self::NATIVE_PDFA, true)) {
            throw InvalidRequest::because(\sprintf('LibreOffice exports PDF/A-1b, PDF/A-2b, PDF/A-3b or PDF/A-4; %s is reached by the remediate stage.', $nativePdfa->value));
        }

        if ($nativePdfua instanceof Profile && Profile::PdfUa1 !== $nativePdfua) {
            throw InvalidRequest::because(\sprintf('LibreOffice exports PDF/UA-1; %s is reached by the remediate stage.', $nativePdfua->value));
        }

        if (Profile::PdfA4 === $nativePdfa && $nativePdfua instanceof Profile) {
            throw InvalidRequest::because('LibreOffice does not make PDF/A-4 and PDF/UA together: pair the profiles in the remediate stage instead.');
        }
    }

    /**
     * The form fields, each option set written as Gotenberg reads it.
     *
     * @return array<string, string>
     */
    public function fields(): array
    {
        $fields = [];

        foreach (new ReflectionObject($this)->getProperties() as $property) {
            $text = $this->text($property->getValue($this));

            if (null !== $text) {
                $fields[$property->getName()] = $text;
            }
        }

        return $fields;
    }

    private function text(mixed $value): ?string
    {
        return match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            \is_int($value), \is_float($value) => (string) $value,
            \is_string($value) => $value,
            $value instanceof BackedEnum => self::text($value->value),
            default => null,
        };
    }

    private function checkLength(string $name, float|string|null $length): void
    {
        if (null === $length) {
            return;
        }

        $number = \is_string($length) ? preg_replace('/(mm|cm|in|pt|px|pc)$/', '', $length) : $length;

        if (!is_numeric($number) || $number <= 0) {
            throw InvalidRequest::because(\sprintf('%s is a number above zero, in inches or with a unit (mm, cm, in, pt, px, pc); "%s" is not.', $name, $length));
        }
    }

    private function checkRange(string $name, ?int $value, int $min, int $max): void
    {
        if (null !== $value && ($value < $min || $value > $max)) {
            throw InvalidRequest::because(\sprintf('%s is from %d to %d; %d is not.', $name, $min, $max, $value));
        }
    }
}
