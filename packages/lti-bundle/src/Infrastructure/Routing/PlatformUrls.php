<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Routing;

use Phil\Lti\Message\AgsEndpoint;
use Phil\Lti\Message\NrpsEndpoint;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Lti\Service\Scope;
use Phil\Url\Url;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The addresses of the platform's services, for the claims of the launches the application builds: the endpoint of the
 * Assignment and Grade Services of a context and of a line item, with the scopes the tool is registered for, and the
 * memberships of the context.
 */
final readonly class PlatformUrls
{
    public function __construct(
        private UrlGeneratorInterface $router,
        private bool $allowLocalhost = false,
    ) {
    }

    public function agsEndpoint(ToolRegistration $tool, string $contextId, ?string $lineItemId = null): AgsEndpoint
    {
        $scopes = array_values(array_filter([Scope::LINE_ITEM, Scope::LINE_ITEM_READ_ONLY, Scope::RESULT_READ_ONLY, Scope::SCORE], $tool->grants(...)));

        return new AgsEndpoint($scopes, $this->url('phil_lti_platform_line_items', ['context' => $contextId]), null === $lineItemId ? null : $this->lineItem($contextId, $lineItemId));
    }

    public function nrpsEndpoint(string $contextId): NrpsEndpoint
    {
        return new NrpsEndpoint($this->url('phil_lti_platform_memberships', ['context' => $contextId]));
    }

    public function lineItem(string $contextId, string $lineItemId): Url
    {
        return $this->url('phil_lti_platform_line_item', ['context' => $contextId, 'id' => $lineItemId]);
    }

    /**
     * @param array<string, string|int> $parameters
     */
    public function url(string $route, array $parameters = []): Url
    {
        return Url::fromString($this->router->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL), $this->allowLocalhost);
    }
}
