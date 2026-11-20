<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnReason;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnLabel;
use Phil\CommerceReturnsBundle\Domain\ValueObject\ReturnSlip;
use Phil\CommerceReturnsBundle\Infrastructure\PagedMedia\PagedMediaReturnSlips;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorTrait;

#[CoversClass(PagedMediaReturnSlips::class)]
final class PagedMediaReturnSlipsTest extends TestCase
{
    public function testASlipIsAPdfInTheLocaleAsked(): void
    {
        $translator = new class implements TranslatorInterface {
            use TranslatorTrait {
                trans as private translate;
            }

            /** @var list<array{string, string, ?string}> */
            public array $asked = [];

            /**
             * @param array<array-key, mixed> $parameters
             */
            public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                $this->asked[] = [(string) $id, (string) $domain, $locale];

                return $this->translate($id, $parameters, $domain, $locale);
            }
        };

        $pdf = new PagedMediaReturnSlips($translator)->render($this->slip(2, ReturnLabel::of('carrier', 'TRACK-1')), 'fr');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('/Count 1', $pdf);
        self::assertContains(['phil_commerce_returns.slip.tracking', 'phil_commerce_returns', 'fr'], $translator->asked);
        self::assertContains(['phil_commerce_returns.reason.wrong_item', 'phil_commerce_returns', 'fr'], $translator->asked);
        self::assertContains(['phil_commerce_returns.slip.instructions', 'phil_commerce_returns', 'fr'], $translator->asked);
    }

    public function testManyLinesTakeMorePagesAndEnglishNeedsNoTranslator(): void
    {
        $pdf = new PagedMediaReturnSlips()->render($this->slip(60));

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertSame(1, preg_match('#/Type /Pages /Kids \[[^]]+\] /Count (\d+)#', $pdf, $count));
        self::assertGreaterThan(1, (int) $count[1]);
    }

    /**
     * Every figure of the layout, from the content of the page: the title, the facts and their values, the header of
     * the columns and its rule, a line and the instructions.
     */
    public function testTheLayoutOfASlip(): void
    {
        $slip = new ReturnSlip('RS-1', '0190', 'A-1', new DateTimeImmutable('2026-11-03 10:00:00'), [['line' => 'product:T', 'description' => 'Short', 'quantity' => 3, 'reason' => ReturnReason::WrongItem]], ReturnLabel::of('carrier', 'T-1'));
        $pdf = new PagedMediaReturnSlips()->render($slip);

        self::assertStringContainsString('/Title (Return slip RS-1)', $pdf);
        self::assertSame([<<<'PDF'
            BT /F1 20 Tf 54 718 Td (Return slip) Tj ET
            BT /F1 11 Tf 54 686 Td (Slip) Tj ET
            BT /F2 11 Tf 164 686 Td (RS-1) Tj ET
            BT /F1 11 Tf 54 670 Td (Order) Tj ET
            BT /F2 11 Tf 164 670 Td (A-1) Tj ET
            BT /F1 11 Tf 54 654 Td (Return) Tj ET
            BT /F2 11 Tf 164 654 Td (0190) Tj ET
            BT /F1 11 Tf 54 638 Td (Printed on) Tj ET
            BT /F2 11 Tf 164 638 Td (2026-11-03) Tj ET
            BT /F1 11 Tf 54 622 Td (Tracking) Tj ET
            BT /F2 11 Tf 164 622 Td (carrier T-1) Tj ET
            BT /F1 11 Tf 54 590 Td (Item) Tj ET
            BT /F1 11 Tf 420.5 590 Td (Units) Tj ET
            BT /F1 11 Tf 468 590 Td (Reason) Tj ET
            q 0.5 w 54 586 m 558 586 l S Q
            BT /F2 11 Tf 441.88 570 Td (3) Tj ET
            BT /F2 11 Tf 468 570 Td (Wrong item) Tj ET
            BT /F2 11 Tf 54 570 Td (Short) Tj ET
            BT /F2 11 Tf 54 538 Td (Put this slip in the parcel with the items listed above.) Tj ET
            PDF."\n"], $this->pages($pdf));
    }

    /**
     * The description wraps in 352 points: a text of 351.3 stays on a line, one of 352.6 does not.
     */
    public function testADescriptionWrapsAtTheWidthOfItsColumn(): void
    {
        $fits = str_repeat('a', 24).' '.str_repeat('i', 79).' z';
        $overflows = str_repeat('a', 25).' '.str_repeat('i', 77).' z';

        [$page] = $this->pages(new PagedMediaReturnSlips()->render($this->slipOf([$fits])));
        self::assertStringContainsString('BT /F2 11 Tf 54 586 Td ('.$fits.') Tj ET', $page);

        [$page] = $this->pages(new PagedMediaReturnSlips()->render($this->slipOf([$overflows])));
        self::assertStringContainsString('BT /F2 11 Tf 54 586 Td ('.str_repeat('a', 25).' '.str_repeat('i', 77).') Tj ET', $page);
        self::assertStringContainsString('BT /F2 11 Tf 54 570 Td (z) Tj ET', $page);
    }

    /**
     * Thirty lines fit under the facts of a slip with a label; the thirty-first goes on a page of its own, under the
     * header again.
     */
    public function testAPageEndsWhereTheNextLineWouldReachTheMargin(): void
    {
        $descriptions = array_map(static fn (int $item): string => 'Item '.$item, range(1, 31));
        [$first, $second] = $this->pages(new PagedMediaReturnSlips()->render($this->slipOf($descriptions, ReturnLabel::of('carrier', 'T-1'))));

        self::assertStringContainsString('BT /F2 11 Tf 54 106 Td (Item 30) Tj ET', $first);
        self::assertStringNotContainsString('Item 31', $first);
        self::assertStringNotContainsString('Put this slip', $first);
        self::assertStringStartsWith(<<<'PDF'
            BT /F1 11 Tf 54 727 Td (Item) Tj ET
            BT /F1 11 Tf 420.5 727 Td (Units) Tj ET
            BT /F1 11 Tf 468 727 Td (Reason) Tj ET
            q 0.5 w 54 723 m 558 723 l S Q
            BT /F2 11 Tf 441.88 707 Td (1) Tj ET
            BT /F2 11 Tf 468 707 Td (Wrong item) Tj ET
            BT /F2 11 Tf 54 707 Td (Item 31) Tj ET
            BT /F2 11 Tf 54 675 Td (Put this slip in the parcel with the items listed above.) Tj ET

            PDF, $second);
    }

    /**
     * A line of several rows does not start where the page cannot hold it all.
     */
    public function testALineThatDoesNotFitMovesToTheNextPageWhole(): void
    {
        $descriptions = array_fill(0, 29, 'Short');
        $descriptions[] = str_repeat('a', 25).' '.str_repeat('i', 77).' z';

        [$first, $second] = $this->pages(new PagedMediaReturnSlips()->render($this->slipOf($descriptions, ReturnLabel::of('carrier', 'T-1'))));

        self::assertStringContainsString('BT /F2 11 Tf 54 122 Td (Short) Tj ET', $first);
        self::assertStringNotContainsString('(z)', $first);
        self::assertStringContainsString('BT /F2 11 Tf 54 707 Td ('.str_repeat('a', 25).' '.str_repeat('i', 77).') Tj ET', $second);
        self::assertStringContainsString('BT /F2 11 Tf 54 691 Td (z) Tj ET', $second);
        self::assertStringContainsString('BT /F2 11 Tf 54 659 Td (Put this slip', $second);
    }

    /**
     * @param list<string> $descriptions
     */
    private function slipOf(array $descriptions, ?ReturnLabel $label = null): ReturnSlip
    {
        $listed = [];

        foreach ($descriptions as $i => $description) {
            $listed[] = ['line' => 'product:ITEM-'.$i, 'description' => $description, 'quantity' => 1, 'reason' => ReturnReason::WrongItem];
        }

        return new ReturnSlip('RS-1', '0190', 'A-1', new DateTimeImmutable('2026-11-03 10:00:00'), $listed, $label);
    }

    /**
     * The content of each page of a PDF, as the writer wrote it.
     *
     * @return list<string>
     */
    private function pages(string $pdf): array
    {
        self::assertGreaterThan(0, preg_match_all('#stream\n(.*?)\nendstream#s', $pdf, $streams));

        return array_map(static fn (string $stream): string => (string) gzuncompress($stream), $streams[1]);
    }

    private function slip(int $lines, ?ReturnLabel $label = null): ReturnSlip
    {
        $listed = [];

        for ($i = 1; $i <= $lines; ++$i) {
            $listed[] = ['line' => 'product:ITEM-'.$i, 'description' => 'A long description of item '.$i.' that the slip wraps on more than one line when the column is narrow enough', 'quantity' => $i, 'reason' => ReturnReason::WrongItem];
        }

        return new ReturnSlip('RS-00000001', '0190-return', 'A-1', new DateTimeImmutable('2026-11-03 10:00:00'), $listed, $label);
    }
}
