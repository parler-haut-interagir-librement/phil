<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * A generator that writes a route and its parameters as a path: `/phil_onboarding_done/author/profile`.
 */
final class Urls implements UrlGeneratorInterface
{
    private RequestContext $context;

    public function __construct()
    {
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
        return '/'.implode('/', [$name, ...array_map(static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '', array_values($parameters))]);
    }
}
