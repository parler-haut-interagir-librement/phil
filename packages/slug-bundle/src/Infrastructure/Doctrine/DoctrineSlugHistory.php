<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use Phil\SlugBundle\Application\Contract\SlugHistory;
use Phil\SlugBundle\Domain\SlugHistoryKeys;
use Psr\Clock\ClockInterface;

/**
 * The slug history in `phil_slug_history`, on a Doctrine DBAL connection.
 *
 * A change is written in one transaction of three statements: the new slug stops being a former slug of the scope,
 * every former slug of the subject is pointed to the new slug, and the old slug is written as a former slug of the
 * subject. So a lookup never follows a chain, and the history holds one row per former slug, whatever the number of
 * changes. It keeps nothing between two calls: it is safe in a long-running worker. A stored slug that no longer reads
 * as one (a row edited by hand) is not answered.
 */
final readonly class DoctrineSlugHistory implements SlugHistory
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function record(string $scope, string $subject, Slug $former, Slug $current): void
    {
        $scope = SlugHistoryKeys::scope($scope);
        $subject = SlugHistoryKeys::subject($subject);

        if ($former->equals($current)) {
            return;
        }

        $now = $this->clock->now();

        $this->connection->transactional(static function (Connection $connection) use ($scope, $subject, $former, $current, $now): void {
            $connection->delete(SlugHistoryTable::NAME, ['scope' => $scope, 'former_slug' => $current->value]);
            $connection->update(SlugHistoryTable::NAME, ['current_slug' => $current->value], ['scope' => $scope, 'subject' => $subject]);
            $connection->delete(SlugHistoryTable::NAME, ['scope' => $scope, 'former_slug' => $former->value]);
            $connection->insert(
                SlugHistoryTable::NAME,
                ['scope' => $scope, 'former_slug' => $former->value, 'subject' => $subject, 'current_slug' => $current->value, 'recorded_at' => $now],
                ['recorded_at' => Types::DATETIME_IMMUTABLE],
            );
        });
    }

    #[Override]
    public function currentSlug(string $scope, Slug $former): ?Slug
    {
        $current = $this->connection->fetchOne(
            'SELECT current_slug FROM '.SlugHistoryTable::NAME.' WHERE scope = ? AND former_slug = ?',
            [SlugHistoryKeys::scope($scope), $former->value],
        );

        return \is_string($current) ? $this->slug($current) : null;
    }

    #[Override]
    public function formerSlugs(string $scope): iterable
    {
        $rows = $this->connection->iterateKeyValue(
            'SELECT former_slug, current_slug FROM '.SlugHistoryTable::NAME.' WHERE scope = ? ORDER BY former_slug',
            [SlugHistoryKeys::scope($scope)],
        );

        foreach ($rows as $former => $current) {
            $current = \is_string($current) ? $this->slug($current) : null;

            if (\is_string($former) && $current instanceof Slug && $this->slug($former) instanceof Slug) {
                yield $former => $current;
            }
        }
    }

    #[Override]
    public function forget(string $scope, string $subject): void
    {
        $this->connection->delete(SlugHistoryTable::NAME, ['scope' => SlugHistoryKeys::scope($scope), 'subject' => SlugHistoryKeys::subject($subject)]);
    }

    private function slug(string $value): ?Slug
    {
        try {
            return Slug::fromString($value);
        } catch (InvalidSlug) {
            return null;
        }
    }
}
