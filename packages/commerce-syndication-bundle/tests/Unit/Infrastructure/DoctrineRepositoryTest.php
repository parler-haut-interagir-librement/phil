<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\ORM\EntityManager;
use Phil\CommerceSyndicationBundle\Domain\Model\FedSku;
use Phil\CommerceSyndicationBundle\Domain\Model\LedgerEntry;
use Phil\CommerceSyndicationBundle\Domain\Model\OutgoingMessage;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\DoctrineSyndicationRepository;
use Phil\CommerceSyndicationBundle\Infrastructure\Doctrine\FedSkuIndex;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Database;
use Phil\CommerceSyndicationBundle\Tests\Unit\Support\Partners;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DoctrineSyndicationRepository::class)]
#[CoversClass(FedSkuIndex::class)]
#[CoversClass(FedSku::class)]
final class DoctrineRepositoryTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineSyndicationRepository $repository;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineSyndicationRepository($this->entityManager);
        $this->at = new DateTimeImmutable('2026-11-03 10:00:00');
    }

    public function testTheActiveResellersAreByCode(): void
    {
        $suspended = Partners::reseller('c-suspended');
        $suspended->suspend();

        foreach ([Partners::reseller('b-reseller'), Partners::supplier('a-supplier'), $suspended, Partners::reseller('a-reseller')] as $partner) {
            $this->repository->addPartner($partner);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['a-reseller', 'b-reseller'], array_map(static fn (Partner $partner): string => $partner->code, $this->repository->activeResellers()));
    }

    /**
     * The resellers fed a SKU come from the index the flush listener keeps: active resellers only, by code, and a
     * change of the list, or of anything else, keeps the index in step.
     */
    public function testTheResellersFedASkuComeFromTheIndex(): void
    {
        $suspended = Partners::reseller('d-suspended', ['TEA-1']);
        $suspended->suspend();

        foreach ([Partners::reseller('c-reseller', ['TEA-1', 'MUG']), Partners::supplier('a-supplier'), $suspended, Partners::reseller('b-reseller', ['MUG']), Partners::reseller('a-reseller', ['CUP', 'TEA-1'])] as $partner) {
            $this->repository->addPartner($partner);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['a-reseller', 'c-reseller'], $this->codes($this->repository->resellersFed('TEA-1')));
        self::assertSame(['b-reseller', 'c-reseller'], $this->codes($this->repository->resellersFed('MUG')));
        self::assertSame([], $this->codes($this->repository->resellersFed('PEN')));

        $this->repository->findPartner('c-reseller')?->feed(['MUG', 'PEN'], 300_000);
        $this->repository->findPartner('a-reseller')?->feed(['TEA-1', 'CUP'], 100_000);
        $this->repository->findPartner('d-suspended')?->resume();
        $this->repository->findPartner('b-reseller')?->suspend();
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['a-reseller', 'd-suspended'], $this->codes($this->repository->resellersFed('TEA-1')));
        self::assertSame(['c-reseller'], $this->codes($this->repository->resellersFed('MUG')));
        self::assertSame(['c-reseller'], $this->codes($this->repository->resellersFed('PEN')));
        self::assertSame(['a-reseller'], $this->codes($this->repository->resellersFed('CUP')));
        self::assertSame(6, $this->entityManager->getRepository(FedSku::class)->count([]), 'one row per reseller and SKU, nothing left behind');
    }

    /**
     * Another entity flushed before a partner, or a partner changed without its list before one whose list changed,
     * leaves the index of the next ones kept.
     */
    public function testTheIndexSkipsWhatItDoesNotConcern(): void
    {
        $this->repository->recordPrice(PublishedPrice::of(Partners::reseller('a-reseller'), 'TEA-1', $this->cad(1_000), $this->cad(700), $this->at));
        $this->repository->addPartner(Partners::reseller('a-reseller', ['TEA-1']));
        $this->repository->addPartner(Partners::reseller('b-reseller', ['TEA-1']));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['a-reseller', 'b-reseller'], $this->codes($this->repository->resellersFed('TEA-1')));

        $first = $this->repository->findPartner('a-reseller');
        $second = $this->repository->findPartner('b-reseller');
        $first?->reach('https://a.example/in', $first->publicKey());
        $second?->feed(['MUG'], 100_000);
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['a-reseller'], $this->codes($this->repository->resellersFed('TEA-1')));
        self::assertSame(['b-reseller'], $this->codes($this->repository->resellersFed('MUG')));
    }

    public function testAClaimedMessageKnowsUntilWhen(): void
    {
        $message = OutgoingMessage::to(Partners::reseller(), 'product.updated', [], $this->at);
        $this->repository->queue($message);
        $this->entityManager->flush();

        self::assertTrue($this->repository->claim($message, $this->at, $this->at->modify('+30 minutes')));
        self::assertSame('2026-11-03 10:30:00', $message->claimedUntil()?->format('Y-m-d H:i:s'));
        self::assertFalse($message->isDue($this->at->modify('+29 minutes')));
    }

    /**
     * The latest price is the one published last to that reseller, whatever the order it was recorded in.
     */
    public function testTheLatestPriceIsTheLastPublishedToThatReseller(): void
    {
        $reseller = Partners::reseller();
        $other = Partners::reseller('other');
        $this->repository->recordPrice(PublishedPrice::of($reseller, 'TEA-1', $this->cad(1_200), $this->cad(840), $this->at));
        $this->repository->recordPrice(PublishedPrice::of($reseller, 'TEA-1', $this->cad(1_000), $this->cad(700), $this->at->modify('-1 day')));
        $this->repository->recordPrice(PublishedPrice::of($other, 'TEA-1', $this->cad(1_500), $this->cad(1_050), $this->at->modify('+1 day')));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(840, $this->repository->latestPrice('reseller', 'TEA-1', 'CAD')?->cost->minorUnits);
        self::assertSame(1_050, $this->repository->latestPrice('other', 'TEA-1', 'CAD')?->cost->minorUnits);
    }

    /**
     * The latest prices of one SKU for several resellers in one read: the last published to each, the greater id
     * between two published at the same moment, and nothing for a reseller never sent it in that currency.
     */
    public function testTheLatestPricesAreReadForSeveralResellersAtOnce(): void
    {
        $first = Partners::reseller('first');
        $second = Partners::reseller('second');
        $tied = [PublishedPrice::of($second, 'TEA-1', $this->cad(1_000), $this->cad(600), $this->at), PublishedPrice::of($second, 'TEA-1', $this->cad(1_000), $this->cad(650), $this->at)];
        usort($tied, static fn (PublishedPrice $left, PublishedPrice $right): int => $right->id <=> $left->id);

        foreach ([
            PublishedPrice::of($first, 'TEA-1', $this->cad(1_200), $this->cad(840), $this->at),
            PublishedPrice::of($first, 'TEA-1', $this->cad(1_300), $this->cad(910), $this->at->modify('-1 day')),
            PublishedPrice::of($first, 'MUG', $this->cad(500), $this->cad(350), $this->at->modify('+1 day')),
            PublishedPrice::of($first, 'TEA-1', Money::of(1_500, Currency::of('USD')), Money::of(1_000, Currency::of('USD')), $this->at->modify('+1 day')),
            ...$tied,
            PublishedPrice::of(Partners::reseller('third'), 'TEA-1', $this->cad(100), $this->cad(70), $this->at),
        ] as $price) {
            $this->repository->recordPrice($price);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $latest = $this->repository->latestPrices('TEA-1', 'CAD', ['first', 'second', 'none']);
        self::assertSame(['first' => 840, 'second' => $tied[0]->cost->minorUnits], array_map(static fn (PublishedPrice $price): int => $price->cost->minorUnits, $latest));
        self::assertSame($tied[0]->cost->minorUnits, $this->repository->latestPrice('second', 'TEA-1', 'CAD')?->cost->minorUnits, 'the same price as latestPrice()');
        self::assertSame([], $this->repository->latestPrices('TEA-1', 'CAD', []));
        self::assertSame(['first'], array_keys($this->repository->latestPrices('TEA-1', 'USD', ['first', 'second'])));
    }

    /**
     * The prices in effect for several SKUs in one read: per SKU, the last before the window then every one within,
     * oldest first; a SKU with nothing in effect is absent.
     */
    public function testThePricesInEffectAreReadForSeveralSkusAtOnce(): void
    {
        $reseller = Partners::reseller();
        $since = $this->at->modify('-7 days');

        foreach ([
            ['TEA-1', 700, '-20 days'], ['TEA-1', 770, '-10 days'], ['TEA-1', 910, '-1 day'], ['TEA-1', 840, '-2 days'],
            ['MUG', 350, '-30 days'], ['MUG', 360, '-8 days'],
            ['CUP', 100, '-3 days'],
        ] as [$sku, $cost, $when]) {
            $this->repository->recordPrice(PublishedPrice::of($reseller, $sku, $this->cad($cost * 2), $this->cad($cost), $this->at->modify($when)));
        }

        $this->repository->recordPrice(PublishedPrice::of(Partners::reseller('other'), 'TEA-1', $this->cad(2), $this->cad(1), $this->at));
        $this->repository->recordPrice(PublishedPrice::of(Partners::reseller('other'), 'PEN', $this->cad(2), $this->cad(1), $this->at));

        $this->entityManager->flush();
        $this->entityManager->clear();

        $inEffect = $this->repository->pricesInEffectSince('reseller', ['TEA-1', 'MUG', 'CUP', 'PEN'], 'CAD', $since);
        ksort($inEffect);

        self::assertSame(
            ['CUP' => [100], 'MUG' => [360], 'TEA-1' => [770, 840, 910]],
            array_map(static fn (array $prices): array => array_map(static fn (PublishedPrice $price): int => $price->cost->minorUnits, $prices), $inEffect),
        );
        self::assertSame([], $this->repository->pricesInEffectSince('reseller', [], 'CAD', $since));
        self::assertSame([], $this->repository->pricesInEffectSince('reseller', ['TEA-1'], 'USD', $since));
    }

    public function testTheOpenEntriesAreAPartnersOwnInTheOrderRecorded(): void
    {
        $supplier = Partners::supplier();
        // Neither the order of the numbers nor the order of recording is the order of the moments.
        $this->repository->record(LedgerEntry::of($supplier, 'A-1', $this->cad(-300), $this->at->modify('+1 hour')));
        $this->repository->record(LedgerEntry::of(Partners::supplier('other'), 'B-1', $this->cad(-500), $this->at));
        $this->repository->record(LedgerEntry::of($supplier, 'A-2', $this->cad(-700), $this->at));

        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame(['A-2', 'A-1'], array_map(static fn (LedgerEntry $entry): string => $entry->orderNumber, $this->repository->openEntries('supplier')));
    }

    /**
     * @param list<Partner> $partners
     *
     * @return list<string>
     */
    private function codes(array $partners): array
    {
        return array_map(static fn (Partner $partner): string => $partner->code, $partners);
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
