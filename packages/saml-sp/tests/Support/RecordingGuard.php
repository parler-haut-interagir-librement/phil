<?php

declare(strict_types=1);

namespace Phil\SamlSp\Tests\Support;

use DateTimeImmutable;
use Override;
use Phil\SamlSp\Contract\ReplayGuard;

/**
 * Records what the processor asks the guard, and lets everything through.
 */
final class RecordingGuard implements ReplayGuard
{
    /** @var list<array{string, string, string}> */
    public array $uses = [];

    #[Override]
    public function firstUse(string $issuer, string $assertionId, DateTimeImmutable $until): bool
    {
        $this->uses[] = [$issuer, $assertionId, $until->format('Y-m-d\TH:i:s.uP')];

        return true;
    }
}
