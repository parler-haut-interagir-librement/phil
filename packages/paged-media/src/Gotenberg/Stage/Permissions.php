<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * What a reader without the owner password may do with an encrypted PDF; each is granted unless it is turned off.
 * Viewers enforce them; only the user password keeps the content secret.
 */
final readonly class Permissions
{
    public function __construct(
        public bool $print = true,
        public bool $printHighQuality = true,
        public bool $modifyContent = true,
        public bool $extractText = true,
        public bool $modifyAnnotations = true,
        public bool $fillForms = true,
        public bool $assemble = true,
    ) {
    }

    /**
     * The permissions turned off, each `false`.
     *
     * @return array<string, false>
     */
    public function denied(): array
    {
        return array_filter([
            'print' => $this->print,
            'printHighQuality' => $this->printHighQuality,
            'modifyContent' => $this->modifyContent,
            'extractText' => $this->extractText,
            'modifyAnnotations' => $this->modifyAnnotations,
            'fillForms' => $this->fillForms,
            'assemble' => $this->assemble,
        ], static fn (bool $granted): bool => !$granted);
    }
}
