<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use LogicException;
use Override;
use Phil\EditLockBundle\Application\Broadcaster;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\Ujo\Document;
use Phil\Ujo\Json\JsonEncoder;

/**
 * Writes down what it is told, one line per message.
 */
final class RecordingBroadcaster implements Broadcaster
{
    /** @var list<string> */
    public array $told = [];

    #[Override]
    public function presence(string $resource, array $leases, DateTimeImmutable $now): void
    {
        if ([] !== $leases && array_keys($leases) !== range(0, \count($leases) - 1)) {
            throw new LogicException('The leases told are a list.');
        }

        $this->told[] = 'presence '.$resource.' ['.implode(', ', array_map(static fn (Lease $lease): string => $lease->key()->toString().'='.$lease->holder()->id, $leases)).'] '.$now->format('H:i:s');
    }

    #[Override]
    public function saved(string $resource, Holder $holder, Document $block, string $list, ?string $after): void
    {
        $this->told[] = 'saved '.$resource.' by '.$holder->id.' in '.$list.' after '.($after ?? '-').' '.new JsonEncoder()->encode($block);
    }

    #[Override]
    public function removed(string $resource, Holder $holder, string $blockId): void
    {
        $this->told[] = 'removed '.$resource.' by '.$holder->id.' '.$blockId;
    }
}
