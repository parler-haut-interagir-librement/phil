<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Infrastructure\Http;

use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The addresses the editor calls: the image upload and the two reference searches, each given only when its service
 * is configured and the application has imported the bundle's routes.
 */
final readonly class Endpoints
{
    public const string IMAGE = 'phil_block_editor_image';

    public const string MENTIONS = 'phil_block_editor_mentions';

    public const string LINKS = 'phil_block_editor_links';

    /**
     * @param list<string> $enabled the routes whose service is configured
     */
    public function __construct(
        private ?UrlGeneratorInterface $router = null,
        private array $enabled = [],
    ) {
    }

    public function url(string $route): ?string
    {
        if (!$this->router instanceof UrlGeneratorInterface || !\in_array($route, $this->enabled, true)) {
            return null;
        }

        try {
            return $this->router->generate($route);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
