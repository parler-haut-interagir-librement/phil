<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Domain\ValueObject;

use Phil\MonitoringBundle\Domain\Status\HealthStatus;

/**
 * What a check found: its status and a short summary for the operator — "3 messages waiting", "not writable". The
 * summary is shown to whoever holds the monitoring token, so a check never puts a secret, a host, a path or a person in
 * it; it is kept to one line of 200 characters.
 */
final readonly class CheckOutcome
{
    public const int SUMMARY_LENGTH = 200;

    public string $summary;

    private function __construct(
        public HealthStatus $status,
        string $summary,
    ) {
        $this->summary = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $summary)), 0, self::SUMMARY_LENGTH);
    }

    public static function up(string $summary = ''): self
    {
        return new self(HealthStatus::Up, $summary);
    }

    public static function degraded(string $summary): self
    {
        return new self(HealthStatus::Degraded, $summary);
    }

    public static function down(string $summary): self
    {
        return new self(HealthStatus::Down, $summary);
    }
}
