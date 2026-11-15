<?php

declare(strict_types=1);

namespace Phil\TimedText\Internal;

use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Issue;
use Phil\TimedText\Limits;

/**
 * The problems of one reading: thrown at once when the reading is strict, listed otherwise. Built for one reading
 * only. Not part of the public API.
 *
 * @internal
 */
final class IssueLog
{
    /**
     * @var list<Issue>
     */
    private array $issues = [];

    public function __construct(
        private readonly bool $strict,
        private readonly Limits $limits,
    ) {
    }

    /**
     * @throws InvalidTimedText when the reading is strict
     * @throws LimitExceeded    when the problems outnumber the limits
     */
    public function report(?int $line, string $message): void
    {
        if ($this->strict) {
            throw InvalidTimedText::at($line, $message);
        }

        if (\count($this->issues) >= $this->limits->maxIssues) {
            throw LimitExceeded::count('problems', $this->limits->maxIssues);
        }

        $this->issues[] = new Issue($line, $message);
    }

    /**
     * @return list<Issue>
     */
    public function issues(): array
    {
        return $this->issues;
    }
}
