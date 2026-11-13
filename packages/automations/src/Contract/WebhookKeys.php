<?php

declare(strict_types=1);

namespace Phil\Automations\Contract;

use Phil\StandardWebhooks\SigningKey;

/**
 * The signing keys of the webhook action, by the name a definition gives: a definition never holds a secret.
 */
interface WebhookKeys
{
    /**
     * @return list<SigningKey> the keys to sign with, the newest first; none when the name is unknown
     */
    public function signingKeys(string $name): array;
}
