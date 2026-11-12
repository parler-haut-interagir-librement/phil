<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Fixture;

use Override;
use Phil\FormsAutosave\Infrastructure\Http\Routes;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * A router that knows the draft route under `/_autosave`, or none.
 */
final class Router implements UrlGeneratorInterface
{
    private RequestContext $context;

    public function __construct(
        private readonly bool $knowsRoutes = true,
    ) {
        $this->context = new RequestContext();
    }

    /**
     * @param array<mixed> $parameters
     */
    #[Override]
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        if (!$this->knowsRoutes || Routes::DRAFT !== $name || !\is_string($parameters['key'] ?? null)) {
            throw new RouteNotFoundException($name);
        }

        return '/_autosave/drafts/'.$parameters['key'];
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
}
