<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;

/**
 * What the statements of one launch must agree with: the learner as an xAPI agent, named by an account of the site —
 * never an e-mail address —, the activity's IRI on this platform and its IRI in the package, the registration, the
 * session, the requirement and the mastery score.
 */
final readonly class Cmi5Context
{
    public function __construct(
        public string $homePage,
        public string $learner,
        public string $activityIri,
        public string $publisherId,
        public string $courseIri,
        public string $registration,
        public string $session,
        public CompletionRequirement $moveOn,
        public ?float $masteryScore,
        public ?string $launchParameters = null,
        public ?string $entitlementKey = null,
    ) {
    }

    public static function of(LaunchSession $session, Enrollment $enrollment, Activity $activity, string $homePage): self
    {
        $mastery = $activity->setting('mastery_score');

        return new self(
            $homePage,
            $enrollment->learner->value,
            self::activityIri($homePage, $activity),
            $activity->stringSetting('au_id') ?? self::activityIri($homePage, $activity),
            self::courseIri($homePage, $activity),
            $enrollment->registration,
            $session->id->value,
            $activity->requirement,
            \is_float($mastery) || \is_int($mastery) ? $mastery : null,
            $activity->stringSetting('launch_parameters'),
            $activity->stringSetting('entitlement_key'),
        );
    }

    private static function activityIri(string $homePage, Activity $activity): string
    {
        return rtrim($homePage, '/').'/courses/activities/'.$activity->id->value;
    }

    private static function courseIri(string $homePage, Activity $activity): string
    {
        return rtrim($homePage, '/').'/courses/'.$activity->course->value;
    }

    /**
     * @return array{objectType: 'Agent', account: array{homePage: string, name: string}}
     */
    public function actor(): array
    {
        return ['objectType' => 'Agent', 'account' => ['homePage' => $this->homePage, 'name' => $this->learner]];
    }
}
