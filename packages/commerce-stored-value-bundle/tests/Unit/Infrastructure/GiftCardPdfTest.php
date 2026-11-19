<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceStoredValueBundle\Application\IssuedGiftCard;
use Phil\CommerceStoredValueBundle\Domain\Instrument\GiftCardOrigin;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;
use Phil\CommerceStoredValueBundle\Infrastructure\Pdf\GiftCardPdf;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GiftCardPdf::class)]
final class GiftCardPdfTest extends TestCase
{
    private const string CODE = '7KQ2-M9XA-3TFD-W8HC';

    public function testACardIsPrintedTheSizeOfAPaymentCardWithItsShopValueAndCode(): void
    {
        $pdf = new GiftCardPdf('La Boutique', compress: false)->render($this->issued());

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertMatchesRegularExpression('#/MediaBox \[0 0 242\.65 153\.01\]#', $pdf, 'An ID-1 card: 85.6 by 53.98 millimetres.');
        self::assertSame(['La Boutique', 'Gift card', '50.00 CAD', self::CODE], $this->texts($pdf));
        self::assertStringContainsString('/Title (Gift card)', $pdf);
        self::assertStringContainsString('/Author (La Boutique)', $pdf);
    }

    public function testAGivenCardShowsItsExpiry(): void
    {
        $at = new DateTimeImmutable('2026-11-11 10:00:00');
        $pdf = new GiftCardPdf('Shop', compress: false)->render($this->issued($at->modify('+90 days'), GiftCardOrigin::Given));

        self::assertSame(['Shop', 'Gift card', '50.00 CAD', self::CODE, 'Valid until 2027-02-09'], $this->texts($pdf));
    }

    public function testACardForASpecificServiceSaysSoInTheLanguageGiven(): void
    {
        $at = new DateTimeImmutable('2026-11-11 10:00:00');
        $labels = ['gift_card' => 'Carte-cadeau', 'valid_until' => "Valide jusqu'au %s", 'specific_service' => 'Pour un service précis'];
        $pdf = new GiftCardPdf('Le Spa', $labels, compress: false)->render($this->issued($at->modify('+1 year'), GiftCardOrigin::Paid, true));
        $texts = $this->texts($pdf);

        self::assertSame(['Le Spa', 'Carte-cadeau', '50.00 CAD', self::CODE], \array_slice($texts, 0, 4));
        self::assertSame(mb_convert_encoding("Pour un service précis - Valide jusqu'au 2027-11-11", 'Windows-1252', 'UTF-8'), stripslashes($texts[4]));
    }

    /**
     * The whole page, byte for byte: the frame, and where each text is drawn, at which size and in which gray.
     */
    public function testACardIsLaidOutAsDrawn(): void
    {
        $at = new DateTimeImmutable('2026-11-11 10:00:00');

        self::assertStringEqualsFile(__DIR__.'/Fixtures/gift-card.pdf', new GiftCardPdf('La Boutique', compress: false)->render($this->issued($at->modify('+1 year'), GiftCardOrigin::Paid, true)));
    }

    /**
     * The shop's name takes the width the title leaves, less 6 points: a name just narrower fits whole, one just
     * wider loses its last letter.
     */
    public function testTheShopTakesTheWidthTheTitleLeaves(): void
    {
        self::assertSame('BoutiqueFromagerieFromagerieCartes', $this->texts(new GiftCardPdf('BoutiqueFromagerieFromagerieCartesNord', compress: false)->render($this->issued()))[0]);
        self::assertSame('BoutiqueBoutiqueBoutiqueFromageri', $this->texts(new GiftCardPdf('BoutiqueBoutiqueBoutiqueFromagerieNord', compress: false)->render($this->issued()))[0]);
    }

    public function testALongShopNameKeepsClearOfTheTitle(): void
    {
        $pdf = new GiftCardPdf(str_repeat('Boutique ', 10), compress: false)->render($this->issued());
        $shop = $this->texts($pdf)[0];

        self::assertStringStartsWith('Boutique', $shop);
        self::assertLessThan(\strlen(str_repeat('Boutique ', 10)), \strlen($shop));
    }

    public function testACardIsPrintedWithItsShop(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A gift card is printed with the name of its shop.');

        new GiftCardPdf(' ');
    }

    public function testThePageIsCompressedByDefault(): void
    {
        self::assertStringNotContainsString(self::CODE, new GiftCardPdf('Shop')->render($this->issued()));
    }

    private function issued(?DateTimeImmutable $expiresAt = null, GiftCardOrigin $origin = GiftCardOrigin::Paid, bool $specificService = false): IssuedGiftCard
    {
        $code = GiftCardCode::fromString(self::CODE);
        [$card] = Instrument::giftCard($code, Money::of(5_000, Currency::of('CAD')), new DateTimeImmutable('2026-11-11 10:00:00'), $expiresAt, null, $origin, $specificService);

        return new IssuedGiftCard($card, $code);
    }

    /**
     * @return list<string> the strings the page shows, in the order drawn
     */
    private function texts(string $pdf): array
    {
        preg_match_all('/\((.*?)(?<!\\\\)\) Tj/', $pdf, $texts);

        return $texts[1];
    }
}
