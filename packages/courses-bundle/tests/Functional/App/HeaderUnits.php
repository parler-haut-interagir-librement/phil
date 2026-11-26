<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional\App;

use Override;
use Phil\CoursesBundle\Application\Contract\LearnerUnits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The units the `X-Units` header names, separated by commas, as the tree of units of an organization would.
 */
final readonly class HeaderUnits implements LearnerUnits
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function units(LearnerId $learner): array
    {
        $units = $this->requests->getCurrentRequest()?->headers->get('X-Units');

        return null === $units ? [] : explode(',', $units);
    }
}
