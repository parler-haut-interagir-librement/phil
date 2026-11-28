<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Markup;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\MarkdownRenderer;
use Phil\Markup\Level;
use Phil\Markup\Markup;
use Phil\Markup\MarkupFormat;

/**
 * Markdown read by `phil/markup` — CommonMark and the GitHub extensions — and purified at the `all` level: a body
 * writes headings, emphasis, links, lists, quotes, tables and images, and nothing a sanitizer counts as unsafe. The
 * components, kept aside while Markdown is read, keep their inline styles.
 */
final readonly class MarkupMarkdown implements MarkdownRenderer
{
    public function __construct(
        private Markup $markup,
    ) {
    }

    #[Override]
    public function toHtml(string $markdown): string
    {
        return $this->markup->toHtml($markdown, MarkupFormat::Markdown, Level::All);
    }
}
