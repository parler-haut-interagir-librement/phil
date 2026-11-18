<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;

/**
 * The fields of a row of the trail as sorted JSON.
 */
final readonly class JsonFields implements CanonicalSerializer
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
