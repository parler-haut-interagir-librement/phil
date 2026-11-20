<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Xapi;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;

/**
 * What the statements of one launch of an xAPI activity must agree with: the learner as an xAPI agent, named by an
 * account of the site — never an e-mail address —, the activity's identifier in its package, the registration of the
 * enrollment, the session and the mastery score.
 */
final readonly class XapiContext
{
    public function __construct(
        public string $homePage,
        public string $learner,
        public string $activityIri,
        public string $registration,
        public string $session,
        public ?float $masteryScore = null,
    ) {
    }

    public static function of(LaunchSession $session, Enrollment $enrollment, Activity $activity, string $homePage): self
    {
        $mastery = $activity->setting('mastery_score');

        return new self(
            $homePage,
            $enrollment->learner->value,
            $activity->stringSetting('activity_id') ?? '',
            $enrollment->registration,
            $session->id->value,
            \is_float($mastery) || \is_int($mastery) ? $mastery : null,
        );
    }

    /**
     * @return array{objectType: 'Agent', account: array{homePage: string, name: string}}
     */
    public function actor(): array
    {
        return ['objectType' => 'Agent', 'account' => ['homePage' => $this->homePage, 'name' => $this->learner]];
    }
}
