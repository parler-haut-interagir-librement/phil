<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Application\Contract;

use Phil\StatusPageBundle\Application\Render\PageLinks;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;

/**
 * Writes the HTML of the page: within the application's layout when it is served, as a document of its own when it is
 * a snapshot read outside the site.
 */
interface PageRenderer
{
    public function render(StatusPage $page, PageLinks $links, bool $standalone): string;
}
