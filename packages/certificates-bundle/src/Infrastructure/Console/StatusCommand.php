<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Infrastructure\Console;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 *     bin/console phil:certificates:status 7KQ2-M9XA-04TZ [--format=json].
 *
 * Where a certificate stands now — valid, expired or revoked —, with what the register shows of it, its holder, the
 * certificate it renews and the one that renews it, and when its holder was last reminded of its end. It fails when
 * there is no such certificate, so a script can tell.
 */
#[AsCommand(name: 'phil:certificates:status', description: 'Shows where a certificate stands: valid, expired or revoked')]
final class StatusCommand extends Command
{
    public function __construct(
        private readonly CertificateRepository $certificates,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this
            ->addArgument('code', InputArgument::REQUIRED, 'The verification code of the certificate')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'text or json', 'text');
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

        $fields = $this->fields($certificate, $this->clock->now());

        if ('json' === $input->getOption('format')) {
            $output->writeln(json_encode($fields, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        foreach ($fields as $field => $value) {
            if (null !== $value) {
                $output->writeln(\sprintf('%-18s %s', $field, $value));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<string, string|null>
     */
    private function fields(Certificate $certificate, DateTimeImmutable $now): array
    {
        return [
            'code' => $certificate->code->formatted(),
            'status' => $certificate->status($now)->value,
            'course' => $certificate->course->slug,
            'name' => $certificate->recipientName,
            'awarded_at' => $certificate->awardedAt->format(\DATE_ATOM),
            'valid_until' => $certificate->validUntil?->format(\DATE_ATOM),
            'revoked_at' => $certificate->revokedAt?->format(\DATE_ATOM),
            'revocation_reason' => $certificate->revocationReason,
            'holder' => $certificate->holder,
            'renews' => $certificate->renews?->code->formatted(),
            'renewed_by' => $this->certificates->findRenewalOf($certificate)?->code->formatted(),
            'reminded_at' => $certificate->remindedAt?->format(\DATE_ATOM),
        ];
    }
}
