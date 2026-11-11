<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * Every route under `/_be/`, but those said to be missing.
 */
final readonly class FixedRouter implements UrlGeneratorInterface
{
    /**
     * @param list<string> $missing
     */
    public function __construct(
        private array $missing = [],
    ) {
    }

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
        return \in_array($name, $this->missing, true) ? throw new RouteNotFoundException($name) : '/_be/'.$name;
    }
}
