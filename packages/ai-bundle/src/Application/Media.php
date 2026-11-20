<?php

declare(strict_types=1);

namespace Phil\AiBundle\Application;

/**
 * An image or a piece of speech the model generated: its bytes and their media type. It lives only in the suggestion;
 * the journal never holds it.
 */
final readonly class Media
{
    public function __construct(
        public string $bytes,
        public string $mimeType = 'application/octet-stream',
    ) {
    }

    /**
     * The bytes as a `data:` address, for an `<img>` or an `<audio>` shown before the person decides.
     */
    public function toDataUri(): string
    {
        return 'data:'.$this->mimeType.';base64,'.sodium_bin2base64($this->bytes, \SODIUM_BASE64_VARIANT_ORIGINAL);
    }
}
