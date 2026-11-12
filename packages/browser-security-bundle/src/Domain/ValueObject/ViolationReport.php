<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\ValueObject;

use Phil\BrowserSecurityBundle\Domain\Enum\Disposition;

/**
 * One violation a browser reported, as the bundle keeps it: the addresses without their query nor fragment, the
 * sample cut to 40 characters, no referrer, no policy, no user agent and no address of the visitor.
 */
final readonly class ViolationReport
{
    public function __construct(
        public string $documentUri,
        public string $blockedUri,
        public string $effectiveDirective,
        public Disposition $disposition = Disposition::Enforce,
        public ?string $sourceFile = null,
        public ?int $line = null,
        public ?int $column = null,
        public ?int $statusCode = null,
        public ?string $sample = null,
    ) {
    }

    /**
     * What makes two reports the same violation: the page, what was blocked, by which directive, where in the code
     * and under which disposition. The SHA-256 of those, in hexadecimal.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode("\n", [
            $this->documentUri,
            $this->blockedUri,
            $this->effectiveDirective,
            $this->disposition->value,
            $this->sourceFile ?? '',
            $this->line ?? '',
            $this->column ?? '',
        ]));
    }
}
