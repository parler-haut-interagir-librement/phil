<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\ActivityType;

use Override;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;

/**
 * A page elsewhere, at the absolute address of its `url` setting, opened in a window of its own and marked done by the
 * learner. An imported SCORM item whose resource is an absolute URL becomes one.
 */
final readonly class LinkActivity implements ActivityType
{
    public const string NAME = 'link';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function settings(array $settings): array
    {
        $url = $settings['url'] ?? null;

        if (!\is_string($url)) {
            throw InvalidCourse::because('A link names its "url".');
        }

        try {
            return ['url' => Url::fromString($url)->toString()];
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidCourse::because(\sprintf('The "url" of a link is not an address: %s', $invalidUrl->getMessage()));
        }
    }

    #[Override]
    public function completesManually(): bool
    {
        return true;
    }

    #[Override]
    public function launch(Activity $activity, Enrollment $enrollment): Launch
    {
        $url = $activity->stringSetting('url');

        if (null === $url) {
            throw ActivityUnavailable::because(\sprintf('The link "%s" names no address.', $activity->id->value));
        }

        return Launch::newWindow($url);
    }
}
