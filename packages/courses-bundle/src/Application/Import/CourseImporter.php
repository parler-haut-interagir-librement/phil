<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Import;

use Phil\CoursesBundle\Application\Contract\ContentStorage;
use Phil\CoursesBundle\Application\Contract\XapiPackageReader;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Domain\Event\CourseImported;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\ElearningPackages\Cmi5\Cmi5Course;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Contract\PackageOpener;
use Phil\ElearningPackages\Exception\PackageRefused as UnreadablePackage;
use Phil\ElearningPackages\Package;
use Phil\ElearningPackages\PackageFormat;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackageReader;
use Phil\ElearningPackages\Xapi\XapiCourse;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Imports a course package to migrate a course: a ZIP archive holding `cmi5.xml` or `imsmanifest.xml` at its root —
 * or `tincan.xml` alone, read by the configured `XapiPackageReader` —, or a cmi5 course structure alone whose units
 * have absolute URLs. The package is read by phil/elearning-packages within
 * the limits, its manifest turned into a plan before anything is written, then the course, the blocks of a cmi5
 * course structure, its modules and activities are created and every file of the package is stored under the
 * course's directory. An import that fails half-way
 * removes what it wrote.
 */
final readonly class CourseImporter
{
    private PackageReader $reader;

    public function __construct(
        private PackageOpener $opener,
        private Courses $courses,
        private ContentStorage $storage,
        private PackageLimits $limits = new PackageLimits(),
        private ?EventDispatcherInterface $events = null,
        ?string $language = null,
        private ?XapiPackageReader $xapi = null,
    ) {
        $this->reader = new PackageReader($limits, $language, $opener);
    }

    /**
     * @param ?string $title the course's title, instead of the package's
     *
     * @throws PackageRefused when the package is unsafe, malformed or describes no course
     */
    public function import(string $path, ?string $title = null): ImportReport
    {
        if (str_ends_with(strtolower($path), '.xml')) {
            return $this->structure($this->file($path), $title);
        }

        try {
            $archive = $this->opener->open($path, $this->limits);
        } catch (UnreadablePackage $unreadablePackage) {
            throw PackageRefused::from($unreadablePackage);
        }

        try {
            return $this->create($this->planOf($archive), $title, $archive);
        } catch (UnreadablePackage $unreadablePackage) {
            throw PackageRefused::from($unreadablePackage);
        } finally {
            $archive->close();
        }
    }

    /**
     * Imports a cmi5 course structure alone, its units on absolute URLs.
     *
     * @throws PackageRefused when the structure is unsafe or malformed, or names files
     */
    public function structure(string $xml, ?string $title = null): ImportReport
    {
        try {
            $package = $this->reader->readStructure($xml);
        } catch (UnreadablePackage $unreadablePackage) {
            throw PackageRefused::from($unreadablePackage);
        }

        return $this->create($this->plan($package, false), $title, null);
    }

    /**
     * The plan of an archive: an xAPI package, described by `tincan.xml` alone, through the reader of xAPI packages; the
     * others through phil/elearning-packages.
     */
    private function planOf(PackageArchive $archive): ImportPlan
    {
        if (!$archive->has(XapiPackageReader::MANIFEST) || $archive->has(PackageFormat::Cmi5->manifest()) || $archive->has(PackageFormat::Scorm2004->manifest())) {
            return $this->plan($this->reader->read($archive), true);
        }

        if (!$this->xapi instanceof XapiPackageReader) {
            throw PackageRefused::because('The package is an xAPI package, described by tincan.xml: it is imported once a reader of xAPI packages is configured in "phil_courses.import.xapi_reader".');
        }

        return new XapiPlanner()->plan($this->xapi->read($archive));
    }

    private function plan(Package $package, bool $packaged): ImportPlan
    {
        if ($package->manifest instanceof XapiCourse) {
            throw PackageRefused::because('An xAPI package holds no course the import can create: only SCORM and cmi5 packages are imported.');
        }

        return $package->manifest instanceof Cmi5Course
            ? new Cmi5Planner()->plan($package->manifest, $packaged, $package->warnings)
            : new ScormPlanner()->plan($package->manifest, $package->warnings);
    }

    private function file(string $path): string
    {
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size || $size > $this->limits->manifestBytes) {
            throw PackageRefused::because(\sprintf('The course structure is not a readable file of %d bytes at most.', $this->limits->manifestBytes));
        }

        return (string) file_get_contents($path);
    }

    private function create(ImportPlan $plan, ?string $title, ?PackageArchive $archive): ImportReport
    {
        // The readers bound what the course keeps: a description to 4 096 characters, an identifier to 255 bytes.
        $course = $this->courses->create(ImportPlan::title($title ?? $plan->title, $plan->source), $plan->description, false, $plan->format, $plan->source);

        try {
            $files = $archive instanceof PackageArchive ? $this->store($course, $archive) : 0;
            $blocks = [];

            foreach ($plan->blocks as $planned) {
                $blocks[] = $this->courses->addBlock($course->id, $planned->title, $planned->source, $planned->position, null === $planned->parent ? null : $blocks[$planned->parent])->id;
            }

            foreach ($plan->modules as $planned) {
                $module = $this->courses->addModule($course->id, $planned->title);

                foreach ($planned->activities as $activity) {
                    $this->courses->addActivity($module->id, $activity->title, $activity->type, $activity->settings, $activity->requirement, $activity->required, null, null, $activity->source, null === $activity->block ? null : $blocks[$activity->block]);
                }
            }
        } catch (Throwable $throwable) {
            $this->courses->remove($course->id);
            $this->storage->removeDirectory($course->id->value);

            throw $throwable instanceof InvalidCourse ? PackageRefused::because($throwable->getMessage()) : $throwable;
        }

        $this->events?->dispatch(new CourseImported($course->id->value, $plan->format->value));

        return new ImportReport($course->id, $plan->format, \count($plan->modules), $plan->activities(), $files, $plan->warnings, \count($plan->blocks));
    }

    private function store(Course $course, PackageArchive $archive): int
    {
        $files = $archive->files();

        foreach ($files as $file) {
            $this->storage->write($course->id->value.'/'.$file, $archive->read($file));
        }

        return \count($files);
    }
}
