<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Infrastructure\Routing;

use DateTimeImmutable;
use Exception;
use Phil\Sitemap\Alternate;
use Phil\Sitemap\ChangeFrequency;
use Phil\Sitemap\InvalidSitemap;
use Phil\Sitemap\Url;
use Phil\Sitemap\UrlContainer;
use Phil\Sitemap\UrlProvider;
use Psr\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * The pages of the routes that carry a `sitemap` option — `true`, or `priority`, `changefreq`, `lastmod` and
 * `section`, each falling back on the defaults —, at their absolute URL. The locales of a localized route are each a
 * page, with the others as alternates. Every route is read whatever the section asked for, so that a bad option shows
 * at once; the container keeps only the section asked for.
 */
final readonly class RouteUrlProvider implements UrlProvider
{
    private const array KEYS = ['priority', 'changefreq', 'lastmod', 'section'];

    /**
     * @param array{priority: ?float, changefreq: ?string, lastmod: ?string} $defaults
     */
    public function __construct(
        private RouterInterface $router,
        private ClockInterface $clock,
        private string $defaultSection,
        private array $defaults,
        private bool $allowLocalhost = false,
    ) {
    }

    public function provide(UrlContainer $urls, ?string $section): void
    {
        $pages = [];

        foreach ($this->router->getRouteCollection()->all() as $name => $route) {
            $options = $this->options($name, $route);

            if (null === $options) {
                continue;
            }

            $canonical = $route->getDefault('_canonical_route');
            $locale = $route->getDefault('_locale');
            $group = \is_string($canonical) ? $canonical : $name;
            $pages[$group][\is_string($locale) ? $locale : ''] = [$this->router->generate($name, [], UrlGeneratorInterface::ABSOLUTE_URL), $options];
        }

        foreach ($pages as $group => $locales) {
            $alternates = [];

            foreach ($locales as $locale => [$loc]) {
                if (\count($locales) > 1) {
                    $alternates[] = new Alternate($locale, $loc, $this->allowLocalhost);
                }
            }

            foreach ($locales as [$loc, $options]) {
                $urls->add($this->url($group, $loc, $options, $alternates), $options['section']);
            }
        }
    }

    /**
     * @return ?array{priority: float|int|null, changefreq: ?string, lastmod: ?string, section: string}
     */
    private function options(string $name, Route $route): ?array
    {
        $option = $route->getOption('sitemap');

        if (null === $option || false === $option) {
            return null;
        }

        if (true === $option) {
            $option = [];
        }

        if (!\is_array($option)) {
            throw InvalidRouteOption::of($name, 'is true, false, or a map of priority, changefreq, lastmod and section.');
        }

        $unknown = array_filter(array_keys($option), static fn (int|string $key): bool => !\in_array($key, self::KEYS, true));

        if ([] !== $unknown) {
            throw InvalidRouteOption::of($name, \sprintf('knows priority, changefreq, lastmod and section, not %s.', implode(', ', $unknown)));
        }

        preg_match_all('/\{!?(\w+)\}/', $route->getPath(), $variables);
        $missing = array_filter($variables[1], static fn (string $variable): bool => !$route->hasDefault($variable));

        if ([] !== $missing) {
            throw InvalidRouteOption::of($name, \sprintf('needs %s to make a URL: give the pages of this route with a UrlProvider.', implode(', ', $missing)));
        }

        $priority = $option['priority'] ?? $this->defaults['priority'];
        $changefreq = $option['changefreq'] ?? $this->defaults['changefreq'];
        $lastmod = $option['lastmod'] ?? $this->defaults['lastmod'];
        $section = $option['section'] ?? $this->defaultSection;

        if ((null !== $priority && !\is_float($priority) && !\is_int($priority)) || (null !== $changefreq && !\is_string($changefreq)) || (null !== $lastmod && !\is_string($lastmod)) || !\is_string($section)) {
            throw InvalidRouteOption::of($name, 'takes a number as priority, and words as changefreq, lastmod and section.');
        }

        return ['priority' => $priority, 'changefreq' => $changefreq, 'lastmod' => $lastmod, 'section' => $section];
    }

    /**
     * @param array{priority: float|int|null, changefreq: ?string, lastmod: ?string, section: string} $options
     * @param list<Alternate>                                                                         $alternates
     */
    private function url(string $name, string $loc, array $options, array $alternates): Url
    {
        $changefreq = null === $options['changefreq'] ? null : ChangeFrequency::tryFrom($options['changefreq']);

        if (null !== $options['changefreq'] && null === $changefreq) {
            throw InvalidRouteOption::of($name, \sprintf('takes a changefreq of always, hourly, daily, weekly, monthly, yearly or never, not "%s".', $options['changefreq']));
        }

        try {
            return new Url($loc, $this->lastModified($name, $options['lastmod']), $changefreq, $options['priority'], $alternates, allowLocalhost: $this->allowLocalhost);
        } catch (InvalidSitemap $invalidSitemap) {
            throw InvalidRouteOption::of($name, 'makes no page: '.$invalidSitemap->getMessage());
        }
    }

    private function lastModified(string $name, ?string $lastmod): ?DateTimeImmutable
    {
        if (null === $lastmod) {
            return null;
        }

        if ('now' === $lastmod) {
            return $this->clock->now();
        }

        try {
            return new DateTimeImmutable($lastmod, $this->clock->now()->getTimezone());
        } catch (Exception) {
            throw InvalidRouteOption::of($name, \sprintf('takes "now" or a date as lastmod, not "%s".', $lastmod));
        }
    }
}
