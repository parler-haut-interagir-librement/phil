<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi;

/**
 * How serious a finding is: an `Error` breaks the contract, a `Warning` weakens it.
 */
enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';

    /**
     * Whether a finding of this severity reaches `$threshold`: an error reaches both severities, a warning only a
     * warning threshold.
     */
    public function reaches(self $threshold): bool
    {
        return self::Error === $this || self::Warning === $threshold;
    }
}
