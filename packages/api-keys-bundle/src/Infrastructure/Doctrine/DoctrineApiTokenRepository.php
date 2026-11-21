<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Model\ApiToken;
use Phil\ApiKeysBundle\Domain\Token\TokenOwner;

/**
 * The tokens in the table `phil_api_token`, through the entity manager. A save is flushed; the last use and the purge
 * are single statements, which flush nothing else of the unit of work.
 */
final readonly class DoctrineApiTokenRepository implements ApiTokenRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(string $id): ?ApiToken
    {
        return $this->entityManager->find(ApiToken::class, $id);
    }

    #[Override]
    public function find(?TokenOwner $owner, int $limit): array
    {
        return $this->entityManager->getRepository(ApiToken::class)->findBy(
            $owner instanceof TokenOwner ? ['ownerType' => $owner->type, 'owner' => $owner->id] : [],
            ['createdAt' => 'DESC', 'id' => 'ASC'],
            $limit,
        );
    }

    #[Override]
    public function save(ApiToken ...$tokens): void
    {
        foreach ($tokens as $token) {
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function recordUse(ApiToken $token): void
    {
        $this->entityManager->createQuery(\sprintf('UPDATE %s t SET t.lastUsedAt = :at, t.lastUsedAddress = :address WHERE t.id = :id', ApiToken::class))
            ->setParameter('at', $token->lastUsedAt(), Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('address', $token->lastUsedAddress())
            ->setParameter('id', $token->id())
            ->execute();
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $removed = $this->entityManager->createQuery(\sprintf('DELETE FROM %s t WHERE t.expiresAt < :before OR t.revokedAt < :before', ApiToken::class))
            ->setParameter('before', $before, Types::DATETIME_UTC_IMMUTABLE)
            ->execute();

        return \is_int($removed) ? $removed : 0;
    }
}
