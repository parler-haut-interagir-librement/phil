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
 *     bin/console phil:certificates:recertify 7KQ2-M9XA-04TZ ada@example.org [--score=85 --minutes=60 --module=cpr].
 *
 * Renews a certificate, as `Awarding::recertify()` does: the holder's credits of continuing education are counted
 * when the course asks for some, the attainment given is checked against the criteria otherwise. It prints the code
 * of the new certificate and the address of its page. The identity is never printed.
 */
#[AsCommand(name: 'phil:certificates:recertify', description: 'Renews a certificate with a new one linked to it')]
final class RecertifyCommand extends Command
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
            ->addArgument('code', InputArgument::REQUIRED, 'The verification code of the certificate renewed')
            ->addArgument('identity', InputArgument::REQUIRED, 'The e-mail address, URL or decentralized identifier the certificate was issued to')
            ->addOption('score', null, InputOption::VALUE_REQUIRED, 'The score of a new assessment, from 0 to 100')
            ->addOption('minutes', null, InputOption::VALUE_REQUIRED, 'The minutes of training followed again')
            ->addOption('module', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'A module completed again; repeat it for each');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        /** @var string $code */
        $code = $input->getArgument('code');
        /** @var string $identity */
        $identity = $input->getArgument('identity');
        /** @var string|null $score */
        $score = $input->getOption('score');
        /** @var string|null $minutes */
        $minutes = $input->getOption('minutes');
        /** @var list<string> $modules */
        $modules = $input->getOption('module');

        try {
            $attainment = null === $score && null === $minutes && [] === $modules ? null : new Attainment(null === $score ? null : Numbers::integer('score', $score), null === $minutes ? 0 : Numbers::integer('minutes', $minutes), $modules);
            $certificate = $this->awarding->recertify($code, $identity, $attainment);
        } catch (InvalidValue $invalidValue) {
            $errors->writeln($invalidValue->getMessage());

            return Command::INVALID;
        } catch (CertificateRefused $certificateRefused) {
            $errors->writeln($certificateRefused->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('Renewed the certificate %s with %s, valid until %s.', $certificate->renews?->code->formatted(), $certificate->code->formatted(), $certificate->validUntil?->format('Y-m-d') ?? 'its course ends'));
        $output->writeln($this->urls->certificate($certificate->code));

        return Command::SUCCESS;
    }
}
