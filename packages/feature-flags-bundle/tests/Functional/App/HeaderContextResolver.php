<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional\App;

use Override;
use Phil\FeatureFlagsBundle\Application\Flag\EvaluationContextResolver;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The tenant of a request from its `X-Tenant` header, its project from `X-Project`. A real application reads
 * them from its session and its host.
 */
final readonly class HeaderContextResolver implements EvaluationContextResolver
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): EvaluationContext
    {
        $headers = $this->requests->getMainRequest()?->headers;
        $tenant = $headers?->get('X-Tenant');
        $project = $headers?->get('X-Project');

        return EvaluationContext::of(tenant: '' === $tenant ? null : $tenant, project: '' === $project ? null : $project);
    }
}
