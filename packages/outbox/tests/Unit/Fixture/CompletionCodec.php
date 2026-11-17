<?php

declare(strict_types=1);

namespace Phil\Outbox\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\Outbox\Contract\EventCodec;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Payload;

/**
 * A codec of a package's own event, `set.completed`, which can be told to fail.
 */
final class CompletionCodec implements EventCodec
{
    /**
     * @param Closure(OutboxRecord): void|null $before
     */
    public function __construct(
        public ?Closure $before = null,
    ) {
    }

    #[Override]
    public function types(): array
    {
        return ['set.completed'];
    }

    #[Override]
    public function decode(OutboxRecord $record, Payload $payload): SetCompleted
    {
        if ($this->before instanceof Closure) {
            ($this->before)($record);
        }

        return new SetCompleted($record->id->value, $payload->string('set'), $payload->int('holder'));
    }
}
