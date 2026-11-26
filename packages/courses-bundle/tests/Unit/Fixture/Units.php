<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Fixture;

use Override;
use Phil\CoursesBundle\Application\Contract\LearnerUnits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The units whose catalogues reach each learner, as a test sets them, and how many times they were asked for.
 */
final class Units implements LearnerUnits
{
    /** @var array<string, list<string>> */
    public array $units = [];

    public int $asked = 0;

    #[Override]
    public function units(LearnerId $learner): array
    {
        ++$this->asked;

        return $this->units[$learner->value] ?? [];
    }
}
