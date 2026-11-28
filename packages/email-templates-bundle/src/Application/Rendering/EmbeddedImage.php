<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application\Rendering;

/**
 * An image attached inline to an email, which the HTML shows as `cid:<name>`.
 */
final readonly class EmbeddedImage
{
    public function __construct(
        public string $name,
        public string $body,
        public string $contentType,
    ) {
    }

    /**
     * The image as a `data:` address, for a preview that cannot read `cid:`.
     */
    public function dataUri(): string
    {
        return 'data:'.$this->contentType.';base64,'.sodium_bin2base64($this->body, \SODIUM_BASE64_VARIANT_ORIGINAL);
    }
}
