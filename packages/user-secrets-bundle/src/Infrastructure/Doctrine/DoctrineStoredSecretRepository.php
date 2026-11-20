<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\Doctrine;

use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Override;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Domain\Encryption\SealedFormat;
use Phil\UserSecretsBundle\Domain\Model\StoredSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\Owner;
use Phil\UserSecretsBundle\Domain\ValueObject\SealedSecret;
use Phil\UserSecretsBundle\Domain\ValueObject\SecretName;
use SortDirection;

/**
 * The secrets in the table `phil_user_secret`, through the entity manager; each save and removal is flushed.
 */
final readonly class DoctrineStoredSecretRepository implements StoredSecretRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function get(Owner $owner, SecretName $name): ?StoredSecret
    {
        return $this->entityManager->find(StoredSecret::class, ['owner' => $owner->value, 'name' => $name->value]);
    }

    #[Override]
    public function ofOwner(Owner $owner): array
    {
        return $this->entityManager->getRepository(StoredSecret::class)->findBy(['owner' => $owner->value], ['name' => 'ASC']);
    }

    #[Override]
    public function toReseal(string $keyId, SealedFormat $format, int $limit, ?StoredSecret $after = null): array
    {
        $criteria = Criteria::create()
            ->where(Criteria::expr()->isNull('revokedAt'))
            ->andWhere(Criteria::expr()->orX(
                Criteria::expr()->neq('keyId', $keyId),
                Criteria::expr()->isNull('format'),
                Criteria::expr()->neq('format', $format->value),
            ))
            ->orderBy(['owner' => SortDirection::Ascending, 'name' => SortDirection::Ascending])
            ->setMaxResults($limit);

        if ($after instanceof StoredSecret) {
            $criteria->andWhere(Criteria::expr()->orX(
                Criteria::expr()->gt('owner', $after->owner()->value),
                Criteria::expr()->andX(
                    Criteria::expr()->eq('owner', $after->owner()->value),
                    Criteria::expr()->gt('name', $after->name()->value),
                ),
            ));
        }

        return [...$this->entityManager->getRepository(StoredSecret::class)->matching($criteria)];
    }

    #[Override]
    public function countToReseal(string $keyId, SealedFormat $format): array
    {
        /** @var list<array{keyId: string, secrets: int|string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s.keyId AS keyId', 'COUNT(s.name) AS secrets')
            ->from(StoredSecret::class, 's')
            ->where('s.revokedAt IS NULL')
            ->andWhere('s.keyId <> :keyId OR s.format IS NULL OR s.format <> :format')
            ->groupBy('s.keyId')
            ->orderBy('s.keyId')
            ->setParameter('keyId', $keyId)
            ->setParameter('format', $format->value)
            ->getQuery()
            ->getArrayResult();

        $counts = [];

        foreach ($rows as $row) {
            $counts[$row['keyId']] = (int) $row['secrets'];
        }

        return $counts;
    }

    #[Override]
    public function fresh(Owner $owner, SecretName $name): ?StoredSecret
    {
        $secret = $this->get($owner, $name);

        if (!$secret instanceof StoredSecret) {
            return null;
        }

        try {
            $this->entityManager->refresh($secret);
        } catch (EntityNotFoundException) {
            $this->entityManager->detach($secret);

            return null;
        }

        return $secret;
    }

    /**
     * One `UPDATE` whose condition is the previous sealed value, then the entity read again: saved, it is in step with
     * its row; not saved, it holds what the other process wrote.
     */
    #[Override]
    public function replace(StoredSecret $secret, SealedSecret $previous): bool
    {
        $sealed = $secret->sealed();
        $updated = $this->entityManager->createQueryBuilder()
            ->update(StoredSecret::class, 's')
            ->set('s.service', ':service')
            ->set('s.kind', ':kind')
            ->set('s.keyId', ':keyId')
            ->set('s.ciphertext', ':ciphertext')
            ->set('s.format', ':format')
            ->set('s.expiresAt', ':expiresAt')
            ->set('s.updatedAt', ':updatedAt')
            ->where('s.owner = :owner')
            ->andWhere('s.name = :name')
            ->andWhere('s.ciphertext = :previous')
            ->andWhere('s.revokedAt IS NULL')
            ->setParameter('service', $secret->service()->value)
            ->setParameter('kind', $secret->kind()->value)
            ->setParameter('keyId', $sealed->keyId)
            ->setParameter('ciphertext', $sealed->ciphertext)
            ->setParameter('format', $sealed->format()?->value)
            ->setParameter('expiresAt', $secret->expiresAt(), Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('updatedAt', $secret->updatedAt(), Types::DATETIME_UTC_IMMUTABLE)
            ->setParameter('owner', $secret->owner()->value)
            ->setParameter('name', $secret->name()->value)
            ->setParameter('previous', $previous->ciphertext)
            ->getQuery()
            ->execute();

        if ($this->entityManager->contains($secret)) {
            try {
                $this->entityManager->refresh($secret);
            } catch (EntityNotFoundException) {
                $this->entityManager->detach($secret);
            }
        }

        return 1 === $updated;
    }

    #[Override]
    public function save(StoredSecret ...$secrets): void
    {
        foreach ($secrets as $secret) {
            $this->entityManager->persist($secret);
        }

        $this->entityManager->flush();
    }

    #[Override]
    public function remove(StoredSecret ...$secrets): void
    {
        foreach ($secrets as $secret) {
            $this->entityManager->remove($secret);
        }

        $this->entityManager->flush();
    }
}
