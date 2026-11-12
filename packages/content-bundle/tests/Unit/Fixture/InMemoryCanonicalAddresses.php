<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Fixture;

use Override;
use Phil\ContentBundle\Application\Contract\CanonicalAddresses;
use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Url\Url;

/**
 * `https://example.org/{language tag}/{slug}` of a content's current route.
 */
final readonly class InMemoryCanonicalAddresses implements CanonicalAddresses
{
    public function __construct(private RouteRepository $routes)
    {
    }

    #[Override]
    public function of(ContentReference $content, Locale $locale): ?Url
    {
        $route = $this->routes->findCurrent($content, $locale);

        return $route instanceof Route ? Url::fromString('https://example.org/'.$locale->toLanguageTag().'/'.$route->slug()->value) : null;
    }
}
