<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The absolute addresses of the newsletter's pages, written into messages a worker sends without a request: each
 * site's base address, from the configuration, before the path of the bundle's routes. A site without a base address
 * takes the router's own context.
 */
final readonly class NewsletterUrls
{
    public const string CONFIRM = 'phil_newsletter_confirm';

    public const string UNSUBSCRIBE = 'phil_newsletter_unsubscribe';

    public const string PREFERENCES = 'phil_newsletter_preferences';

    public const string CLICK = 'phil_newsletter_click';

    /**
     * @param array<string, string> $baseUrls each site's base address, `https://example.com`, by site key
     */
    public function __construct(
        private UrlGeneratorInterface $router,
        private array $baseUrls = [],
    ) {
    }

    public function to(string $route, string $token, Site $site): string
    {
        $base = $this->baseUrls[$site->key] ?? null;

        if (null === $base) {
            return $this->router->generate($route, ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return rtrim($base, '/').$this->router->generate($route, ['token' => $token], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
