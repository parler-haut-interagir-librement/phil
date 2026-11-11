<?php

declare(strict_types=1);

namespace Phil\ReferencesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Override;
use Phil\ReferencesBundle\Domain\Repository\UsageRepository;
use Phil\ReferencesBundle\Domain\ValueObject\Usage;
use Phil\Resource\ResourceReference;

/**
 * The usages in their table. A source's usages are replaced in one transaction that writes only what changed: the
 * usages it no longer has are deleted, the new ones inserted, and a source whose links did not change costs one read.
 * The same usage found twice is kept once.
 */
final readonly class DbalUsageRepository implements UsageRepository
{
    /**
     * How many targets of a kind one read of usages names, well below the bound parameters a database takes.
     */
    private const int CHUNK = 500;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function replace(ResourceReference $source, array $usages): void
    {
        $wanted = [];

        foreach ($usages as $usage) {
            $key = UsageTable::keyOf($source->key, $source->id, $usage->field, $usage->target->key, $usage->target->id);
            $wanted[$key] = [
                'usage_key' => $key,
                'source_kind' => $source->key,
                'source_id' => $source->id,
                'field' => $usage->field,
                'target_kind' => $usage->target->key,
                'target_id' => $usage->target->id,
            ];
        }

        $this->connection->transactional(function () use ($source, $wanted): void {
            $stored = $this->connection->fetchFirstColumn(\sprintf('SELECT usage_key FROM %s WHERE source_kind = ? AND source_id = ?', UsageTable::NAME), [$source->key, $source->id]);

            foreach ($stored as $key) {
                if (\is_string($key) && isset($wanted[$key])) {
                    unset($wanted[$key]);
                } else {
                    $this->connection->delete(UsageTable::NAME, ['usage_key' => $key]);
                }
            }

            foreach ($wanted as $row) {
                $this->connection->insert(UsageTable::NAME, $row);
            }
        });
    }

    #[Override]
    public function forget(ResourceReference $source): void
    {
        $this->connection->delete(UsageTable::NAME, ['source_kind' => $source->key, 'source_id' => $source->id]);
    }

    #[Override]
    public function usagesOf(ResourceReference $target): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf('SELECT source_kind, source_id, field FROM %s WHERE target_kind = ? AND target_id = ? ORDER BY source_kind, source_id, field', UsageTable::NAME),
            [$target->key, $target->id],
        );

        return array_map(static fn (array $row): Usage => Usage::of(ResourceReference::of(self::text($row['source_kind']), self::text($row['source_id'])), self::text($row['field']), $target), $rows);
    }

    #[Override]
    public function usagesOfAll(array $targets): array
    {
        $ids = [];

        foreach ($targets as $target) {
            $ids[$target->key][] = $target->id;
        }

        $found = [];

        foreach ($ids as $kind => $ofKind) {
            foreach (array_chunk($ofKind, self::CHUNK) as $chunk) {
                $rows = $this->connection->fetchAllAssociative(
                    \sprintf('SELECT target_id, source_kind, source_id, field FROM %s WHERE target_kind = ? AND target_id IN (?) ORDER BY source_kind, source_id, field', UsageTable::NAME),
                    [$kind, $chunk],
                    [ParameterType::STRING, ArrayParameterType::STRING],
                );

                foreach ($rows as $row) {
                    $found[$kind][self::text($row['target_id'])][] = [self::text($row['source_kind']), self::text($row['source_id']), self::text($row['field'])];
                }
            }
        }

        $usages = [];

        foreach ($targets as $target) {
            foreach ($found[$target->key][$target->id] ?? [] as [$sourceKind, $sourceId, $field]) {
                $usages[] = Usage::of(ResourceReference::of($sourceKind, $sourceId), $field, $target);
            }
        }

        return $usages;
    }

    #[Override]
    public function targets(): iterable
    {
        foreach ($this->connection->iterateAssociative(\sprintf('SELECT DISTINCT target_kind, target_id FROM %s ORDER BY target_kind, target_id', UsageTable::NAME)) as $row) {
            yield ResourceReference::of(self::text($row['target_kind']), self::text($row['target_id']));
        }
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
