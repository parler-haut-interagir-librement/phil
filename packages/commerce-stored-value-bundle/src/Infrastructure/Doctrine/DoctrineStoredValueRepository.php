<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Infrastructure\Doctrine;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceStoredValueBundle\Domain\Instrument\MovementKind;
use Phil\CommerceStoredValueBundle\Domain\Model\Instrument;
use Phil\CommerceStoredValueBundle\Domain\Model\Movement;
use Phil\CommerceStoredValueBundle\Domain\Repository\InstrumentRepository;

/**
 * Gift cards, store credits and their movements through Doctrine.
 *
 * The cards an order issues are stored by an entity manager of their own, on the same connection: a unique constraint
 * refusing them closes that manager only, and leaves the application's own, with what it holds, open — and the
 * connection usable, the refusal rolled back to a savepoint when the caller had begun a transaction.
 */
final readonly class DoctrineStoredValueRepository implements InstrumentRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findInstrument(string $id): ?Instrument
    {
        return $this->entityManager->find(Instrument::class, $id);
    }

    #[Override]
    public function findByCodeHash(string $codeHash): ?Instrument
    {
        return $this->entityManager->getRepository(Instrument::class)->findOneBy(['codeHash' => $codeHash]);
    }

    #[Override]
    public function findStoreCredit(string $holder, string $currency): ?Instrument
    {
        // Only a store credit has a holder.
        return $this->entityManager->getRepository(Instrument::class)->findOneBy(['holder' => $holder, 'currencyCode' => $currency]);
    }

    #[Override]
    public function addInstrument(Instrument $instrument): void
    {
        $this->entityManager->persist($instrument);
    }

    #[Override]
    public function addMovement(Movement $movement): void
    {
        $this->entityManager->persist($movement);
    }

    #[Override]
    public function addIssuedCards(array $cards): bool
    {
        // The application's listeners see these cards stored, as any other entity.
        $events = $this->entityManager->getEventManager();
        $own = new EntityManager($this->entityManager->getConnection(), $this->entityManager->getConfiguration(), $events instanceof EventManager ? $events : null);

        foreach ($cards as [$card, $movement]) {
            $own->persist($card);
            $own->persist($movement);
        }

        try {
            $own->flush();
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function movementUnderKey(string $instrument, string $idempotencyKey): ?Movement
    {
        return $this->entityManager->getRepository(Movement::class)->findOneBy(['instrument' => $instrument, 'idempotencyKey' => $idempotencyKey]);
    }

    #[Override]
    public function redemptionOf(string $payment): ?Movement
    {
        return $this->entityManager->getRepository(Movement::class)->findOneBy(['payment' => $payment, 'kind' => MovementKind::Redeemed]);
    }

    #[Override]
    public function issuedFor(string $orderNumber): int
    {
        return $this->entityManager->getRepository(Movement::class)->count(['orderNumber' => $orderNumber, 'kind' => MovementKind::Issued]);
    }

    #[Override]
    public function cardsIssuedFor(string $orderNumber): array
    {
        $ids = array_map(
            static fn (Movement $movement): string => $movement->instrument,
            $this->entityManager->getRepository(Movement::class)->findBy(['orderNumber' => $orderNumber, 'kind' => MovementKind::Issued]),
        );

        return $this->entityManager->getRepository(Instrument::class)->findBy(['id' => $ids], ['openedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function movementsOf(string $instrument): array
    {
        return $this->entityManager->getRepository(Movement::class)->findBy(['instrument' => $instrument], ['occurredAt' => 'ASC', 'id' => 'ASC']);
    }
}
