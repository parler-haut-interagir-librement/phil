<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Tests\Support;

use Phil\AudienceSegments\Contract\Exclusions;

final readonly class MemoryExclusions implements Exclusions
{
    /** @var list<string> */
    private array $excluded;

    public function __construct(string ...$excluded)
    {
        $this->excluded = array_values($excluded);
    }

    public function excluded(array $members): array
    {
        return array_values(array_intersect($members, $this->excluded));
    }
}
