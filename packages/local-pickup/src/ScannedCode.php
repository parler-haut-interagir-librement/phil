<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Internal\Check;
use SensitiveParameter;

/**
 * What a scanner read from a pickup QR code: the order number and the code it carries.
 */
final readonly class ScannedCode
{
    /** The longest payload read: an order number of 64 characters, a colon and a code typed with its separators. */
    public const int MAX_PAYLOAD = 97;

    private function __construct(
        public string $order,
        #[SensitiveParameter]
        public string $code,
    ) {
    }

    /**
     * @throws InvalidPickup when the payload is not an order number, a colon and a code
     */
    public static function parse(#[SensitiveParameter] string $payload): self
    {
        $colon = \strlen($payload) > self::MAX_PAYLOAD ? false : strrpos($payload, ':');

        if (false === $colon) {
            throw InvalidPickup::because('scanned payload', 'is an order number, a colon and a pickup code');
        }

        $code = PickupCodes::normalize(substr($payload, $colon + 1));

        if (null === $code) {
            throw InvalidPickup::because('scanned payload', 'ends with a pickup code');
        }

        return new self(Check::order(substr($payload, 0, $colon)), $code);
    }

    /**
     * @return array{order: string, code: string}
     */
    public function __debugInfo(): array
    {
        return ['order' => $this->order, 'code' => '********'];
    }
}
