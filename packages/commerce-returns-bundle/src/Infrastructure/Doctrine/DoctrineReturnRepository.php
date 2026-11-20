<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnCreditNote;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;
use Phil\CommerceReturnsBundle\Domain\Rma\ReturnState;

/**
 * Returns and their credit notes through Doctrine: the authorized returns waiting too long are read in one query.
 */
final readonly class DoctrineReturnRepository implements ReturnRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function findReturn(string $id): ?ReturnRequest
    {
        return $this->entityManager->find(ReturnRequest::class, $id);
    }

    #[Override]
    public function addReturn(ReturnRequest $return): void
    {
        $this->entityManager->persist($return);
    }

    #[Override]
    public function returnsOf(string $orderNumber): array
    {
        return $this->entityManager->getRepository(ReturnRequest::class)->findBy(['orderNumber' => $orderNumber], ['requestedAt' => 'ASC']);
    }

    #[Override]
    public function authorizedBefore(DateTimeImmutable $before): array
    {
        /** @var list<ReturnRequest> */
        return $this->entityManager->createQuery(\sprintf('SELECT r FROM %s r WHERE r.state = :state AND r.changedAt < :before ORDER BY r.changedAt ASC', ReturnRequest::class))
            ->setParameter('state', ReturnState::Authorized)
            ->setParameter('before', $before, Types::DATETIME_IMMUTABLE)
            ->getResult();
    }

    #[Override]
    public function findCreditNote(string $returnId): ?ReturnCreditNote
    {
        return $this->entityManager->getRepository(ReturnCreditNote::class)->findOneBy(['returnId' => $returnId]);
    }

    #[Override]
    public function addCreditNote(ReturnCreditNote $note): void
    {
        $this->entityManager->persist($note);
    }
}
