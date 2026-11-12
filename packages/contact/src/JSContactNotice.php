<?php

declare(strict_types=1);

namespace Phil\Contact;

/**
 * A part of a JSContact card that was read but not kept, with the JSON pointer that names it: `/phones/p1/contexts`.
 */
final readonly class JSContactNotice
{
    public function __construct(
        public string $pointer,
        public string $reason,
    ) {
    }
}
