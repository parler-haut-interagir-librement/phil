<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * A URL generator that answers every route with the same address, or none when the routes are not imported.
 */
final class FixedUrls implements UrlGeneratorInterface
{
    /** @var list<array{string, array<array-key, mixed>, int}> */
    public array $asked = [];

    private RequestContext $context;

    public function __construct(
        private readonly bool $routed = true,
    ) {
        $this->context = new RequestContext();
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    #[Override]
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        $this->asked[] = [$name, $parameters, $referenceType];

        if (!$this->routed) {
            throw new RouteNotFoundException('No route '.$name);
        }

        return 'https://example.com/messages';
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
