<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\ValueObject;

use Phil\SitesConsoleBundle\Domain\Document\Fields;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;

/**
 * One health check of a site, as its agent reports it.
 */
final readonly class CheckState
{
    public const int NAME_MAX_LENGTH = 100;

    public const int SUMMARY_MAX_LENGTH = 200;

    public string $summary;

    public function __construct(
        public string $name,
        public HealthState $state,
        string $summary = '',
    ) {
        $this->summary = mb_substr($summary, 0, self::SUMMARY_MAX_LENGTH);
    }

    public static function fromArray(mixed $value): self
    {
        $object = Fields::object($value, 'check');
        $state = HealthState::tryFrom(Fields::string($object, 'status', 10));

        if (null === $state) {
            throw MalformedDocument::because('"status" is up, degraded, down or unknown.');
        }

        return new self(Fields::string($object, 'name', self::NAME_MAX_LENGTH), $state, Fields::optionalString($object, 'summary', self::SUMMARY_MAX_LENGTH));
    }

    /**
     * @return array{name: string, status: string, summary: string}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'status' => $this->state->value, 'summary' => $this->summary];
    }
}
