<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Template;

use Phil\PagedMedia\Exception\InvalidDocument;
use Phil\PagedMedia\Image\Image;

/**
 * One gift card: its amount as the application formats it, its code and PIN, who it is for, a message, until when it
 * is valid, already written as the reader reads it, and an image of its code — a barcode or a QR code.
 */
final readonly class GiftCard
{
    public function __construct(
        public string $amount,
        public string $code,
        public ?string $pin = null,
        public ?string $recipient = null,
        public ?string $message = null,
        public ?string $validUntil = null,
        public ?Image $codeImage = null,
    ) {
        if ('' === trim($amount) || '' === trim($code)) {
            throw InvalidDocument::because('A gift card has an amount and a code.');
        }
    }
}
