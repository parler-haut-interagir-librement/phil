<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Every route under `/_el/`.
 */
final readonly class Router implements UrlGeneratorInterface
{
    #[Override]
    public function setContext(RequestContext $context): void
    {
    }

    #[Override]
    public function getContext(): RequestContext
    {
        return new RequestContext();
    }

    /**
     * @param array<mixed> $parameters
     */
    #[Override]
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '/_el/'.$name;
    }
}
