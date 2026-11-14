<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use Override;
use Phil\AuditBundle\Domain\Contract\CanonicalSerializer;

/**
 * A canonical serialization for the tests: the fields sorted by name, as JSON.
 */
final readonly class JsonSerializer implements CanonicalSerializer
{
    #[Override]
    public function version(): string
    {
        return 'json-1';
    }

    #[Override]
    public function serialize(array $fields): string
    {
        ksort($fields);

        return json_encode($fields, \JSON_THROW_ON_ERROR);
    }
}
