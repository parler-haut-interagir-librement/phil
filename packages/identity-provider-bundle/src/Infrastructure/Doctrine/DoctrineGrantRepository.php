<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\UsedAssertion;

/**
 * The codes in `phil_identity_code` and the tokens in `phil_identity_token`; each save is flushed, the purge is two
 * statements. A code is used, and a refresh token rotated, by one conditional `UPDATE`, whose count of rows tells the
 * one request that won; a client assertion is recorded by one `INSERT` on the connection, outside the unit of work, the
 * key of the row refusing the second.
 */
final readonly class DoctrineGrantRepository implements GrantRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function code(string $id): ?AuthorizationCode
    {
        return $this->entityManager->find(AuthorizationCode::class, $id);
    }

    #[Override]
    public function saveCode(AuthorizationCode $code): void
    {
        $this->entityManager->persist($code);
        $this->entityManager->flush();
    }

    #[Override]
    public function consumeCode(AuthorizationCode $code, DateTimeImmutable $now): bool
    {
        $count = $this->entityManager->createQuery(\sprintf('UPDATE %s c SET c.usedAt = :now WHERE c.id = :id AND c.usedAt IS NULL', AuthorizationCode::class))
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $code->id())
            ->execute();

        if (1 !== $count) {
            return false;
        }

        $code->use($now);

        return true;
    }

    #[Override]
    public function token(string $id): ?IssuedToken
    {
        return $this->entityManager->find(IssuedToken::class, $id);
    }

    #[Override]
    public function family(string $family): array
    {
        return $this->entityManager->getRepository(IssuedToken::class)->findBy(['family' => $family], ['issuedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function activeFor(string $accountId, ?string $clientId = null): array
    {
        return $this->entityManager->getRepository(IssuedToken::class)->findBy(
            ['accountId' => $accountId, 'revokedAt' => null] + (null === $clientId ? [] : ['clientId' => $clientId]),
            ['issuedAt' => 'ASC', 'id' => 'ASC'],
        );
    }

    #[Override]
    public function forSession(string $sessionId): array
    {
        return $this->entityManager->getRepository(IssuedToken::class)->findBy(['sessionId' => $sessionId, 'revokedAt' => null], ['issuedAt' => 'ASC', 'id' => 'ASC']);
    }

    #[Override]
    public function saveToken(IssuedToken ...$tokens): void
    {
        foreach ($tokens as $token) {
            $this->entityManager->persist($token);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function rotateToken(IssuedToken $token, DateTimeImmutable $now): bool
    {
        $count = $this->entityManager->createQuery(\sprintf('UPDATE %s t SET t.rotatedAt = :now WHERE t.id = :id AND t.rotatedAt IS NULL AND t.revokedAt IS NULL', IssuedToken::class))
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $token->id())
            ->execute();

        if (1 !== $count) {
            return false;
        }

        $token->rotate($now);

        return true;
    }

    #[Override]
    public function useAssertion(UsedAssertion $assertion): bool
    {
        try {
            $this->entityManager->getConnection()->insert(UsedAssertion::TABLE, ['id' => $assertion->id(), 'expires_at' => $assertion->expiresAt()], ['expires_at' => Types::DATETIME_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $removed = 0;

        foreach ([AuthorizationCode::class, IssuedToken::class, UsedAssertion::class] as $class) {
            $count = $this->entityManager->createQuery(\sprintf('DELETE FROM %s g WHERE g.expiresAt < :before', $class))
                ->setParameter('before', $before)
                ->execute();
            \assert(\is_int($count));
            $removed += $count;
        }

        return $removed;
    }
}
