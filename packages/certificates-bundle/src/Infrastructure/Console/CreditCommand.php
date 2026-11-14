<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use Override;
use Phil\CertificatesBundle\Application\ContinuingEducation;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:credit member-42 "Workshop on triage" 3.5 [--completed=2026-11-12 --course=first-aid --source=workshop-17].
 *
 * Records an activity of continuing education for a member, as `ContinuingEducation::record()` does, and prints its
 * identifier; recording the same source again prints the activity already recorded. `--remove=<id>` removes one.
 */
#[AsCommand(name: 'phil:certificates:credit', description: 'Records the credits of an activity of continuing education, or removes one')]
final class CreditCommand extends Command
{
    public function __construct(
        private readonly ContinuingEducation $education,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('member', InputArgument::OPTIONAL, 'The stable identifier of the member')
            ->addArgument('title', InputArgument::OPTIONAL, 'The title of the activity')
            ->addArgument('credits', InputArgument::OPTIONAL, 'The credits it earns, such as 3 or 1.5')
            ->addOption('completed', null, InputOption::VALUE_REQUIRED, 'The day it was completed, YYYY-MM-DD; now when left out')
            ->addOption('course', null, InputOption::VALUE_REQUIRED, 'The slug of the course it counts toward; every course when left out')
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Where it comes from, unique for the member')
            ->addOption('remove', null, InputOption::VALUE_REQUIRED, 'The identifier of an activity to remove instead');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        /** @var string|null $remove */
        $remove = $input->getOption('remove');
        /** @var string|null $member */
        $member = $input->getArgument('member');
        /** @var string|null $title */
        $title = $input->getArgument('title');
        /** @var string|null $credits */
        $credits = $input->getArgument('credits');
        /** @var string|null $completed */
        $completed = $input->getOption('completed');
        /** @var string|null $course */
        $course = $input->getOption('course');
        /** @var string|null $source */
        $source = $input->getOption('source');

        try {
            if (null !== $remove) {
                $activity = $this->education->remove($remove);
                $output->writeln(\sprintf('Removed the activity %s, "%s", of %s.', $activity->id, $activity->title, $activity->member));

                return Command::SUCCESS;
            }

            if (\in_array(null, [$member, $title, $credits], true)) {
                throw InvalidValue::because('Give the member, the title and the credits of the activity, or --remove=<id>.');
            }

            $activity = $this->education->record($member, $title, Credits::fromString($credits), null === $completed ? null : Numbers::date('day completed', $completed), $course, $source);
        } catch (InvalidValue $invalidValue) {
            $errors->writeln($invalidValue->getMessage());

            return Command::INVALID;
        } catch (CertificateRefused $certificateRefused) {
            $errors->writeln($certificateRefused->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('Recorded %s credits for %s: %s.', $activity->credits->formatted(), $activity->member, $activity->id));

        return Command::SUCCESS;
    }
}
