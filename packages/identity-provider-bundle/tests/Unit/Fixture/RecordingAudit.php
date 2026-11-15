<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Fixture;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;

/**
 * An audit trail that keeps what it is told, for the tests.
 */
final class RecordingAudit implements AuditRecorder
{
    /** @var list<array{string, ?string, array<string, bool|int|string|null>}> */
    public array $records = [];

    #[Override]
    public function record(string $action, ?string $accountId, array $fields = []): void
    {
        $this->records[] = [$action, $accountId, $fields];
    }

    /**
     * The last record.
     *
     * @return array{string, ?string, array<string, bool|int|string|null>}
     */
    public function last(): array
    {
        $last = end($this->records);

        return false === $last ? ['', null, []] : $last;
    }

    public function lastAction(): string
    {
        return $this->last()[0];
    }

    /**
     * @return list<string>
     */
    public function actions(): array
    {
        return array_map(static fn (array $record): string => $record[0], $this->records);
    }
}
