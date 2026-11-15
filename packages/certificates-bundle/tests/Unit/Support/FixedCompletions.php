<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use Override;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;

/**
 * A source of completions that answers what it is given, by the course's key then the holder, and notes what it is
 * asked.
 */
final class FixedCompletions implements CourseCompletions
{
    /** @var list<array{string, string}> */
    public array $asked = [];

    /**
     * @param array<string, array<string, string>> $completions
     */
    public function __construct(
        public array $completions = [],
    ) {
    }

    #[Override]
    public function completion(string $course, string $holder): ?string
    {
        $this->asked[] = [$course, $holder];

        return $this->completions[$course][$holder] ?? null;
    }
}
