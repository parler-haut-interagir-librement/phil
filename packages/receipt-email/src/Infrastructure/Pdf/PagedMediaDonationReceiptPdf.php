<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Infrastructure\Pdf;

use DateTimeImmutable;
use Phil\PagedMedia\Font\StandardFont;
use Phil\PagedMedia\Font\TrueTypeFont;
use Phil\PagedMedia\Image\Image;
use Phil\PagedMedia\Pdf\Conformance;
use Phil\PagedMedia\Pdf\Document;
use Phil\PagedMedia\Pdf\Metadata;
use Phil\PagedMedia\Pdf\PageSize;
use Phil\ReceiptEmail\Application\Port\DonationReceiptPdf;
use Phil\ReceiptEmail\Domain\ValueObject\DonationReceipt;
use Phil\ReceiptEmail\Domain\ValueObject\Party;

/**
 * An official donation receipt as a PDF, with `phil/paged-media`: the charity, its address and registration number,
 * the statement that it is an official receipt for income tax purposes, the serial number, where and when it was
 * issued, when the gift was received, the donor and their address, the gift's description and its appraiser for
 * property, its amount, the advantage and its description, the eligible amount, the signatory — under the image of
 * their signature when one is given —, and the tax authority's name and website.
 *
 * The labels are English, or French for a French locale; an application passes its own by locale or language.
 * Helvetica prints the Western languages; an application whose names need another script passes TrueType fonts, which
 * PDF/A-2u, for a receipt kept for years, also needs. Amounts are written in the gift's currency, named.
 */
