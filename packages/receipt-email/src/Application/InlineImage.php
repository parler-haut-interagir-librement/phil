<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Application;

/**
 * An image attached inline to the email, which its HTML shows as `cid:<name>`: never an address the client loads.
 */
final readonly class InlineImage
{
    public function __construct(
        public string $name,
        public string $body,
        public string $contentType,
    ) {
    }
}
