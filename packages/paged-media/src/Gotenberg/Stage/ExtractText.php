<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Internal\Check;
use Phil\PagedMedia\Gotenberg\Pages;

/**
 * Returns the text the pages show as a UTF-8 `.txt` file, the pages separated by a form feed. It comes last; after
 * other stages, the ZIP holds the PDF next to its text.
 */
final readonly class ExtractText implements Stage
{
    public ?Pages $pages;

    /**
     * @param bool $invisible   adds the text layer a searchable scan carries under its image
     * @param bool $artifacts   adds the headers, footers, page numbers and stamps marked as artifacts
     * @param bool $annotations adds the text of annotations, such as comments
     */
    public function __construct(
        Pages|string|null $pages = null,
        public bool $invisible = false,
        public bool $artifacts = false,
        public bool $annotations = false,
    ) {
        $this->pages = \is_string($pages) ? Pages::of($pages) : $pages;
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::ExtractText;
    }

    #[Override]
    public function options(): array
    {
        return Check::set([
            'pages' => $this->pages?->value,
            'invisible' => $this->invisible ? true : null,
            'artifacts' => $this->artifacts ? true : null,
            'annotations' => $this->annotations ? true : null,
        ]);
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
