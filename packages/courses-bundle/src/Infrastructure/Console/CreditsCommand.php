<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Console;

use Phil\CoursesBundle\Application\Training\Transcripts;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `phil:courses:credits`: sets the credits a course grants on its completion — an amount in continuing education units
 * or hours —, or none with `0`.
 */
#[AsCommand(name: 'phil:courses:credits', description: 'Sets the credits a course grants on its completion.')]
final readonly class CreditsCommand
{
    public function __construct(
        private Transcripts $transcripts,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The identifier of the course.')]
        string $course,
        #[Argument('The amount, two decimals at most, such as 1.5; 0 for none.')]
        string $amount,
        #[Option('The unit: ceu or hours.')]
        string $unit = 'hours',
    ): int {
        $kind = CreditUnit::tryFrom($unit);

        if (!$kind instanceof CreditUnit) {
            $io->error('The unit is ceu or hours.');

            return Command::FAILURE;
        }

        try {
            $credits = '0' === $amount ? null : Credits::of($amount, $kind);
            $this->transcripts->credit(CourseId::of($course), $credits);
        } catch (CoursesException $coursesException) {
            $io->error($coursesException->getMessage());

            return Command::FAILURE;
        }

        $io->success($credits instanceof Credits ? \sprintf('The course grants %s %s.', $credits->amount(), $credits->unit->value) : 'The course grants no credits.');

        return Command::SUCCESS;
    }
}
