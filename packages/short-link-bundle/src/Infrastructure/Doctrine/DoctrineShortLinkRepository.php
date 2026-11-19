<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Override;
use Phil\ShortLinkBundle\Domain\Contract\ShortLinkRepository;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use SortDirection;

/**
 * The short links with Doctrine. A visit is counted by one `UPDATE` whose conditions are the link's terms at that
 * moment: the database counts the visits of racing requests one after the other, and the last allowed visit once.
 * The update goes straight to the table, so a link already loaded keeps the count it was read with.
 *
 * A code is found only in its own case, even on a database that compares text without case, as MySQL does by
 * default; there, a code that differs only by its case is taken, since the unique index refuses it.
 *
 * The search of the administration is a `LIKE` on the code, the label and the target, its wildcards escaped.
 */
final readonly class DoctrineShortLinkRepository implements ShortLinkRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Override]
    public function find(int $id): ?ShortLink
    {
        return $this->entityManager->find(ShortLink::class, $id);
    }

    #[Override]
    public function findByCode(string $code): ?ShortLink
    {
        $link = $this->entityManager->getRepository(ShortLink::class)->findOneBy(['code' => $code]);

        // A database that compares text without case finds the code in another case: it is not this one.
        return $link instanceof ShortLink && $link->code() === $code ? $link : null;
    }

    #[Override]
    public function isTaken(string $code): bool
    {
        return 0 < $this->entityManager->getRepository(ShortLink::class)->count(['code' => $code]);
    }

    #[Override]
    public function save(ShortLink $link): void
    {
        $this->entityManager->persist($link);
        $this->entityManager->flush();
    }

    #[Override]
    public function remove(int $id): bool
    {
        $link = $this->find($id);

        if (!$link instanceof ShortLink) {
            return false;
        }

        $this->entityManager->remove($link);
        $this->entityManager->flush();

        return true;
    }

    #[Override]
    public function recordVisit(int $id, DateTimeImmutable $now, VisitorClass $visitor = VisitorClass::Human): bool
    {
        $counted = $this->entityManager->getConnection()->executeStatement(
            'UPDATE '.ShortLink::TABLE.' SET '.($visitor->isHuman() ? 'visits = visits + 1, last_visit_at = :now' : 'robot_visits = robot_visits + 1')
            .' WHERE id = :id AND enabled = :enabled'
            .' AND (valid_from IS NULL OR valid_from <= :now)'
            .' AND (valid_until IS NULL OR valid_until > :now)'
            .' AND (max_visits IS NULL OR visits < max_visits)',
            ['id' => $id, 'now' => $now, 'enabled' => true],
            ['id' => ParameterType::INTEGER, 'now' => Types::DATETIME_UTC_IMMUTABLE, 'enabled' => ParameterType::BOOLEAN],
        );

        return 1 === $counted;
    }

    #[Override]
    public function page(string $search, int $offset, int $limit): array
    {
        /** @var list<ShortLink> */
        return $this->searched($search)
            ->orderBy('l.createdAt', SortDirection::Descending)
            ->addOrderBy('l.id', SortDirection::Descending)
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }

    #[Override]
    public function count(string $search): int
    {
        $count = $this->searched($search)->select('COUNT(l.id)')->getQuery()->getSingleScalarResult();

        return is_numeric($count) ? (int) $count : 0;
    }

    private function searched(string $search): QueryBuilder
    {
        $builder = $this->entityManager->createQueryBuilder()->select('l')->from(ShortLink::class, 'l');

        if ('' !== $search) {
            $builder->where("l.code LIKE :search ESCAPE '!' OR l.label LIKE :search ESCAPE '!' OR l.target LIKE :search ESCAPE '!'")
                ->setParameter('search', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%');
        }

        return $builder;
    }
}
