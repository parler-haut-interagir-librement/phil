<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\AuthorizationCode;
use Phil\IdentityProviderBundle\Domain\Model\Oidc\IssuedToken;

/**
 * The codes in `phil_identity_code` and the tokens in `phil_identity_token`; each save is flushed, the purge is two
 * statements.
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
    public function purge(DateTimeImmutable $before): int
    {
        $removed = 0;

        foreach ([AuthorizationCode::class, IssuedToken::class] as $class) {
            $count = $this->entityManager->createQuery(\sprintf('DELETE FROM %s g WHERE g.expiresAt < :before', $class))
                ->setParameter('before', $before)
                ->execute();
            \assert(\is_int($count));
            $removed += $count;
        }

        return $removed;
    }
}
