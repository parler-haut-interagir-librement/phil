<?php

declare(strict_types=1);

namespace Phil\RouteAccessCheckBundle\Tests\Unit\Fixture;

use LogicException;
use Override;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * A router over a fixed collection.
 */
final class StaticRouter implements RouterInterface
{
    private RequestContext $context;

    public function __construct(
        private readonly RouteCollection $routes = new RouteCollection(),
    ) {
        $this->context = new RequestContext();
    }

    #[Override]
    public function getRouteCollection(): RouteCollection
    {
        return $this->routes;
    }

    #[Override]
    public function setContext(RequestContext $context): void
    {
        $this->context = $context;
    }

    #[Override]
    public function getContext(): RequestContext
    {
        return $this->context;
    }

    /**
     * @param array<mixed> $parameters
     */
    #[Override]
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        throw new LogicException('Not used.');
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function match(string $pathinfo): array
    {
        throw new LogicException('Not used.');
    }
}
