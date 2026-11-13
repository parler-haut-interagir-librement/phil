<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

use Phil\PagedMedia\Gotenberg\Result;

/**
 * A rendered file — a PDF, or the images, text or ZIP a terminal stage makes — with its name and media type, and the
 * remediation counts when a `remediate` stage ran.
 */
final readonly class Output
{
    /**
     * @param int|null $residual   after `remediate`: the violations left, `0` when the file conforms
     * @param int|null $rolledBack after `remediate`: the fixes undone because they lost pages, text or images
     */
    public function __construct(
        public string $contents,
        public string $filename,
        public string $contentType = 'application/pdf',
        public ?int $residual = null,
        public ?int $rolledBack = null,
    ) {
    }

    /**
     * What Gotenberg answered, read whole; named as Gotenberg named it, or with the name given.
     */
    public static function fromResult(Result $result, string $filename): self
    {
        return new self($result->contents(), $result->filename ?? $filename, '' === $result->contentType ? 'application/octet-stream' : $result->contentType, $result->residual, $result->rolledBack);
    }
}
