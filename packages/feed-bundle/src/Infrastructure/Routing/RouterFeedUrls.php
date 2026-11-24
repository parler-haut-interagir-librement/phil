<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\Routing;

use Override;
use Phil\FeedBundle\Application\Contract\FeedUrls;
use Phil\FeedBundle\Domain\FeedFormat;
use Phil\Url\Url;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The addresses of the feeds through the router: the route `phil_feed`, absolute, with `?page=` from page 2 on.
 *
 * Outside a request — in a Messenger worker — the router writes the address of its request context, which
 * `framework.router.default_uri` sets.
 */
final readonly class RouterFeedUrls implements FeedUrls
{
    public const string ROUTE = 'phil_feed';

    public function __construct(
        private UrlGeneratorInterface $router,
        private bool $allowLocalhost = false,
    ) {
    }

    #[Override]
    public function feed(string $name, FeedFormat $format, int $page = 1): Url
    {
        $parameters = ['name' => $name, 'format' => $format->value];

        if ($page > 1) {
            $parameters['page'] = $page;
        }

        return Url::fromString($this->router->generate(self::ROUTE, $parameters, UrlGeneratorInterface::ABSOLUTE_URL), $this->allowLocalhost);
    }
}
