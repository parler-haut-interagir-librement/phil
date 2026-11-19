<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Override;
use Phil\ConversationBundle\Application\Contract\MessageSealer;
use SensitiveParameter;

/**
 * The sealer of a store whose rows another request writes while the rotation runs: each time a text is opened, every
 * subject and body of the store is written again, sealed under the current key.
 */
class RacingSealer implements MessageSealer
{
    private readonly SealerStandIn $inner;

    public function __construct(
        private readonly Store $store,
    ) {
        $this->inner = new SealerStandIn();
    }

    #[Override]
    public function seal(#[SensitiveParameter] string $text, string $context): string
    {
        return $this->inner->seal($text, $context);
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        $text = new SealerStandIn('k2025')->open($sealed, $context);
        $this->store->connection->executeStatement('UPDATE phil_conversation SET subject = ?, subject_key = ?', ['rewritten', 'k2026']);
        $this->store->connection->executeStatement('UPDATE phil_conversation_message SET body = ?, body_key = ?', ['rewritten', 'k2026']);

        return $text;
    }

    #[Override]
    public function currentKey(): string
    {
        return $this->inner->currentKey();
    }
}
