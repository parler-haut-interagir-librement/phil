<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\Audit;

use DateTimeInterface;
use Override;
use Phil\UserSecretsBundle\Application\Audit\SecretEvent;
use Phil\UserSecretsBundle\Application\Contract\SecretAuditor;
use Psr\Log\LoggerInterface;

/**
 * An audit hook that writes each event to a PSR-3 logger, at `info` level: what happened, to which secret of whom,
 * when, and the purpose of a read. Never a value.
 */
final readonly class LoggerAuditor implements SecretAuditor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[Override]
    public function record(SecretEvent $event): void
    {
        $this->logger->info('User secret {secret} of {owner}: {event}.', [
            'event' => $event->kind->value,
            'owner' => $event->owner->value,
            'secret' => $event->name->value,
            'at' => $event->at->format(DateTimeInterface::ATOM),
            'purpose' => $event->purpose,
        ]);
    }
}
