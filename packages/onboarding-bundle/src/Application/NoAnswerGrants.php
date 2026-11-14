<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use Override;
use Phil\OnboardingBundle\Application\Contract\AnswerGrants;

/**
 * Grants nothing: for questions whose choices ask for no role and no subscription. The container is not built when a
 * choice asks for one and the application names no `AnswerGrants`.
 */
final readonly class NoAnswerGrants implements AnswerGrants
{
    #[Override]
    public function grant(Answer $answer): void
    {
    }
}
