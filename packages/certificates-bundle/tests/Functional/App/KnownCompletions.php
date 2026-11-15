<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Functional\App;

use Override;
use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;

/**
 * The application's source of completions: the completions it knows, by the course's key then the member.
 */
final class KnownCompletions implements CourseCompletions
{
    /** @var array<string, array<string, string>> */
    public array $completions = ['first-aid-2027' => ['member-42' => 'enrolment-7']];

    #[Override]
    public function completion(string $course, string $holder): ?string
    {
        return $this->completions[$course][$holder] ?? null;
    }
}
