<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Application;

use DateTimeImmutable;
use Override;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Document;

/**
 * Tells nobody: the broadcaster without Mercure, where the controller asks for presence at each heartbeat instead.
 */
final readonly class NullBroadcaster implements Broadcaster
{
    #[Override]
    public function presence(string $resource, array $leases, DateTimeImmutable $now): void
    {
    }

    #[Override]
    public function saved(string $resource, Holder $holder, Document $block, string $list, ?string $after): void
    {
    }

    #[Override]
    public function removed(string $resource, Holder $holder, string $blockId): void
    {
    }
}
