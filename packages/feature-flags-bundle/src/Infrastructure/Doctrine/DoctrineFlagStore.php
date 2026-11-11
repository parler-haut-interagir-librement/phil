<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\FeatureFlagsBundle\Application\Toggle\WritableFlagStore;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Psr\Clock\ClockInterface;

/**
 * The active states in `phil_feature_flags_state`, read in one query on the first flag of a request and kept for
 * the rest of it; `reset()` ends the request. The writes are single conditional statements, so a toggle that
 * lost a race to another process writes nothing.
 */
final class DoctrineFlagStore implements WritableFlagStore
{
    /** @var array<string, bool>|null */
    private ?array $states = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Override]
    public function isActive(FlagName $flag): ?bool
    {
        return ($this->states ??= $this->load())[$flag->value] ?? null;
    }

    #[Override]
    public function compareAndSet(FlagName $flag, bool $expected, bool $active): bool
    {
        $this->states = null;

        $updated = $this->connection->executeStatement(
            'UPDATE '.FlagTables::STATES.' SET active = ?, updated_at = ? WHERE name = ? AND active = ?',
            [$active, $this->clock->now(), $flag->value, $expected],
            [ParameterType::BOOLEAN, Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::BOOLEAN],
        );

        return 1 === $updated;
    }

    #[Override]
    public function insert(FlagName $flag, bool $active): bool
    {
        $this->states = null;

        try {
            $this->connection->insert(
                FlagTables::STATES,
                ['name' => $flag->value, 'active' => $active, 'updated_at' => $this->clock->now()],
                ['active' => ParameterType::BOOLEAN, 'updated_at' => Types::DATETIME_IMMUTABLE],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function reset(): void
    {
        $this->states = null;
    }

    /**
     * @return array<string, bool>
     */
    private function load(): array
    {
        $boolean = Type::getType(Types::BOOLEAN);
        $platform = $this->connection->getDatabasePlatform();
        $states = [];

        /** @var array<string, mixed> $rows the active state of each flag, by name */
        $rows = $this->connection->fetchAllKeyValue('SELECT name, active FROM '.FlagTables::STATES);

        foreach ($rows as $name => $active) {
            $states[$name] = true === $boolean->convertToPHPValue($active, $platform);
        }

        return $states;
    }
}
