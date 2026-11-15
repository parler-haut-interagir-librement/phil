<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use Override;
use Phil\CertificatesBundle\Application\Awarding;
use Phil\CertificatesBundle\Application\Contract\CertificateUrls;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\ValueObject\Attainment;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:award first-aid "Ada Lovelace" ada@example.org --score=82 --minutes=240 --module=cpr --listed --holder=member-42.
 *
 * Awards the certificate of a course, as `Awarding` does, and prints its verification code and the address of its
 * page in the register. The identity is never printed: the credential keeps it as a salted hash only. A course that
 * names a completion source asks it whether the holder completed the course; the identifier of the completion is
 * printed. With `--on-completion`, a completion already awarded prints the certificate it holds instead of failing.
 */
#[AsCommand(name: 'phil:certificates:award', description: 'Awards the certificate of a course to a learner who meets its criteria')]
final class AwardCommand extends Command
{
    public function __construct(
        private readonly Awarding $awarding,
        private readonly CertificateUrls $urls,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('course', InputArgument::REQUIRED, 'The slug of the course')
            ->addArgument('name', InputArgument::REQUIRED, 'The name printed on the certificate')
            ->addArgument('identity', InputArgument::REQUIRED, 'The e-mail address, URL or decentralized identifier of the learner')
            ->addOption('score', null, InputOption::VALUE_REQUIRED, 'The score of the assessment, from 0 to 100')
            ->addOption('minutes', null, InputOption::VALUE_REQUIRED, 'The minutes of training followed', '0')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A module completed; repeat it for each')
            ->addOption('listed', null, InputOption::VALUE_NONE, "Whether the certificate appears on its course's page in the register")
            ->addOption('holder', null, InputOption::VALUE_REQUIRED, 'The stable identifier of the member, by which credits of continuing education are counted and the completion of the course asked')
            ->addOption('on-completion', null, InputOption::VALUE_NONE, 'Award on the completion of a course that names a completion source, or show the certificate already awarded on it');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        /** @var string $course */
        $course = $input->getArgument('course');
        /** @var string $name */
        $name = $input->getArgument('name');
        /** @var string $identity */
        $identity = $input->getArgument('identity');
        /** @var string|null $score */
        $score = $input->getOption('score');
        /** @var string $minutes */
        $minutes = $input->getOption('minutes');
        /** @var list<string> $modules */
        $modules = $input->getOption('module');
        /** @var string|null $holder */
        $holder = $input->getOption('holder');

        $listed = true === $input->getOption('listed');
        $onCompletion = true === $input->getOption('on-completion');

        try {
            $attainment = new Attainment(null === $score ? null : Numbers::integer('score', $score), Numbers::integer('minutes', $minutes), $modules);
            $certificate = $onCompletion
                ? $this->awarding->awardOnCompletion($course, $holder ?? throw InvalidValue::because('An award on completion names the holder, with --holder.'), $name, $identity, $attainment, $listed)
                : $this->awarding->award($course, $name, $identity, $attainment, $listed, $holder);
        } catch (InvalidValue $invalidValue) {
            $errors->writeln($invalidValue->getMessage());

            return Command::INVALID;
        } catch (CertificateRefused $certificateRefused) {
            $errors->writeln($certificateRefused->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf($onCompletion ? 'The certificate %s of "%s" is awarded to %s on that completion.' : 'Awarded the certificate %s of "%s" to %s.', $certificate->code->formatted(), $certificate->course->slug, $certificate->recipientName));
        $output->writeln($this->urls->certificate($certificate->code));

        if (null !== $certificate->completion) {
            $output->writeln(\sprintf('Completion: %s', $certificate->completion));
        }

        return Command::SUCCESS;
    }
}
