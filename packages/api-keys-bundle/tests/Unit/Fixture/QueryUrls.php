<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use Override;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * URLs written `/<route>?<parameters>`.
 */
final class QueryUrls implements UrlGeneratorInterface
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
        return '/'.$name.'?'.http_build_query($parameters);
    }
}
