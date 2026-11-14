<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use Override;
use Phil\CertificatesBundle\Application\Revocation;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:revoke 7KQ2-M9XA-04TZ "Issued in error".
 *
 * Revokes a certificate with its reason, as `Revocation` does: the register shows it revoked and the next status list
 * published sets its bit.
 */
#[AsCommand(name: 'phil:certificates:revoke', description: 'Revokes a certificate, with its reason')]
final class RevokeCommand extends Command
{
    public function __construct(
        private readonly Revocation $revocation,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'The verification code of the certificate')
            ->addArgument('reason', InputArgument::REQUIRED, 'Why it is revoked, shown in the register; up to 500 characters');
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errors = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;

        /** @var string $code */
        $code = $input->getArgument('code');
        /** @var string $reason */
        $reason = $input->getArgument('reason');

        try {
            $certificate = $this->revocation->revoke($code, $reason);
        } catch (InvalidValue $invalidValue) {
            $errors->writeln($invalidValue->getMessage());

            return Command::INVALID;
        } catch (CertificateRefused $certificateRefused) {
            $errors->writeln($certificateRefused->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(\sprintf('Revoked the certificate %s.', $certificate->code->formatted()));

        return Command::SUCCESS;
    }
}
