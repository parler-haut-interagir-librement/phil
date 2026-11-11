<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Upload;

/**
 * An image a writer sent from the editor, as the request received it: a temporary file, and what the browser said
 * of it. The name and the type are the browser's word, never checked: the uploader reads the file itself.
 */
final readonly class ImageUpload
{
    public function __construct(
        public string $path,
        public string $clientName,
        public string $clientMimeType,
        public int $size,
    ) {
    }
}
