<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\AccountSessionRepository;
use Phil\IdentityProviderBundle\Domain\Model\Session\AccountSession;

/**
 * The signed-in sessions in `phil_identity_session`; each save is flushed, the purge is one statement.
 */
final readonly class DoctrineAccountSessionRepository implements AccountSessionRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?AccountSession
    {
        return $this->entityManager->find(AccountSession::class, $id);
    }

    #[Override]
    public function openFor(string $accountId): array
    {
        return $this->entityManager->getRepository(AccountSession::class)->findBy(['accountId' => $accountId, 'closedAt' => null], ['lastSeenAt' => 'DESC', 'id' => 'ASC']);
    }

    #[Override]
    public function hasAny(string $accountId): bool
    {
        return null !== $this->entityManager->getRepository(AccountSession::class)->findOneBy(['accountId' => $accountId]);
    }

    #[Override]
    public function knowsDevice(string $accountId, string $device): bool
    {
        return null !== $this->entityManager->getRepository(AccountSession::class)->findOneBy(['accountId' => $accountId, 'device' => $device]);
    }

    #[Override]
    public function save(AccountSession ...$sessions): void
    {
        foreach ($sessions as $session) {
            $this->entityManager->persist($session);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $removed = $this->entityManager->createQuery(\sprintf('DELETE FROM %s s WHERE s.closedAt IS NOT NULL OR s.lastSeenAt < :before', AccountSession::class))
            ->setParameter('before', $before, Types::DATETIME_UTC_IMMUTABLE)
            ->execute();

        return \is_int($removed) ? $removed : 0;
    }
}
