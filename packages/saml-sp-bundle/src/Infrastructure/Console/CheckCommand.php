<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Console;

use DateInterval;
use DateTimeImmutable;
use Phil\SamlSp\Exception\SamlException;
use Phil\SamlSp\Key\Certificate;
use Phil\SamlSp\Key\KeyPair;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Fetches again the metadata of each identity provider — or of one —, and reports its services and the expiry of its
 * signing certificates and of the certificates of the service provider. It fails when metadata cannot be read, or a
 * certificate expires within the days given, so that a scheduled run warns before sign-ins stop.
 */
#[AsCommand(name: 'phil:saml:check', description: 'Checks the metadata of the identity providers and the expiry of the certificates.')]
final readonly class CheckCommand
{
    public function __construct(private IdentityProviders $providers, private ClockInterface $clock)
    {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'The key of one identity provider; every one by default.')]
        ?string $idp = null,
        #[Option(description: 'Warn about a certificate that expires within this many days.')]
        int $days = 30,
    ): int {
        $keys = null === $idp ? $this->providers->keys() : [$idp];
        $limit = $this->clock->now()->add(new DateInterval('P'.max(0, $days).'D'));
        $failed = false;

        foreach ($keys as $key) {
            $io->section($key);

            try {
                $metadata = $this->providers->identityProvider($key, true)->metadata;
            } catch (SamlException $samlException) {
                $io->error($samlException->getMessage());
                $failed = true;

                continue;
            }

            $io->definitionList(
                ['Entity ID' => $metadata->entityId],
                ['Sign-on' => implode(', ', $metadata->singleSignOnServices)],
                ['Logout' => [] === $metadata->singleLogoutServices ? 'none' : implode(', ', $metadata->singleLogoutServices)],
                ['Valid until' => $metadata->validUntil?->format('Y-m-d H:i T') ?? 'not said'],
            );
            $failed = $this->certificates($io, 'Signing certificates of the identity provider', $metadata->signingCertificates, $limit) || $failed;
        }

        $own = array_filter([$this->providers->signing()?->certificate, ...array_map(static fn (KeyPair $pair): Certificate => $pair->certificate, $this->providers->encryption())]);
        $failed = $this->certificates($io, 'Certificates of the service provider', $own, $limit) || $failed;

        if ($failed) {
            $io->error('Sign-ins through an identity provider may fail: see above.');

            return Command::FAILURE;
        }

        $io->success('The identity providers and the certificates are in order.');

        return Command::SUCCESS;
    }

    /**
     * @param array<Certificate> $certificates
     *
     * @return bool whether one expires before the limit
     */
    private function certificates(SymfonyStyle $io, string $title, array $certificates, DateTimeImmutable $limit): bool
    {
        $now = $this->clock->now();
        $rows = [];
        $failed = false;

        foreach ($certificates as $certificate) {
            $expiry = $certificate->notAfter();
            $state = $expiry <= $now ? 'expired' : ($expiry <= $limit ? 'expires soon' : 'valid');
            $failed = $failed || 'valid' !== $state;
            $rows[] = [$certificate->subject(), substr($certificate->fingerprint(), 0, 16), $expiry->format('Y-m-d'), $state];
        }

        if ([] !== $rows) {
            $io->text($title);
            $io->table(['Subject', 'SHA-256', 'Expires', 'State'], $rows);
        }

        return $failed;
    }
}
