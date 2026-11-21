<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\LinkTokenRepository;
use Phil\IdentityProviderBundle\Domain\Model\Link\LinkToken;

/**
 * The link tokens in `phil_identity_link_token`; a token is consumed by a DELETE that the database counts, so that two
 * uses at once remove it once. A token is read by a query, never from the unit of work alone, so that one removed by
 * such a DELETE is gone for the next read too; the unit of work is left as it is, the other entities it holds still
 * managed.
 */
final readonly class DoctrineLinkTokenRepository implements LinkTokenRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?LinkToken
    {
        $token = $this->entityManager->createQuery(\sprintf('SELECT t FROM %s t WHERE t.id = :id', LinkToken::class))
            ->setParameter('id', $id)
            ->getOneOrNullResult();
        \assert(null === $token || $token instanceof LinkToken);

        return $token;
    }

    #[Override]
    public function save(LinkToken $token): void
    {
        $this->entityManager->persist($token);
        $this->entityManager->flush();
    }

    #[Override]
    public function consume(string $id): bool
    {
        return 1 === $this->delete('t.id = :id', ['id' => $id]);
    }

    #[Override]
    public function revoke(string $accountId, ?string $purpose = null): int
    {
        return null === $purpose
            ? $this->delete('t.accountId = :account', ['account' => $accountId])
            : $this->delete('t.accountId = :account AND t.purpose = :purpose', ['account' => $accountId, 'purpose' => $purpose]);
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        return $this->delete('t.expiresAt < :before', ['before' => $before]);
    }

    /**
     * @param array<string, string|DateTimeImmutable> $parameters
     */
    private function delete(string $condition, array $parameters): int
    {
        $query = $this->entityManager->createQuery(\sprintf('DELETE FROM %s t WHERE %s', LinkToken::class, $condition));

        foreach ($parameters as $name => $value) {
            $query->setParameter($name, $value, $value instanceof DateTimeImmutable ? Types::DATETIME_UTC_IMMUTABLE : null);
        }

        $removed = $query->execute();
        \assert(\is_int($removed));

        return $removed;
    }
}
