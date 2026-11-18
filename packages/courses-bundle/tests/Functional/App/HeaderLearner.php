<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional\App;

use Override;
use Phil\CoursesBundle\Application\Contract\CurrentLearner;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The learner the `X-User` header names, as a firewall would.
 */
final readonly class HeaderLearner implements CurrentLearner
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): ?LearnerId
    {
        $user = $this->requests->getCurrentRequest()?->headers->get('X-User');

        return null === $user ? null : LearnerId::of($user);
    }
}
