<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Edit\WritableFlagDefinitions;
use Phil\FeatureFlagsBundle\Domain\Edit\RevisedDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\DiagnosableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\UnreadableFlag;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\FlagDefinitionFormat;
use Psr\Clock\ClockInterface;

/**
 * The flags created from the admin, in `phil_feature_flags_definition`: a provider of the evaluator, and the
 * store the admin writes to.
 *
 * As a provider, it reads every definition in one query on the first flag of a request and keeps them for the
 * rest of it; `reset()` ends the request. **A row that no longer reads as a definition is unreadable, not
 * absent**: `find()` throws `InvalidValue` with the reason for its flag, so the evaluation is off with the code
 * `PARSE_ERROR` while every other flag still evaluates; `all()` leaves it out and `unreadable()` lists it with
 * its reason.
 */
final class DoctrineFlagDefinitions implements DiagnosableFlagProvider, WritableFlagDefinitions
{
    /** @var array<string, FlagDefinition>|null */
    private ?array $definitions = null;

    /** @var array<array-key, string> the reason each unreadable row gives, by the name it stores */
    private array $unreadable = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Override]
    public function find(FlagName $name): ?FlagDefinition
    {
        $definitions = $this->definitions ??= $this->load();

        if (isset($this->unreadable[$name->value])) {
            throw InvalidValue::because($this->unreadable[$name->value]);
        }

        return $definitions[$name->value] ?? null;
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->definitions ??= $this->load());
    }

    #[Override]
    public function unreadable(): array
    {
        $this->definitions ??= $this->load();

        return array_map(
            // A name of digits only is an integer key of the array.
            static fn (int|string $name, string $reason): UnreadableFlag => new UnreadableFlag((string) $name, $reason),
            array_keys($this->unreadable),
            $this->unreadable,
        );
    }

    /**
     * @throws InvalidValue when the stored row no longer reads as a definition
     */
    #[Override]
    public function findRevised(FlagName $flag): ?RevisedDefinition
    {
        $row = $this->connection->fetchAssociative(
            'SELECT definition, revision FROM '.FlagTables::DEFINITIONS.' WHERE name = ?',
            [$flag->value],
        );

        if (false === $row) {
            return null;
        }

        $revision = $row['revision'] ?? null;

        // A driver may return an integer column as a string of digits.
        if (\is_string($revision) && 1 === preg_match('/^\d+$/D', $revision)) {
            $revision = (int) $revision;
        }

        if (!\is_int($revision)) {
            throw InvalidValue::because(\sprintf('The stored revision of "%s" is not a whole number.', $flag->value));
        }

        return new RevisedDefinition($this->definition($flag, $row['definition'] ?? null), $revision);
    }

    #[Override]
    public function create(FlagDefinition $definition): bool
    {
        $this->definitions = null;

        try {
            $this->connection->insert(
                FlagTables::DEFINITIONS,
                ['name' => $definition->name->value, 'definition' => FlagDefinitionFormat::write($definition), 'revision' => 1, 'updated_at' => $this->clock->now()],
                ['definition' => Types::JSON, 'revision' => ParameterType::INTEGER, 'updated_at' => Types::DATETIME_IMMUTABLE],
            );
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function replace(FlagDefinition $definition, int $revision): bool
    {
        $this->definitions = null;

        $updated = $this->connection->executeStatement(
            'UPDATE '.FlagTables::DEFINITIONS.' SET definition = ?, revision = revision + 1, updated_at = ? WHERE name = ? AND revision = ?',
            [FlagDefinitionFormat::write($definition), $this->clock->now(), $definition->name->value, $revision],
            [Types::JSON, Types::DATETIME_IMMUTABLE, ParameterType::STRING, ParameterType::INTEGER],
        );

        return 1 === $updated;
    }

    public function reset(): void
    {
        $this->definitions = null;
    }

    /**
     * @return array<string, FlagDefinition>
     */
    private function load(): array
    {
        $definitions = [];
        $this->unreadable = [];

        foreach ($this->connection->fetchAllKeyValue('SELECT name, definition FROM '.FlagTables::DEFINITIONS) as $name => $json) {
            try {
                $flag = FlagName::of((string) $name);
                $definitions[$flag->value] = $this->definition($flag, $json);
            } catch (InvalidValue $invalidValue) {
                $this->unreadable[$name] = $invalidValue->getMessage();
            }
        }

        ksort($this->unreadable, \SORT_STRING);

        return $definitions;
    }

    /**
     * @throws InvalidValue
     */
    private function definition(FlagName $flag, mixed $json): FlagDefinition
    {
        if (!\is_string($json)) {
            throw InvalidValue::because(\sprintf('The stored definition of "%s" is not JSON.', $flag->value));
        }

        try {
            $decoded = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidValue::because(\sprintf('The stored definition of "%s" is not JSON: %s', $flag->value, $jsonException->getMessage()));
        }

        return FlagDefinitionFormat::read($flag, $decoded);
    }
}
