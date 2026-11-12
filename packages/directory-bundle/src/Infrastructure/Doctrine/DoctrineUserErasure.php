<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\DirectoryBundle\Application\UserErasure;
use Phil\DirectoryBundle\Domain\Model\Listing;
use Phil\DirectoryBundle\Domain\Model\ListingDimension;

/**
 * `UserErasure` on the Doctrine ORM, in one transaction, through the entities so that those already loaded stay
 * true: the versions whose `edited_by` names the user, and the listings, read one at a time — the editors are a JSON
 * list, which no portable query searches —, each forgotten, then flushed.
 */
final readonly class DoctrineUserErasure implements UserErasure
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function erase(string $user): int
    {
        return $this->entityManager->wrapInTransaction(function () use ($user): int {
            $changed = 0;
            $versions = $this->entityManager->createQueryBuilder()
                ->select('d')
                ->from(ListingDimension::class, 'd')
                ->where('d.editedBy = :user')
                ->setParameter('user', $user)
                ->getQuery();

            foreach ($versions->toIterable() as $version) {
                if ($version instanceof ListingDimension) {
                    $version->setEditedBy(null);
                    ++$changed;
                }
            }

            $query = $this->entityManager->createQueryBuilder()->select('l')->from(Listing::class, 'l')->getQuery();

            foreach ($query->toIterable() as $listing) {
                if ($listing instanceof Listing && $listing->forget($user)) {
                    ++$changed;
                }
            }

            $this->entityManager->flush();

            return $changed;
        });
    }
}
