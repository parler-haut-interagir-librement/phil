<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use Override;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:course first-aid "First aid" "Score 70 % or more and practise CPR." --min-score=70 --module=cpr --validity=24 --credits=12 --ends=2030-01-01.
 *
 * Defines a course, as `Courses::define()` does, or revises it with `--revise`, and prints the address of its page in
 * the register, which identifies the achievement of its certificates.
 */
#[AsCommand(name: 'phil:certificates:course', description: 'Defines a course and its pass criteria, or revises it')]
final class CourseCommand extends Command
{
    public function __construct(
        private readonly Courses $courses,
        private readonly CertificateUrls $urls,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('slug', InputArgument::REQUIRED, 'The slug of the course, in its URLs')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the course')
            ->addArgument('narrative', InputArgument::REQUIRED, 'What a learner does to pass, as the credential states it')
            ->addOption('description', null, InputOption::VALUE_REQUIRED, 'What the course teaches', '')
            ->addOption('min-score', null, InputOption::VALUE_REQUIRED, 'The minimum score of the assessment, from 1 to 100')
            ->addOption('min-minutes', null, InputOption::VALUE_REQUIRED, 'The minimum of minutes of training')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A module to complete; repeat it for each')
            ->addOption('validity', null, InputOption::VALUE_REQUIRED, 'How many months a certificate holds; for ever when left out')
            ->addOption('image', null, InputOption::VALUE_REQUIRED, 'The absolute URL of the image of its badge')
            ->addOption('credits', null, InputOption::VALUE_REQUIRED, 'The credits of continuing education a recertification asks for, such as 12 or 7.5')
            ->addOption('ends', null, InputOption::VALUE_REQUIRED, 'The day the course ends, YYYY-MM-DD: no certificate is awarded from then on, nor valid past it')
            ->addOption('revise', null, InputOption::VALUE_NONE, 'Revise the course of this slug instead of defining a new one');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        /** @var string $slug */
        $slug = $input->getArgument('slug');
        /** @var string $name */
        $name = $input->getArgument('name');
        /** @var string $narrative */
        $narrative = $input->getArgument('narrative');
        /** @var string $description */
        $description = $input->getOption('description');
        /** @var list<string> $modules */
        $modules = $input->getOption('module');
        /** @var string|null $image */
        $image = $input->getOption('image');
        $revise = true === $input->getOption('revise');
        /** @var string|null $credits */
        $credits = $input->getOption('credits');
        /** @var string|null $ends */
        $ends = $input->getOption('ends');

        try {
            $criteria = new PassCriteria($this->number($input, 'min-score', 'minimum score'), $this->number($input, 'min-minutes', 'minimum of minutes'), $modules);
            $validity = $this->number($input, 'validity', 'validity in months');
            $credits = null === $credits ? null : Credits::fromString($credits);
            $ends = null === $ends ? null : Numbers::date('end', $ends);
            $course = $revise
                ? $this->courses->revise($slug, $name, $description, $narrative, $criteria, $validity, $image, $credits, $ends)
                : $this->courses->define($slug, $name, $description, $narrative, $criteria, $validity, $image, $credits, $ends);
        } catch (InvalidValue $invalidValue) {
            $errors->writeln($invalidValue->getMessage());

            return Command::INVALID;
        } catch (CertificateRefused $certificateRefused) {
            $errors->writeln($certificateRefused->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('%s the course "%s", %s.', $revise ? 'Revised' : 'Defined', $course->slug, $course->name));
        $output->writeln($this->urls->course($course->slug));

        return Command::SUCCESS;
    }

    private function number(InputInterface $input, string $option, string $label): ?int
    {
        /** @var string|null $value */
        $value = $input->getOption($option);

        return null === $value ? null : Numbers::integer($label, $value);
    }
}
