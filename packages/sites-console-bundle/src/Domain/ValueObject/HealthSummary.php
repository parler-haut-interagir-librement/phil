<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;

/**
 * The health of a site: its overall state and its checks.
 */
final readonly class HealthSummary
{
    public const int MAX_CHECKS = 100;

    /**
     * @param list<CheckState> $checks
     */
    public function __construct(
        public HealthState $state,
        public array $checks = [],
    ) {
    }

    public static function unknown(): self
    {
        return new self(HealthState::Unknown);
    }

    public static function fromArray(mixed $value): self
    {
        if (null === $value) {
            return self::unknown();
        }

        $object = Fields::object($value, 'health');
        $state = HealthState::tryFrom(Fields::string($object, 'status', 10));

        if (null === $state) {
            throw MalformedDocument::because('"status" is up, degraded, down or unknown.');
        }

        $checks = array_map(CheckState::fromArray(...), Fields::list($object['checks'] ?? [], 'checks', self::MAX_CHECKS));

        return new self($state, $checks);
    }

    /**
     * @return array{status: string, checks: list<array{name: string, status: string, summary: string}>}
     */
    public function toArray(): array
    {
        return ['status' => $this->state->value, 'checks' => array_map(static fn (CheckState $check): array => $check->toArray(), $this->checks)];
    }
}
