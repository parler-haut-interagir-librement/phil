<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\ActivityType;

use Override;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;

/**
 * A page of text the activity holds in its `body` setting, shown on the activity page as plain text, and marked done
 * by the learner.
 */
final readonly class PageActivity implements ActivityType
{
    public const string NAME = 'page';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function settings(array $settings): array
    {
        $body = $settings['body'] ?? '';

        if (!\is_string($body)) {
            throw InvalidCourse::because('The "body" of a page is text.');
        }

        return ['body' => $body];
    }

    #[Override]
    public function completesManually(): bool
    {
        return true;
    }

    #[Override]
    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        return Launch::page();
    }
}
