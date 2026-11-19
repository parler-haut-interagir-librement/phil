<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class FixedUrls implements UrlGeneratorInterface
{
    /** @var array{string, array<mixed>, int}|null */
    public ?array $asked = null;

    private RequestContext $context;

    public function __construct(
        private readonly bool $routed = true,
    ) {
        $this->context = new RequestContext();
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
        if (!$this->routed) {
            throw new RouteNotFoundException($name);
        }

        $this->asked = [$name, $parameters, $referenceType];

        return 'https://example.com/errors';
    }
}
