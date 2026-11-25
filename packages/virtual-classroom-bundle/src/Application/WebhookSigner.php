<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application;

use Phil\VirtualClassroomBundle\Domain\Exception\InvalidRoom;
use SensitiveParameter;

/**
 * The proof an address of the webhook carries: the HMAC-SHA-256 of its provider, its room and its channel under a key
 * derived from the application's secret, 32 characters of base64url. The address of each channel of each room is its
 * own, so that the address of the signed events, which BigBlueButton shows to the participants, cannot end the room,
 * nor reach another room.
 */
final readonly class WebhookSigner
{
    public const int TOKEN_LENGTH = 32;

    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_virtual_classroom.webhook_address';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    /**
     * @throws InvalidRoom when the secret is shorter than 16 bytes
     */
    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        if (\strlen($secret) < 16) {
            throw InvalidRoom::because('The secret of the webhook addresses is 16 bytes or more.');
        }

        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    public function token(string $provider, ?string $room, string $channel): string
    {
        $mac = hash_hmac('sha256', implode("\0", ['phil_virtual_classroom', $provider, $room ?? '', $channel]), $this->key, true);

        return substr(sodium_bin2base64($mac, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, self::TOKEN_LENGTH);
    }

    public function verify(string $token, string $provider, ?string $room, string $channel): bool
    {
        return hash_equals($this->token($provider, $room, $channel), $token);
    }
}
