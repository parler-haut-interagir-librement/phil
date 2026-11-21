<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Path;

use Phil\CoursesBundle\Application\Completion;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\LearningPathRepository;
use Phil\CoursesBundle\Domain\Event\LearnerEnrolledInPath;
use Phil\CoursesBundle\Domain\Event\PathCompleted;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\LearningPath;
use Phil\CoursesBundle\Domain\Model\PathEnrollment;
use Phil\CoursesBundle\Domain\Model\PathStep;
use Phil\CoursesBundle\Domain\Path\StepState;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;
use Phil\CoursesBundle\Domain\ValueObject\PathEnrollmentId;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The learning paths: an ordered sequence of courses, each step waiting for courses placed before it. A learner
 * enrolls in the path once; they are enrolled in each course whose prerequisites they completed, at once and after
 * each completion, and the path is completed — with `PathCompleted` — once every required step is. A step is completed
 * by its course or by an equivalent course, as `Completions` answers.
 */
final readonly class LearningPaths
{
    public const int MAX_STEPS = 100;

    public function __construct(
        private LearningPathRepository $paths,
        private CourseRepository $courses,
        private Enrollments $enrollments,
        private Completions $completions,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @throws InvalidCourse when the title or the description is not one a path holds
     */
    public function create(string $title, string $description = ''): LearningPath
    {
        $path = new LearningPath(LearningPathId::generate(), $title, $this->clock->now(), $description);
        $this->paths->save($path);

        return $path;
    }

    /**
     * @throws UnknownRecord when the path is not known
     */
    public function get(LearningPathId $id): LearningPath
    {
        return $this->paths->find($id) ?? throw UnknownRecord::of('learning path', $id->value);
    }

    /**
     * @return list<LearningPath> a page of the paths, the latest first
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        return $this->paths->all($limit, $offset);
    }

    /**
     * @throws UnknownRecord when the path is not known
     * @throws InvalidCourse when the title or the description is not one a path holds
     */
    public function rename(LearningPathId $id, string $title, string $description): LearningPath
    {
        $path = $this->get($id);
        $path->rename($title, $description);

        $this->paths->save($path);

        return $path;
    }

    /**
     * Removes the path with its steps and enrollments; the learners stay enrolled in its courses.
     */
    public function remove(LearningPathId $id): void
    {
        $this->paths->remove($id);
    }

    /**
     * @return list<PathStep> the steps of the path, in their order
     */
    public function steps(LearningPathId $path): array
    {
        return $this->paths->steps($path);
    }

    /**
     * Adds the course to the path, after its other steps or before the step of another course.
     *
     * @param list<CourseId> $prerequisites the courses of the path completed before this one opens, placed before it
     * @param ?CourseId      $before        the course whose step this one goes before; null for the end of the path
     *
     * @throws UnknownRecord when the path or the course is not known
     * @throws InvalidCourse when the course is in the path already, a prerequisite is not placed before it, the step
     *                       of `$before` is not in the path, or the path is full
     */
    public function addStep(LearningPathId $path, CourseId $course, bool $required = true, array $prerequisites = [], ?CourseId $before = null): PathStep
    {
        $this->get($path);
        $this->courses->find($course) ?? throw UnknownRecord::of('course', $course->value);
        $steps = $this->paths->steps($path);

        if (null !== $this->index($steps, $course)) {
            throw InvalidCourse::because(\sprintf('The course "%s" is already a step of the path.', $course->value));
        }

        if (\count($steps) >= self::MAX_STEPS) {
            throw InvalidCourse::because(\sprintf('A path holds %d steps at most.', self::MAX_STEPS));
        }

        $at = $before instanceof CourseId ? $this->index($steps, $before) ?? throw InvalidCourse::because(\sprintf('The course "%s" is not a step of the path.', $before->value)) : (\count($steps));
        $earlier = \array_slice($steps, 0, $at);

        foreach ($prerequisites as $prerequisite) {
            if (null === $this->index($earlier, $prerequisite)) {
                throw InvalidCourse::because(\sprintf('The prerequisite "%s" is not a step placed before this one.', $prerequisite->value));
            }
        }

        $step = new PathStep($path, $course, $at, $required, $prerequisites);
        array_splice($steps, $at, 0, [$step]);
        $this->paths->saveSteps($path, $this->renumber($steps));

        return $step;
    }

    /**
     * Takes the course out of the path, and out of the prerequisites of the other steps.
     *
     * @throws UnknownRecord when the path is not known
     */
    public function removeStep(LearningPathId $path, CourseId $course): void
    {
        $this->get($path);
        $steps = array_values(array_filter($this->paths->steps($path), static fn (PathStep $step): bool => !$step->course->equals($course)));
        $this->paths->saveSteps($path, $this->renumber($steps, $course));
    }

    /**
     * Enrolls the learner in the path, or gives back the enrollment they have, then enrolls them in the courses
     * opened to them.
     *
     * @throws UnknownRecord when the path is not known
     */
    public function enroll(LearningPathId $path, LearnerId $learner): PathEnrollment
    {
        $this->get($path);
        $enrollment = $this->paths->enrollmentFor($path, $learner);

        if (!$enrollment instanceof PathEnrollment) {
            $enrollment = new PathEnrollment(PathEnrollmentId::generate(), $path, $learner, $this->clock->now());
            $this->paths->saveEnrollment($enrollment);
            $this->events?->dispatch(new LearnerEnrolledInPath($enrollment->id->value, $path->value, $learner->value));
        }

        $this->advance($enrollment);

        return $enrollment;
    }

    /**
     * @throws UnknownRecord when the enrollment is not known
     */
    public function enrollment(PathEnrollmentId $id): PathEnrollment
    {
        return $this->paths->enrollment($id) ?? throw UnknownRecord::of('path enrollment', $id->value);
    }

    public function of(LearningPathId $path, LearnerId $learner): ?PathEnrollment
    {
        return $this->paths->enrollmentFor($path, $learner);
    }

    /**
     * @return list<PathEnrollment> the learner's enrollments in paths, the latest first
     */
    public function ofLearner(LearnerId $learner): array
    {
        return $this->paths->enrollmentsOf($learner);
    }

    /**
     * Where the learner stands in each step of the path.
     */
    public function progress(PathEnrollment $enrollment): PathProgress
    {
        $statuses = $this->statuses($enrollment);
        $required = array_filter($statuses, static fn (StepStatus $status): bool => $status->step->required);
        $completed = array_filter($required, static fn (StepStatus $status): bool => StepState::Completed === $status->state);
        $whole = $enrollment->isCompleted() ? 100 : 0;
        $percent = [] === $required ? $whole : intdiv(100 * \count($completed), \count($required));

        return new PathProgress($enrollment, $statuses, max(0, min(100, $percent)));
    }

    /**
     * Enrolls the learner in each course of the path opened to them, and completes the path once every required step
     * is completed: says whether this call completed it.
     */
    public function advance(PathEnrollment $enrollment): bool
    {
        $statuses = $this->statuses($enrollment);

        foreach ($statuses as $status) {
            if (StepState::Open === $status->state) {
                $this->enrollments->enroll($status->step->course, $enrollment->learner);
            }
        }

        $done = [] !== $statuses && !array_any($statuses, static fn (StepStatus $status): bool => $status->step->required && StepState::Completed !== $status->state);

        if (!$done || !$enrollment->complete($this->clock->now())) {
            return false;
        }

        $this->paths->saveEnrollment($enrollment);
        $this->events?->dispatch(new PathCompleted($enrollment->id->value, $enrollment->path->value, $enrollment->learner->value));

        return true;
    }

    /**
     * The learner's completion of the path, as the identifier of their enrollment in it, or null when they have not
     * completed it.
     */
    public function completion(LearningPathId $path, LearnerId $learner): ?PathEnrollment
    {
        $enrollment = $this->paths->enrollmentFor($path, $learner);

        return $enrollment instanceof PathEnrollment && $enrollment->isCompleted() ? $enrollment : null;
    }

    /**
     * @return list<StepStatus>
     */
    private function statuses(PathEnrollment $enrollment): array
    {
        $completions = [];
        $statuses = [];

        foreach ($this->paths->steps($enrollment->path) as $step) {
            $completion = $this->completions->completion($step->course, $enrollment->learner);
            $completions[$step->course->value] = $completion instanceof Completion;
            // A prerequisite that is no step of the path any more — its course removed — holds nothing back.
            $open = !array_any($step->prerequisites, static fn (CourseId $prerequisite): bool => !($completions[$prerequisite->value] ?? true));
            $state = match (true) {
                $completion instanceof Completion => StepState::Completed,
                $open => StepState::Open,
                default => StepState::Locked,
            };
            $statuses[] = new StepStatus($step, $state, $completion);
        }

        return $statuses;
    }

    /**
     * @param list<PathStep> $steps
     *
     * @return ?int<0, max> the place of the course's step, or null when it is no step
     */
    private function index(array $steps, CourseId $course): ?int
    {
        return array_find_key($steps, static fn (PathStep $step): bool => $step->course->equals($course));
    }

    /**
     * The steps placed again from 0, without the course removed in their prerequisites.
     *
     * @param list<PathStep> $steps
     *
     * @return list<PathStep>
     */
    private function renumber(array $steps, ?CourseId $removed = null): array
    {
        $placed = [];

        foreach ($steps as $position => $step) {
            $prerequisites = $removed instanceof CourseId ? array_values(array_filter($step->prerequisites, static fn (CourseId $prerequisite): bool => !$prerequisite->equals($removed))) : $step->prerequisites;
            $placed[] = new PathStep($step->path, $step->course, $position, $step->required, $prerequisites);
        }

        return $placed;
    }
}
