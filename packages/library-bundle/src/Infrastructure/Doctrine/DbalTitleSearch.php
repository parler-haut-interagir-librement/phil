<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\LibraryBundle\Application\Contract\TitleSearch;
use Phil\LibraryBundle\Application\Search\SearchCriteria;
use Phil\LibraryBundle\Application\Search\SearchResult;
use Phil\LibraryBundle\Application\Search\TitleRow;
use Phil\LibraryBundle\Domain\Exception\InvalidLibraryData;
use Phil\LibraryBundle\Domain\Model\Copy;
use Phil\LibraryBundle\Domain\Model\Loan;
use Phil\LibraryBundle\Domain\Model\Title;
use Phil\LibraryBundle\Domain\Status\CopyCondition;
use Phil\LibraryBundle\Domain\Status\LoanStatus;
use Phil\LibraryBundle\Domain\Status\TitleKind;
use Psr\Clock\ClockInterface;

/**
 * The catalogue searched with DBAL, a long list read without the ORM: one query for the page, one for the count. A copy
 * is on the shelf when it is not withdrawn and no loan keeps it now — none active, no reservation whose span holds this
 * moment. Words are matched case-insensitively, `%` and `_` taken literally.
 */
final readonly class DbalTitleSearch implements TitleSearch
{
    /** The furthest page read. */
    public const int MAX_PAGE = 1000;

    /** The most rows a page holds. */
    public const int MAX_PER_PAGE = 100;

    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidLibraryData when the page or its size is out of bounds
     */
    #[Override]
    public function search(SearchCriteria $criteria, int $page, int $perPage): SearchResult
    {
        if ($page < 1 || $page > self::MAX_PAGE || $perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw InvalidLibraryData::because(\sprintf('A search reads pages 1 to %d of 1 to %d titles.', self::MAX_PAGE, self::MAX_PER_PAGE));
        }

        $total = $this->filtered($criteria)->select('COUNT(*)')->executeQuery()->fetchOne();
        $rows = $this->filtered($criteria)
            ->select('t.id', 't.kind', 't.name', 't.creators', 't.year', 't.min_players', 't.max_players', 't.min_minutes', 't.max_minutes', 't.min_age')
            ->addSelect('(SELECT COUNT(*) FROM '.Copy::TABLE.' c WHERE c.title = t.id AND c.state <> :withdrawn) AS copies')
            ->addSelect('(SELECT COUNT(*) FROM '.Copy::TABLE.' c WHERE c.title = t.id AND c.state <> :withdrawn AND '.$this->free().') AS available')
            ->orderBy('t.name')
            ->addOrderBy('t.id')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->executeQuery()
            ->fetchAllAssociative();

        return new SearchResult(array_map($this->row(...), $rows), is_numeric($total) ? (int) $total : 0, $page, $perPage);
    }

    private function filtered(SearchCriteria $criteria): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder()
            ->from(Title::TABLE, 't')
            ->setParameter('withdrawn', CopyCondition::Withdrawn->value)
            ->setParameter('active', LoanStatus::Active->value)
            ->setParameter('reserved', LoanStatus::Reserved->value)
            ->setParameter('now', $this->clock->now(), Types::DATETIME_UTC_IMMUTABLE);

        if (null !== $criteria->text) {
            $words = '%'.strtr(mb_strtolower($criteria->text), ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $match = "LOWER(t.name) LIKE :words ESCAPE '!' OR LOWER(t.creators) LIKE :words ESCAPE '!'";
            $isbn = $criteria->isbn();

            if (null !== $isbn) {
                $match .= ' OR t.isbn = :isbn';
                $query->setParameter('isbn', $isbn);
            }

            $query->andWhere('('.$match.')')->setParameter('words', $words);
        }

        if ($criteria->kind instanceof TitleKind) {
            $query->andWhere('t.kind = :kind')->setParameter('kind', $criteria->kind->value);
        }

        if (null !== $criteria->players) {
            $query->andWhere('(t.min_players IS NULL OR t.min_players <= :players) AND (t.max_players IS NULL OR t.max_players >= :players)')->setParameter('players', $criteria->players, Types::INTEGER);
        }

        if (null !== $criteria->minutes) {
            $query->andWhere('(t.min_minutes IS NULL OR t.min_minutes <= :minutes)')->setParameter('minutes', $criteria->minutes, Types::INTEGER);
        }

        if (null !== $criteria->age) {
            $query->andWhere('(t.min_age IS NULL OR t.min_age <= :age)')->setParameter('age', $criteria->age, Types::INTEGER);
        }

        if (null !== $criteria->language) {
            $query->andWhere('t.language = :language')->setParameter('language', $criteria->language);
        }

        if ($criteria->availableOnly) {
            $query->andWhere('EXISTS (SELECT 1 FROM '.Copy::TABLE.' c WHERE c.title = t.id AND c.state <> :withdrawn AND '.$this->free().')');
        }

        return $query;
    }

    /**
     * The condition of a copy `c` that no loan keeps now.
     */
    private function free(): string
    {
        return 'NOT EXISTS (SELECT 1 FROM '.Loan::TABLE.' l WHERE l.copy = c.id AND (l.status = :active OR (l.status = :reserved AND l.starts_at <= :now AND l.due_at > :now)))';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function row(array $row): TitleRow
    {
        return new TitleRow(
            $this->text($row['id']),
            TitleKind::from($this->text($row['kind'])),
            $this->text($row['name']),
            $this->optionalText($row['creators']),
            $this->number($row['year']),
            $this->number($row['min_players']),
            $this->number($row['max_players']),
            $this->number($row['min_minutes']),
            $this->number($row['max_minutes']),
            $this->number($row['min_age']),
            $this->number($row['copies']) ?? 0,
            $this->number($row['available']) ?? 0,
        );
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? trim($value) : '';
    }

    private function optionalText(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }

    private function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
