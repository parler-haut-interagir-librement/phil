<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CommerceStoredValueBundle\Domain\Instrument\GiftCardOrigin;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\ValueObject\GiftCardCode;
use Phil\CommerceStoredValueBundle\Infrastructure\Doctrine\DoctrineStoredValueRepository;
use Phil\CommerceStoredValueBundle\Tests\Functional\App\TestKernel;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(DoctrineStoredValueRepository::class)]
final class DoctrineStoredValueRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private DoctrineStoredValueRepository $repository;

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $this->entityManager = $entityManager;
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->repository = new DoctrineStoredValueRepository($entityManager);
    }

    public function testACardIsFoundByItsOwnCode(): void
    {
        $first = $this->card('AAAA-BBBB-CCCC-DDDD', '2026-11-03 10:00', 'A-1');
        $second = $this->card('EEEE-FFFF-GGGG-HHHH', '2026-11-03 10:00', 'A-1');
        $this->store();

        self::assertSame($second->id, $this->repository->findByCodeHash(GiftCardCode::fromString('EEEE-FFFF-GGGG-HHHH')->hash())?->id);
        self::assertSame($first->id, $this->repository->findByCodeHash(GiftCardCode::fromString('AAAA-BBBB-CCCC-DDDD')->hash())?->id);
        self::assertNull($this->repository->findByCodeHash(GiftCardCode::fromString('JJJJ-KKKK-LLLL-MMMM')->hash()));
    }

    public function testTheOriginOfACardIsReadBack(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00');
        [$given] = Instrument::giftCard(GiftCardCode::fromString('AAAA-BBBB-CCCC-DDDD'), $this->cad(1_000), $at, $at->modify('+90 days'), null, GiftCardOrigin::Given);
        [$service] = Instrument::giftCard(GiftCardCode::fromString('EEEE-FFFF-GGGG-HHHH'), $this->cad(1_000), $at, $at->modify('+1 year'), 'A-1', GiftCardOrigin::Paid, true);
        $credit = Instrument::storeCredit('ada', Currency::of('CAD'), $at);

        foreach ([$given, $service, $credit] as $instrument) {
            $this->repository->addInstrument($instrument);
        }

        $this->store();

        $read = static fn (?Instrument $instrument): array => [$instrument?->origin, $instrument?->specificService];
        self::assertSame([GiftCardOrigin::Given, false], $read($this->repository->findByCodeHash(GiftCardCode::fromString('AAAA-BBBB-CCCC-DDDD')->hash())));
        self::assertSame([GiftCardOrigin::Paid, true], $read($this->repository->findByCodeHash(GiftCardCode::fromString('EEEE-FFFF-GGGG-HHHH')->hash())));
        self::assertSame([null, false], $read($this->repository->findStoreCredit('ada', 'CAD')));
    }

    public function testTheCardsOfAnOrderAreCountedAndListedInTheOrderTheyWereOpened(): void
    {
        $late = $this->card('AAAA-BBBB-CCCC-DDDD', '2026-11-03 12:00', 'A-1');
        $early = $this->card('EEEE-FFFF-GGGG-HHHH', '2026-11-03 10:00', 'A-1');
        $this->card('JJJJ-KKKK-LLLL-MMMM', '2026-11-03 11:00', 'A-2');
        $this->store();

        self::assertSame([2, 1, 0], [$this->repository->issuedFor('A-1'), $this->repository->issuedFor('A-2'), $this->repository->issuedFor('A-3')]);
        self::assertSame([$early->id, $late->id], array_map(static fn (Instrument $card): string => $card->id, $this->repository->cardsIssuedFor('A-1')));
        self::assertSame([], $this->repository->cardsIssuedFor('A-3'));
    }

    public function testTheMovementsOfAnInstrumentAreListedInTheOrderTheyHappened(): void
    {
        $card = $this->card('AAAA-BBBB-CCCC-DDDD', '2026-11-03 12:00', 'A-1');
        $this->entityManager->persist($card->credit(MovementKind::Loaded, $this->cad(1_000), new DateTimeImmutable('2026-11-03 09:00')));
        $this->store();

        self::assertSame([MovementKind::Loaded, MovementKind::Issued], array_map(static fn (Movement $movement): MovementKind => $movement->kind, $this->repository->movementsOf($card->id)));
    }

    public function testARedemptionIsFoundByItsPayment(): void
    {
        $card = $this->card('AAAA-BBBB-CCCC-DDDD', '2026-11-03 10:00', 'A-1');
        $at = new DateTimeImmutable('2026-11-03 11:00');
        $this->entityManager->persist($card->redeem($this->cad(1_000), $at, 'A-2', 'p-1'));
        $this->entityManager->persist($card->redeem($this->cad(2_000), $at, 'A-3', 'p-2'));
        $this->store();

        self::assertSame([-2_000, -1_000], [$this->repository->redemptionOf('p-2')?->amount->minorUnits, $this->repository->redemptionOf('p-1')?->amount->minorUnits]);
        self::assertNull($this->repository->redemptionOf('p-3'));
    }

    public function testAStoreCreditIsFoundByItsHolderAndCurrency(): void
    {
        $at = new DateTimeImmutable('2026-11-03 10:00');
        $ada = Instrument::storeCredit('ada', Currency::of('CAD'), $at);
        $grace = Instrument::storeCredit('grace', Currency::of('CAD'), $at);
        $this->entityManager->persist($ada);
        $this->entityManager->persist($grace);
        $this->store();

        self::assertSame([$grace->id, $ada->id], [$this->repository->findStoreCredit('grace', 'CAD')?->id, $this->repository->findStoreCredit('ada', 'CAD')?->id]);
        self::assertNull($this->repository->findStoreCredit('ada', 'USD'));
    }

    private function card(string $code, string $at, string $orderNumber): Instrument
    {
        [$card, $issued] = Instrument::giftCard(GiftCardCode::fromString($code), $this->cad(5_000), new DateTimeImmutable($at), null, $orderNumber);
        $this->repository->addInstrument($card);
        $this->repository->addMovement($issued);

        return $card;
    }

    private function store(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
