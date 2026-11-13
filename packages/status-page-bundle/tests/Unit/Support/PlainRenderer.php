<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Support;

use Override;
use Phil\StatusPageBundle\Application\Contract\PageRenderer;
use Phil\StatusPageBundle\Application\Render\PageLinks;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;

/**
 * The page as one line that tells what it was given.
 */
final readonly class PlainRenderer implements PageRenderer
{
    #[Override]
    public function render(StatusPage $page, PageLinks $links, bool $standalone): string
    {
        return \sprintf('%s|%s|%s|%s|%s|%s', $page->title, $page->status()->value, $links->page, $links->json, $links->atom, $standalone ? 'standalone' : 'served');
    }
}
