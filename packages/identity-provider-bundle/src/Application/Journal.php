<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application;

use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;
use Phil\IdentityProviderBundle\Application\Event\IdentityEvent;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Where the services tell what happened: each event is written to the audit trail and dispatched, identifiers only.
 */
final readonly class Journal
{
    public function __construct(
        private AuditRecorder $audit,
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
    }

    /**
     * @param array<string, bool|int|string|null> $context
     */
    public function record(IdentityEventType $type, ?string $accountId, array $context = []): void
    {
        $this->audit->record($type->value, $accountId, $context);
        $this->dispatcher?->dispatch(new IdentityEvent($type, $accountId, $context));
    }
}
