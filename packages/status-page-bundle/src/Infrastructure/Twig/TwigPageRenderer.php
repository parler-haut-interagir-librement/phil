<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Infrastructure\Twig;

use Override;
use Phil\StatusPageBundle\Application\Contract\PageRenderer;
use Phil\StatusPageBundle\Application\Render\PageLinks;
use Phil\StatusPageBundle\Domain\ValueObject\StatusPage;
use Twig\Environment;

/**
 * The page drawn by `@PhilStatusPage/page.html.twig`: within `$layout` when it is served, within the document of its
 * own, `@PhilStatusPage/snapshot.html.twig`, when it is a snapshot, with `$stylesheet` linked when the application
 * gives one.
 */
final readonly class TwigPageRenderer implements PageRenderer
{
    public const string TEMPLATE = '@PhilStatusPage/page.html.twig';

    public const string LAYOUT = '@PhilStatusPage/layout.html.twig';

    public const string SNAPSHOT_LAYOUT = '@PhilStatusPage/snapshot.html.twig';

    public function __construct(
        private Environment $twig,
        private string $layout = self::LAYOUT,
        private ?string $stylesheet = null,
    ) {
    }

    #[Override]
    public function render(StatusPage $page, PageLinks $links, bool $standalone): string
    {
        return $this->twig->render(self::TEMPLATE, [
            'layout' => $standalone ? self::SNAPSHOT_LAYOUT : $this->layout,
            'page' => $page,
            'links' => $links,
            'standalone' => $standalone,
            'stylesheet' => $this->stylesheet,
        ]);
    }
}
