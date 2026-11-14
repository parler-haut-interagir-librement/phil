<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Application;

use Phil\CertificatesBundle\Domain\Contract\CertificateRepository;
use Phil\CertificatesBundle\Domain\Event\CertificateRevoked;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Revokes a certificate, with its reason: the register shows it revoked, its PDF and its credential are no longer
 * served, and the next status list published sets its bit, so that verifiers elsewhere learn it too. Once stored,
 * `CertificateRevoked` is dispatched when there is a dispatcher.
 */
final readonly class Revocation
{
    public function __construct(
        private CertificateRepository $certificates,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function revoke(string $code, string $reason): Certificate
    {
        $certificate = $this->certificates->findByCode(VerificationCode::fromString($code)->value) ?? throw CertificateRefused::because(\sprintf('There is no certificate %s.', $code));
        $certificate->revoke($reason, $this->clock->now());

        $this->certificates->save($certificate);
        $this->events?->dispatch(new CertificateRevoked($certificate->id, $certificate->code->value, $certificate->course->slug));

        return $certificate;
    }
}
