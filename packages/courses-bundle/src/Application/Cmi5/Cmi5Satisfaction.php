<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Event\BlockSatisfied;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Issues the `satisfied` statements of the platform, as cmi5 asks: for each block whose required units are all
 * satisfied — the units of its nested blocks included, a waived unit counted satisfied, a unit that is not required
 * (`moveOn` of `NotApplicable`) left out —, the innermost blocks first, then for the course once all its required units
 * are. Each statement is issued once for an enrollment: the satisfaction is noted before the statement is kept, and
 * forgotten again when the store refuses it. The object of a block's statement is the identifier its course structure
 * gives it; that of the course, the identifier of its course structure, or the platform's IRI of the course.
 *
 * @internal built by Cmi5Statements, which it issues the statements through
 */
final readonly class Cmi5Satisfaction
{
    public function __construct(
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private Cmi5Statements $statements,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * @param Cmi5Context $context the context of the session whose statement, or waiver, satisfied a unit
     */
    public function satisfy(Cmi5Context $context, Enrollment $enrollment): void
    {
        $course = $this->courses->find($enrollment->course);
        $activities = $this->courses->activities($enrollment->course);

        if (!$course instanceof Course || [] === $activities) {
            return;
        }

        $progresses = $this->enrollments->progresses($enrollment->id);
        $claimed = array_flip($this->enrollments->satisfactions($enrollment->id));
        $blocks = $this->courses->blocks($course->id);
        $parents = [];

        foreach ($blocks as $block) {
            $parents[$block->id->value] = $block->parent?->value;
        }

        $holding = [];
        $unmet = [];
        $courseMet = true;

        foreach ($activities as $activity) {
            $met = !$activity->required || (($progresses[$activity->id->value] ?? null)?->isSatisfied() ?? false);
            $courseMet = $courseMet && $met;

            // Up the tree from the innermost block, each block once even if a store gave a cycle.
            for ($block = $activity->block?->value, $seen = []; null !== $block && !isset($seen[$block]); $block = $parents[$block] ?? null) {
                $seen[$block] = true;
                $holding[$block] = true;
                $unmet[$block] = ($unmet[$block] ?? false) || !$met;
            }
        }

        $courseIri = CourseOrigin::Cmi5 === $course->origin && null !== $course->source ? $course->source : $context->courseIri;
        $now = $this->clock->now();

        foreach ($this->innermostFirst($blocks) as $block) {
            $id = $block->id->value;

            if (isset($holding[$id]) && !$unmet[$id] && !isset($claimed[$id])) {
                $statement = PlatformStatements::blockSatisfied($context, $block->source, $courseIri, $now);

                if ($this->issue($enrollment, $id, $statement, $context)) {
                    $this->events?->dispatch(new BlockSatisfied($enrollment->id->value, $id, $this->id($statement)));
                }
            }
        }

        if ($courseMet && !isset($claimed[$course->id->value])) {
            $this->issue($enrollment, $course->id->value, PlatformStatements::courseSatisfied($context, $now, $courseIri), $context);
        }
    }

    /**
     * @param list<Block> $blocks each parent before the blocks it holds
     *
     * @return list<Block> the deepest blocks first, in their order at each depth
     */
    private function innermostFirst(array $blocks): array
    {
        $depths = [];

        foreach ($blocks as $block) {
            $parent = $block->parent?->value;
            $depths[$block->id->value] = null === $parent ? 0 : ($depths[$parent] ?? 0) + 1;
        }

        usort($blocks, static fn (Block $a, Block $b): int => $depths[$b->id->value] <=> $depths[$a->id->value]);

        return $blocks;
    }

    /**
     * @param array<string, mixed> $statement
     */
    private function issue(Enrollment $enrollment, string $subject, array $statement, Cmi5Context $context): bool
    {
        if (!$this->enrollments->claimSatisfaction($enrollment->id, $subject, $this->id($statement), $this->clock->now())) {
            return false;
        }

        try {
            $this->statements->issue($statement, $enrollment->registration, $context->session);
        } catch (Throwable $throwable) {
            $this->enrollments->releaseSatisfaction($enrollment->id, $subject);

            throw $throwable;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $statement
     */
    private function id(array $statement): string
    {
        return \is_string($statement['id'] ?? null) ? $statement['id'] : '';
    }
}
