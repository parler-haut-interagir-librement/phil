<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\DiagnosableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\UnreadableFlag;

/**
 * A store some of whose flags read, by name and active state, and some do not, by name and reason.
 */
final readonly class PartlyUnreadableProvider implements DiagnosableFlagProvider
{
    /**
     * @param array<string, bool>   $readable
     * @param array<string, string> $unreadable
     */
    public function __construct(
        private array $readable = [],
        private array $unreadable = [],
    ) {
    }

    #[Override]
    public function find(FlagName $name): ?FlagDefinition
    {
        if (isset($this->unreadable[$name->value])) {
            throw InvalidValue::because($this->unreadable[$name->value]);
        }

        return isset($this->readable[$name->value]) ? new FlagDefinition($name, $this->readable[$name->value]) : null;
    }

    #[Override]
    public function all(): array
    {
        return array_map(static fn (string $name, bool $active): FlagDefinition => new FlagDefinition(FlagName::of($name), $active), array_keys($this->readable), $this->readable);
    }

    #[Override]
    public function unreadable(): array
    {
        return array_map(static fn (string $name, string $reason): UnreadableFlag => new UnreadableFlag($name, $reason), array_keys($this->unreadable), $this->unreadable);
    }
}
