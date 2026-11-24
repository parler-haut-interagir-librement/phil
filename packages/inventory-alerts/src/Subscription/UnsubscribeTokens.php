<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

use Phil\InventoryAlerts\Exception\InvalidInput;
use SensitiveParameter;

/**
 * The tokens of the one-click unsubscription links: the subscription identifier signed with a secret of the
 * application (HMAC-SHA-256), so nothing more is stored and a link cannot be guessed for another subscription.
 */
final readonly class UnsubscribeTokens
{
    private const string CONTEXT = 'phil/inventory-alerts unsubscribe ';

    public function __construct(#[SensitiveParameter] private string $secret)
    {
        if (\strlen($secret) < 32) {
            throw InvalidInput::because('unsubscription secret', 'must have at least 32 bytes');
        }
    }

    public function issue(string $subscriptionId): string
    {
        return $subscriptionId.'.'.$this->signature($subscriptionId);
    }

    /**
     * The subscription identifier a token was issued for, or null for a token this secret did not sign.
     */
    public function verify(string $token): ?string
    {
        if (1 !== preg_match('/^([0-9a-f]{32})\.([A-Za-z0-9_-]{43})$/D', $token, $parts)) {
            return null;
        }

        return hash_equals($this->signature($parts[1]), $parts[2]) ? $parts[1] : null;
    }

    private function signature(string $subscriptionId): string
    {
        return sodium_bin2base64(hash_hmac('sha256', self::CONTEXT.$subscriptionId, $this->secret, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