final readonly class PagedMediaDonationReceiptPdf implements DonationReceiptPdf
{
    public const array ENGLISH = [
        'title' => 'Official receipt for income tax purposes',
        'registration' => 'Registration number: %s',
        'serial' => 'Serial number: %s',
        'issued_on' => 'Date issued: %s',
        'issued_in' => 'Location issued: %s',
        'received_on' => 'Date gift received: %s',
        'received_year' => 'Year gift received: %d',
        'donor' => 'Donor',
        'gift' => 'Description of the gift: %s',
        'appraiser' => 'Appraiser',
        'amount' => 'Amount of the gift',
        'advantage' => 'Amount of the advantage',
        'advantage_description' => 'Advantage: %s',
        'eligible' => 'Eligible amount of the gift',
        'signature' => 'Authorized signature: %s',
        'authority' => '%s: %s',
    ];

    public const array FRENCH = [
        'title' => 'Reçu officiel aux fins de l’impôt sur le revenu',
        'registration' => 'Numéro d’enregistrement : %s',
        'serial' => 'Numéro de série : %s',
        'issued_on' => 'Date de délivrance : %s',
        'issued_in' => 'Lieu de délivrance : %s',
        'received_on' => 'Date de réception du don : %s',
        'received_year' => 'Année de réception du don : %d',
        'donor' => 'Donateur',
        'gift' => 'Description du don : %s',
        'appraiser' => 'Évaluateur',
        'amount' => 'Montant du don',
        'advantage' => 'Montant de l’avantage',
        'advantage_description' => 'Avantage : %s',
        'eligible' => 'Montant admissible du don',
        'signature' => 'Signature autorisée : %s',
        'authority' => '%s : %s',
    ];

    private const float MARGIN = 54;

    private const float LINE = 14;

    private const float SIZE = 10;

    private PageSize $size;

    /**
     * @param array<string, array<string, string>> $labels      locale or language → `ENGLISH`'s keys, translated
     * @param Image|null                           $signature   the signatory's signature, printed above their name
     * @param Conformance|null                     $conformance PDF/A-2u for an archived receipt, with TrueType fonts
     */
    public function __construct(
        private array $labels = [],
        ?PageSize $size = null,
        private StandardFont|TrueTypeFont $font = StandardFont::Helvetica,
        private StandardFont|TrueTypeFont $bold = StandardFont::HelveticaBold,
        private ?Conformance $conformance = null,
        private ?Image $signature = null,
    ) {
        $this->size = $size ?? PageSize::letter();
    }

    public function render(DonationReceipt $receipt, string $locale): string
    {
        $labels = $this->labels($locale);
        $donation = $receipt->donation;
        $currency = ' '.$donation->amount->currency->code;
        $pdf = new Document($this->size, new Metadata($labels['title'].' '.$receipt->serialNumber, $donation->charity->name, createdAt: $receipt->issuedAt), conformance: $this->conformance);
        $pdf->addPage();

        $y = $this->size->height - self::MARGIN;

        $pdf->text(self::MARGIN, $y, $donation->charity->name, 16, $this->bold);
        $y = $this->lines($pdf, $y, [...$donation->charity->address, \sprintf($labels['registration'], $donation->registrationNumber)]);

        $y -= 2 * self::LINE;
        $pdf->text(self::MARGIN, $y, $labels['title'], 14, $this->bold);
        $y = $this->lines($pdf, $y, [
            \sprintf($labels['serial'], $receipt->serialNumber),
            \sprintf($labels['issued_on'], $receipt->issuedAt->format('Y-m-d')),
            \sprintf($labels['issued_in'], $donation->issuedIn),
            $donation->received instanceof DateTimeImmutable
                ? \sprintf($labels['received_on'], $donation->received->format('Y-m-d'))
                : \sprintf($labels['received_year'], $donation->received),
        ]);

        $y = $this->party($pdf, $y, $labels['donor'], $donation->donor);

        if (null !== $donation->giftDescription) {
            $y = $this->lines($pdf, $y - self::LINE, [\sprintf($labels['gift'], $donation->giftDescription)]);
        }

        if ($donation->appraiser instanceof Party) {
            $y = $this->party($pdf, $y, $labels['appraiser'], $donation->appraiser);
        }

        $right = $this->size->width - self::MARGIN;
        $y -= self::LINE;

        foreach ([[$labels['amount'], $donation->amount], [$labels['advantage'], $donation->advantage]] as [$label, $amount]) {
            $y = $this->room($pdf, $y);
            $pdf->text(self::MARGIN, $y, $label, self::SIZE, $this->font);
            $pdf->textRight($right, $y, $amount->toDecimal().$currency, self::SIZE, $this->font);
        }

        if (null !== $donation->advantageDescription) {
            $y = $this->lines($pdf, $y, [\sprintf($labels['advantage_description'], $donation->advantageDescription)]);
        }

        $y = $this->room($pdf, $y);
        $pdf->text(self::MARGIN, $y, $labels['eligible'], 12, $this->bold);
        $pdf->textRight($right, $y, $donation->eligibleAmount()->toDecimal().$currency, 12, $this->bold);

        $y -= 2 * self::LINE;

        if ($this->signature instanceof Image) {
            $height = 36.0;
            $width = $height * $this->signature->width / max(1, $this->signature->height);
            $y -= $height;

            if ($y < self::MARGIN) {
                $pdf->addPage();
                $y = $this->size->height - self::MARGIN - $height;
            }

            $pdf->image($this->signature, self::MARGIN, $y, $width, $height);
        }

        $y = $this->lines($pdf, $y, [\sprintf($labels['signature'], $donation->signatory)]);
        $this->lines($pdf, $y - self::LINE, [\sprintf($labels['authority'], DonationReceipt::authority($locale), DonationReceipt::AUTHORITY_WEBSITE)]);

        return $pdf->render();
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        $language = explode('_', $locale, 2)[0];

        return array_replace('fr' === $language ? self::FRENCH : self::ENGLISH, $this->labels[$language] ?? [], $this->labels[$locale] ?? []);
    }

    /**
     * The next line's baseline, on a new page when this one is full.
     */
    private function room(Document $pdf, float $y): float
    {
        $y -= self::LINE;

        if ($y >= self::MARGIN) {
            return $y;
        }

        $pdf->addPage();

        return $this->size->height - self::MARGIN;
    }

    private function party(Document $pdf, float $y, string $label, Party $party): float
    {
        $y = $this->room($pdf, $this->room($pdf, $y));
        $pdf->text(self::MARGIN, $y, $label, self::SIZE, $this->bold);

        return $this->lines($pdf, $y, [$party->name, ...$party->address]);
    }

    /**
     * Writes each text below the baseline given, cut to the width of the page, and returns the last baseline.
     *
     * @param list<string> $texts
     */
    private function lines(Document $pdf, float $y, array $texts): float
    {
        foreach ($texts as $text) {
            foreach (Document::wrap($text, $this->size->width - 2 * self::MARGIN, self::SIZE, $this->font) as $line) {
                $y = $this->room($pdf, $y);
                $pdf->text(self::MARGIN, $y, $line, self::SIZE, $this->font);
            }
        }

        return $y;
    }
}
