<?php

declare(strict_types=1);

namespace Phil\LegalNoticesBundle\Infrastructure\Body;

use Override;
use Phil\LegalNotices\Contract\BodyRenderer;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\Markup\Exception\MarkupException;
use Phil\Markup\Level;
use Phil\Markup\Markup;

/**
 * The bodies rendered by `phil/markup`: Markdown, UJO (Universal JSON Output), HTML, plain text and every other format
 * it knows, purified at its `all` level — the level of an article an editor writes.
 */
final readonly class MarkupBody implements BodyRenderer
{
    public function __construct(
        private Markup $markup,
    ) {
    }

    #[Override]
    public function toHtml(string $format, string $body): string
    {
        try {
            return $this->markup->toHtml($body, $format, Level::All);
        } catch (MarkupException $markupException) {
            throw new InvalidLegalNotice($markupException->getMessage(), 'unreadable_body', $markupException);
        }
    }
}
