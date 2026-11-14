<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Functional\App;

use Override;
use Phil\OnboardingBundle\Application\Answer;
use Phil\OnboardingBundle\Application\Contract\AnswerGrants;

/**
 * The grants of the application, recorded rather than written.
 */
final class RecordedGrants implements AnswerGrants
{
    /** @var list<Answer> */
    public array $answers = [];

    #[Override]
    public function grant(Answer $answer): void
    {
        $this->answers[] = $answer;
    }
}
