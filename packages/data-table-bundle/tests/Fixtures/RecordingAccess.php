<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Fixtures;

use Override;
use Phil\DataTableBundle\Application\Contract\TableAccess;

/**
 * Grants every attribute but those refused, and remembers what it was asked.
 */
final class RecordingAccess implements TableAccess
{
    /** @var list<string> */
    public array $refused = [];

    /** @var list<array{string, array<string, mixed>|object|null}> */
    public array $asked = [];

    #[Override]
    public function isGranted(string $attribute, array|object|null $subject = null): bool
    {
        $this->asked[] = [$attribute, $subject];

        return !\in_array($attribute, $this->refused, true);
    }
}
