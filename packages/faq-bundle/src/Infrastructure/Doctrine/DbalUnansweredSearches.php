<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Override;
use Phil\FaqBundle\Domain\Contract\UnansweredSearchRepository;
use Phil\FaqBundle\Domain\Model\UnansweredSearch;

/**
 * The searches without answer in their table: one `UPDATE` adds to a total, an `INSERT` starts one, and when two start
 * the same total at once the second one adds to it instead.
 */
final readonly class DbalUnansweredSearches implements UnansweredSearchRepository
{
    private const string DAY = 'Y-m-d';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function record(string $locale, string $query, DateTimeImmutable $day): void
    {
        $id = UnansweredSearch::idOf($locale, $query);
        $date = $day->format(self::DAY);

        if ($this->increment($id, $date) > 0) {
            return;
        }

        try {
            $this->connection->insert(UnansweredSearch::TABLE, ['id' => $id, 'locale' => $locale, 'terms' => $query, 'searches' => 1, 'last_searched_on' => $date]);
        } catch (UniqueConstraintViolationException) {
            $this->increment($id, $date);
        }
    }

    #[Override]
    public function mostFrequent(int $limit, ?string $locale = null): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('locale', 'terms', 'searches', 'last_searched_on')
            ->from(UnansweredSearch::TABLE)
            ->orderBy('searches', 'DESC')->addOrderBy('last_searched_on', 'DESC')->addOrderBy('id')
            ->setMaxResults($limit);

        if (null !== $locale) {
            $query->where('locale = :locale')->setParameter('locale', $locale);
        }

        $searches = [];

        foreach ($query->executeQuery()->iterateAssociative() as $row) {
            $day = DateTimeImmutable::createFromFormat('!'.self::DAY, substr($this->text($row['last_searched_on']), 0, 10));
            $searches[] = UnansweredSearch::restored($this->text($row['locale']), $this->text($row['terms']), is_numeric($row['searches']) ? (int) $row['searches'] : 0, false === $day ? new DateTimeImmutable('@0') : $day);
        }

        return $searches;
    }

    #[Override]
    public function forget(string $locale, string $query): void
    {
        $this->connection->delete(UnansweredSearch::TABLE, ['id' => UnansweredSearch::idOf($locale, $query)]);
    }

    private function increment(string $id, string $date): int
    {
        return (int) $this->connection->executeStatement(
            \sprintf('UPDATE %s SET searches = searches + 1, last_searched_on = ? WHERE id = ?', UnansweredSearch::TABLE),
            [$date, $id],
        );
    }

    private function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
