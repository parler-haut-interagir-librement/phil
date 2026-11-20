<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Import;

use Override;
use Phil\CoursesBundle\Application\Contract\XapiPackageReader;
use Phil\CoursesBundle\Application\Import\XapiLaunchable;
use Phil\CoursesBundle\Application\Import\XapiPackage;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Exception\PackageRefused as UnreadablePackage;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\Xapi\XapiActivity;
use Phil\ElearningPackages\Xapi\XapiCourseReader;
use Phil\ElearningPackages\Xapi\XapiWarning;

/**
 * The reader of xAPI packages of the bundle, on the `XapiCourseReader` of phil/elearning-packages: the `tincan.xml` of
 * the archive read within the limits of the import, its course activity giving the IRI, the title and the description
 * in the language of the import, each launchable activity becoming an activity in the order of the package, and the
 * notes of the reading kept as the warnings of the import. A `tincan.xml` gives no mastery score: the activities are
 * satisfied once completed. What the library refuses is refused in its words.
 */
final readonly class ElearningXapiPackageReader implements XapiPackageReader
{
    public function __construct(
        private PackageLimits $limits = new PackageLimits(),
        private ?string $language = null,
    ) {
    }

    #[Override]
    public function read(PackageArchive $archive): XapiPackage
    {
        try {
            $course = new XapiCourseReader($this->limits, $this->language)->read($archive->read(self::MANIFEST));
        } catch (UnreadablePackage $unreadablePackage) {
            throw PackageRefused::from($unreadablePackage);
        }

        return new XapiPackage(
            $course->id(),
            $course->title,
            $course->course->description($this->language),
            array_map(fn (XapiActivity $activity): XapiLaunchable => new XapiLaunchable($activity->id, $activity->name($this->language), (string) $activity->launch), $course->launchable()),
            array_map(static fn (XapiWarning $warning): string => $warning->message, $course->warnings),
        );
    }
}
