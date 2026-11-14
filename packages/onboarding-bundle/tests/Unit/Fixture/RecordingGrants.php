<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Phil\OnboardingBundle\Application\Answer;
use Phil\OnboardingBundle\Application\Contract\AnswerGrants;
use RuntimeException;

/**
 * The grants of an application, recorded; one that fails, when asked.
 */
final class RecordingGrants implements AnswerGrants
{
    /** @var list<Answer> */
    public array $answers = [];

    public bool $failing = false;

    #[Override]
    public function grant(Answer $answer): void
    {
        if ($this->failing) {
            throw new RuntimeException('The directory is down.');
        }

        $this->answers[] = $answer;
    }
}
