<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * An xAPI package as `tincan.xml` describes it: the activity a platform launches — the first launchable activity of
 * type `course`, else the first launchable one —, its title in the language asked for, every activity the package
 * declares in order, and the issues set aside while reading it.
 */
final readonly class XapiCourse
{
    /**
     * @param list<XapiActivity> $activities the activities, each IRI once, the course among them
     * @param list<XapiWarning>  $warnings
     */
    public function __construct(
        public XapiActivity $course,
        public string $title,
        public array $activities,
        public array $warnings = [],
    ) {
    }

    /**
     * The IRI of the course activity.
     */
    public function id(): string
    {
        return $this->course->id;
    }

    /**
     * The activity of that IRI, or null.
     */
    public function activity(string $id): ?XapiActivity
    {
        return array_find($this->activities, static fn (XapiActivity $activity): bool => $activity->id === $id);
    }

    /**
     * @return list<XapiActivity> the activities a platform may launch, in order
     */
    public function launchable(): array
    {
        return array_values(array_filter($this->activities, static fn (XapiActivity $activity): bool => $activity->isLaunchable()));
    }

    /**
     * @return list<XapiActivity> the questions the package declares, in order
     */
    public function interactions(): array
    {
        return array_values(array_filter($this->activities, static fn (XapiActivity $activity): bool => XapiActivity::INTERACTION === $activity->type));
    }
}
