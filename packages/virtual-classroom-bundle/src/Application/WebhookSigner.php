<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application;

use Phil\VirtualClassroomBundle\Domain\Exception\InvalidRoom;
use SensitiveParameter;

/**
 * The proof an address of the webhook carries: the HMAC-SHA-256 of its provider, its room and its channel under the
 * application's secret, 32 characters of base64url. The address of each channel of each room is its own, so that the
 * address of the signed events, which BigBlueButton shows to the participants, cannot end the room, nor reach
 * another room.
 */
final readonly class WebhookSigner
{
    public const int TOKEN_LENGTH = 32;

    /**
     * @throws InvalidRoom when the secret is shorter than 16 bytes
     */
    public function __construct(
        #[SensitiveParameter]
        private string $secret,
    ) {
        if (\strlen($secret) < 16) {
            throw InvalidRoom::because('The secret of the webhook addresses is 16 bytes or more.');
        }
    }

    public function token(string $provider, ?string $room, string $channel): string
    {
        $mac = hash_hmac('sha256', implode("\0", ['phil_virtual_classroom', $provider, $room ?? '', $channel]), $this->secret, true);

        return substr(sodium_bin2base64($mac, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, self::TOKEN_LENGTH);
    }

    public function verify(string $token, string $provider, ?string $room, string $channel): bool
    {
        return hash_equals($this->token($provider, $room, $channel), $token);
    }
}
