<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use Override;
use Phil\CertificatesBundle\Application\ContinuingEducation;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Period;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:credits member-42 [--from=2026-01-01 --until=2027-01-01 --course=first-aid] [--format=json]
 *     bin/console phil:certificates:credits --certificate=7KQ2-M9XA-04TZ.
 *
 * The credits of continuing education a member gathered over a period — the twelve months before today when none is
 * given —, with its activities; or, for a certificate, those its holder gathered since it was awarded, against those
 * its recertification asks for.
 */
#[AsCommand(name: 'phil:certificates:credits', description: 'Shows the credits of continuing education of a member, or of the holder of a certificate')]
final class CreditsCommand extends Command
{
    public function __construct(
        private readonly ContinuingEducation $education,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('member', InputArgument::OPTIONAL, 'The stable identifier of the member')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'The first day counted, YYYY-MM-DD')
            ->addOption('until', null, InputOption::VALUE_REQUIRED, 'The day after the last one counted, YYYY-MM-DD')
            ->addOption('course', null, InputOption::VALUE_REQUIRED, 'Only the credits that count toward this course')
            ->addOption('certificate', null, InputOption::VALUE_REQUIRED, "The progress of a certificate's holder toward its recertification instead")
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'text or json', 'text');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        try {
            $fields = $this->fields($input);
        } catch (InvalidValue $invalidValue) {
            $errors->writeln($invalidValue->getMessage());

            return Command::INVALID;
        } catch (CertificateRefused $certificateRefused) {
            $errors->writeln($certificateRefused->getMessage());

            return Command::FAILURE;
        }

        if ('json' === $input->getOption('format')) {
            $output->writeln(json_encode($fields, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        foreach ($fields as $field => $value) {
            if (\is_array($value)) {
                foreach ($value as $activity) {
                    $output->writeln(\sprintf('  %s  %6s  %s', $activity['completed_at'], $activity['credits'], $activity['title']));
                }
            } elseif (null !== $value) {
                $output->writeln(\sprintf('%-10s %s', $field, $value));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string|list<array{id: string, title: string, credits: string, completed_at: string, course: ?string}>|null>
     */
    private function fields(InputInterface $input): array
    {
        /** @var string|null $certificate */
        $certificate = $input->getOption('certificate');

        if (null !== $certificate) {
            $progress = $this->education->progress($certificate);

            return ['gathered' => $progress['gathered']->formatted(), 'required' => $progress['required']?->formatted(), 'missing' => $progress['missing']?->formatted()];
        }

        /** @var string|null $member */
        $member = $input->getArgument('member');
        /** @var string|null $from */
        $from = $input->getOption('from');
        /** @var string|null $until */
        $until = $input->getOption('until');
        /** @var string|null $course */
        $course = $input->getOption('course');

        if (null === $member) {
            throw InvalidValue::because('Give the member, or --certificate=<code>.');
        }

        $today = $this->clock->now()->setTime(0, 0);
        $period = new Period(null === $from ? $today->modify('-1 year') : Numbers::date('first day', $from), null === $until ? $today->modify('+1 day') : Numbers::date('day after the last', $until));

        return [
            'member' => $member,
            'from' => $period->from->format('Y-m-d'),
            'until' => $period->until->format('Y-m-d'),
            'total' => $this->education->total($member, $period, $course)->formatted(),
            'activities' => array_map(static fn (LearningActivity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->title,
                'credits' => $activity->credits->formatted(),
                'completed_at' => $activity->completedAt->format('Y-m-d'),
                'course' => $activity->course?->slug,
            ], array_values(array_filter($this->education->activities($member, $period), static fn (LearningActivity $activity): bool => null === $course || !$activity->course instanceof Course || $activity->course->slug === $course))),
        ];
    }
}
