<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\IdentityClientBundle\Application\Contract\RefreshOutcomes;
use Phil\IdentityClientBundle\Application\Refresh\RefreshOutcome;
use RuntimeException;
use SensitiveParameter;

final class FailingRefreshOutcomes implements RefreshOutcomes
{
    #[Override]
    public function find(#[SensitiveParameter] string $refreshToken): ?RefreshOutcome
    {
        throw new RuntimeException('The store is down.');
    }

    #[Override]
    public function publish(#[SensitiveParameter] string $refreshToken, RefreshOutcome $outcome): void
    {
    }
}
