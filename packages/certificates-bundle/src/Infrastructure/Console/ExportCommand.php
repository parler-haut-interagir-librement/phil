<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use Override;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:export 7KQ2-M9XA-04TZ > credential.jwt.
 *
 * Writes the signed credential of a certificate as it was issued — a compact JSON Web Token, or the JSON of a
 * document with its proof — and nothing else, for a validator or a wallet to read. Its kind goes to the error output,
 * so that the file stays the credential alone.
 */
#[AsCommand(name: 'phil:certificates:export', description: 'Writes the signed credential of a certificate, for a validator or a wallet')]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly CertificateRepository $certificates,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument('code', InputArgument::REQUIRED, 'The verification code of the certificate');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        /** @var string $code */
        $code = $input->getArgument('code');

        try {
            $certificate = $this->certificates->findByCode(VerificationCode::fromString($code)->value);
        } catch (InvalidValue $invalidValue) {
            $errors->writeln($invalidValue->getMessage());

            return Command::INVALID;
        }

        if (!$certificate instanceof Certificate) {
            $errors->writeln(\sprintf('There is no certificate %s.', $code));

            return Command::FAILURE;
        }

        $errors->writeln($certificate->isToken() ? 'A JSON Web Token: application/vc+jwt, credential.jwt.' : 'A document with its proof: application/vc, credential.json.', OutputInterface::VERBOSITY_VERBOSE);
        $output->write($certificate->credential, false, OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
