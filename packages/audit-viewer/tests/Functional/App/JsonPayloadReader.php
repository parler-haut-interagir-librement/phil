<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Functional\App;

use Override;
use Phil\AuditBundle\Application\Contract\PayloadReader;
use Phil\AuditBundle\Domain\Chain\AuditRow;

/**
 * Reads back the payloads of `JsonSerializer`, and no other version.
 */
final readonly class JsonPayloadReader implements PayloadReader
{
    #[Override]
    public function fields(AuditRow $row): ?array
    {
        if ('json-1' !== $row->version) {
            return null;
        }

        /** @var array<string, scalar|null> */
        return json_decode($row->payload, true, flags: \JSON_THROW_ON_ERROR);
    }
}
