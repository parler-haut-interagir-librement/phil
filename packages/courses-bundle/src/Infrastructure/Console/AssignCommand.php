<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Console;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Application\Training\Assignments;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:courses:assign`: assigns a course to a learner, a group or a unit, due by the end of a day in the site's time
 * zone, renewed after a number of months for a recertification.
 */
#[AsCommand(name: 'phil:courses:assign', description: 'Assigns a course to a learner, a group or a unit, by a due date.')]
final readonly class AssignCommand
{
    public function __construct(
        private Assignments $assignments,
        private DateTimeZone $zone,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifier of the course.')]
        string $course,
        #[Argument('Who the course is assigned to: learner, group or unit.')]
        string $kind,
        #[Argument('The identifier of the learner, the group or the unit.')]
        string $assignee,
        #[Argument("The last day to complete it, YYYY-MM-DD, in the site's time zone.")]
        string $due,
        #[Option('After how many months a completion is renewed.')]
        ?int $renewMonths = null,
        #[Option('The identifier of the person who assigns it, told when it is overdue.')]
        ?string $by = null,
    ): int {
        $type = AssigneeKind::tryFrom($kind);
        $day = 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/D', $due) ? DateTimeImmutable::createFromFormat('!Y-m-d', $due, $this->zone) : false;

        if (!$type instanceof AssigneeKind || false === $day || $day->format('Y-m-d') !== $due) {
            $io->error('The kind is learner, group or unit, and the due date a day written YYYY-MM-DD.');

            return Command::FAILURE;
        }

        try {
            $assignment = $this->assignments->assign(CourseId::of($course), $type, $assignee, $day->modify('+1 day'), $renewMonths, null === $by ? null : LearnerId::of($by));
        } catch (CoursesException $coursesException) {
            $io->error($coursesException->getMessage());

            return Command::FAILURE;
        }

        $io->success(\sprintf('Assignment %s: the course is due before %s UTC.', $assignment->id->value, $assignment->dueAt->format('Y-m-d H:i')));

        return Command::SUCCESS;
    }
}
