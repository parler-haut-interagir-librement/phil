<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Application\Contract;

use Phil\ConversationBundle\Domain\Exception\UnreadableMessage;
use SensitiveParameter;

/**
 * Seals a text at rest and opens it again, bound to a context — the row it belongs to — so that a sealed text moved to
 * another row does not open. A sealed text names the master key it was sealed under, so that the texts sealed before
 * the current key changed still open while the key management service holds their key, and can be sealed again.
 */
interface MessageSealer
{
    public function seal(#[SensitiveParameter] string $text, string $context): string;

    /**
     * @throws UnreadableMessage when the sealed text does not open in this context
     */
    public function open(string $sealed, string $context): string;

    /**
     * The master key texts are sealed under now.
     */
    public function currentKey(): string;
}
