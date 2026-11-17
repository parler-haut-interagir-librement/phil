<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\ContentBundle\Domain\Content\SingleRoot;
use Phil\ContentBundle\Domain\Contract\SingleRootRepository;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;

/**
 * `SingleRootRepository` on the table of `SingleRoot`, by statements of the entity manager's connection rather than
 * through its unit of work: a claim is written at once, inside the running transaction, and a refusal of the primary
 * key becomes an `InvalidContent` without closing the entity manager. The transaction of the caller rolls back.
 */
final readonly class DoctrineSingleRootRepository implements SingleRootRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function claim(ContentReference $root): void
    {
        try {
            $this->entityManager->getConnection()->insert(SingleRoot::TABLE, ['resource_key' => $root->resourceKey, 'resource_id' => $root->resourceId]);
        } catch (UniqueConstraintViolationException $uniqueConstraintViolationException) {
            throw InvalidContent::singleKindTaken($root->resourceKey, $uniqueConstraintViolationException);
        }
    }

    #[Override]
    public function release(ContentReference $root): void
    {
        $this->entityManager->getConnection()->delete(SingleRoot::TABLE, ['resource_key' => $root->resourceKey, 'resource_id' => $root->resourceId]);
    }
}
