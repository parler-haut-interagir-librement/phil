<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\BlockId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Release;

/**
 * An activity of a module: a page, a link, a SCORM content or a cmi5 assignable unit — its type names the adapter
 * that launches it —, what satisfies it, whether the course needs it, how many tries a learner has, when it opens,
 * and the settings its type reads. An imported activity keeps the identifier it had in its package as its source; a
 * cmi5 unit nested in a block names the innermost block that holds it.
 *
 * @phpstan-type Settings array<string, bool|float|int|string|null>
 */
final class Activity
{
    public const int SETTINGS_LENGTH = 65_536;

    public const int MAX_ATTEMPTS = 100;

    public private(set) string $title;

    public private(set) Release $release;

    /** @var Settings */
    public private(set) array $settings;

    /**
     * @param int<0, max>             $position
     * @param array<array-key, mixed> $settings
     * @param ?int                    $maxAttempts how many failed tries a learner has before they ask for a second chance; null
     *                                             for no limit
     *
     * @throws InvalidCourse when a value is not one an activity holds
     */
    public function __construct(
        public readonly ActivityId $id,
        public readonly CourseId $course,
        public readonly ModuleId $module,
        string $title,
        public readonly string $type,
        public readonly int $position,
        array $settings = [],
        public private(set) CompletionRequirement $requirement = CompletionRequirement::Completed,
        public private(set) bool $required = true,
        public private(set) ?int $maxAttempts = null,
        ?Release $release = null,
        public readonly ?string $source = null,
        public readonly ?BlockId $block = null,
    ) {
        $this->title = Identifiers::title($title);
        $this->release = $release ?? Release::immediately();
        $this->settings = $this->checked($settings);
        Identifiers::check('An activity type', $type, 64);

        $this->checkTries($maxAttempts);

        if (null !== $source && ('' === $source || \strlen($source) > Course::SOURCE_LENGTH)) {
            throw InvalidCourse::because(\sprintf('The source of an activity is 1 to %d bytes.', Course::SOURCE_LENGTH));
        }
    }

    public function setting(string $name): bool|float|int|string|null
    {
        return $this->settings[$name] ?? null;
    }

    public function stringSetting(string $name): ?string
    {
        $value = $this->settings[$name] ?? null;

        return \is_string($value) ? $value : null;
    }

    public function schedule(Release $release): void
    {
        $this->release = $release;
    }

    /**
     * @throws InvalidCourse when the tries are not a number an activity allows
     */
    public function require(CompletionRequirement $requirement, bool $required, ?int $maxAttempts): void
    {
        $this->checkTries($maxAttempts);
        $this->requirement = $requirement;
        $this->required = $required;
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * @throws InvalidCourse when the tries are not a number an activity allows
     */
    private function checkTries(?int $maxAttempts): void
    {
        if (null !== $maxAttempts && ($maxAttempts < 1 || $maxAttempts > self::MAX_ATTEMPTS)) {
            throw InvalidCourse::because(\sprintf('An activity allows 1 to %d tries, or no limit.', self::MAX_ATTEMPTS));
        }
    }

    /**
     * @param array<array-key, mixed> $settings
     *
     * @return Settings
     */
    private function checked(array $settings): array
    {
        $checked = [];

        foreach ($settings as $name => $value) {
            if (!\is_string($name) || '' === $name || null !== $value && !\is_scalar($value)) {
                throw InvalidCourse::because('The settings of an activity are named scalar values.');
            }

            $checked[$name] = $value;
        }

        if (\strlen((string) json_encode($checked, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION)) > self::SETTINGS_LENGTH) {
            throw InvalidCourse::because(\sprintf('The settings of an activity are %d bytes at most, in JSON.', self::SETTINGS_LENGTH));
        }

        return $checked;
    }
}
