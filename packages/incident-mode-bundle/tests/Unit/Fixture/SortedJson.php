<?php

declare(strict_types=1);

namespace Phil\IncidentModeBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;

/**
 * The fields of a row of the trail as JSON, sorted by key.
 */
final readonly class SortedJson implements CanonicalSerializer
{
    #[Override]
    public function version(): string
    {
        return 'test-1';
    }

    #[Override]
    public function serialize(array $fields): string
    {
        ksort($fields);

        return (string) json_encode($fields, \JSON_UNESCAPED_SLASHES);
    }
}
