<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The URL of a content root in a locale: its current route, under the Symfony route of its kind.
 *
 * `_locale` is passed only when it differs from the locale of the request being served, so a site with one
 * locale and no `{_locale}` in its paths never gets a `?_locale=` query; a site with several mounts its
 * routes with `{_locale}`, or as localized routes, which the generator picks by `_locale`.
 */
final readonly class ContentUrlGenerator
{
    public function __construct(
        private RouteRepository $routes,
        private ResourceRoutes $resourceRoutes,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * `null` when the root has no current route in the locale, or its kind is mounted on no route.
     */
    public function generate(ContentReference $content, Locale $locale, bool $absolute = false): ?string
    {
        $name = $this->resourceRoutes->routeOf($content->resourceKey);
        $route = null === $name ? null : $this->routes->findCurrent($content, $locale);

        return null === $name || !$route instanceof Route ? null : $this->url($name, $route, $locale, $absolute);
    }

    /**
     * The URLs of many contents in a locale, their current routes read in one lookup per kind: what a sitemap and a
     * feed generate. A content without a URL — no current route in the locale, or its kind mounted on no route — is
     * missing from the result.
     *
     * @param array<ContentReference> $contents
     *
     * @return array<string, string> by the string of each reference
     */
    public function generateAll(array $contents, Locale $locale, bool $absolute = false): array
    {
        $idsByKind = [];

        foreach ($contents as $content) {
            $idsByKind[$content->resourceKey][] = $content->resourceId;
        }

        $urls = [];

        foreach ($idsByKind as $resourceKey => $resourceIds) {
            $name = $this->resourceRoutes->routeOf($resourceKey);

            if (null === $name) {
                continue;
            }

            foreach ($this->routes->findCurrentAmong($resourceKey, $locale, $resourceIds) as $route) {
                $urls[$route->content()->toString()] = $this->url($name, $route, $locale, $absolute);
            }
        }

        return $urls;
    }

    private function url(string $name, Route $route, Locale $locale, bool $absolute): string
    {
        $parameters = ['slug' => $route->slug()->value];

        if ($this->urlGenerator->getContext()->getParameter('_locale') !== $locale->value) {
            $parameters['_locale'] = $locale->value;
        }

        return $this->urlGenerator->generate(
            $name,
            $parameters,
            $absolute ? UrlGeneratorInterface::ABSOLUTE_URL : UrlGeneratorInterface::ABSOLUTE_PATH,
        );
    }
}
