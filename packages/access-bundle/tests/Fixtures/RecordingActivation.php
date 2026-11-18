<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Fixtures;

use Override;
use Phil\TenancyBundle\Application\Contract\TenantActivation;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use RuntimeException;

/**
 * Writes down each tenant entered and each leaving, and fails on entering the tenant a test names.
 */
final class RecordingActivation implements TenantActivation
{
    /** @var list<string> */
    public array $calls = [];

    /** The tenant whose entering fails, once a test names it. */
    public ?string $failsOn = null;

    #[Override]
    public function activate(TenantId $tenant): void
    {
        $this->calls[] = 'enter '.$tenant->value;

        if ($tenant->value === $this->failsOn) {
            throw new RuntimeException(\sprintf('The database of <%s> is down.', $tenant->value));
        }
    }

    #[Override]
    public function deactivate(): void
    {
        $this->calls[] = 'leave';
    }
}
