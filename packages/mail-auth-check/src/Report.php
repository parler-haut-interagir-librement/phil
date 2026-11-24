<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck;

/**
 * Everything the checks found for one sender profile, in the order of the checks. The profile can send when no finding
 * failed.
 */
final readonly class Report
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(
        public SenderProfile $profile,
        public array $findings,
    ) {
    }

    public function canSend(): bool
    {
        return [] === $this->failures();
    }

    /**
     * @return list<Finding>
     */
    public function failures(): array
    {
        return $this->withStatus(Status::Fail);
    }

    /**
     * @return list<Finding>
     */
    public function warnings(): array
    {
        return $this->withStatus(Status::Warning);
    }

    /**
     * @return list<Finding>
     */
    public function forCheck(string $check): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $finding): bool => $finding->check === $check));
    }

    /**
     * @return list<Finding>
     */
    private function withStatus(Status $status): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $finding): bool => $finding->status === $status));
    }
}
