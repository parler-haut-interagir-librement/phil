<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional\App;

use Override;
use Phil\FeatureFlagsBundle\Application\Admin\ActorProvider;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The operator of a request from its `X-Operator` header. A real application reads its security token.
 */
final readonly class HeaderActorProvider implements ActorProvider
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        $operator = $this->requests->getMainRequest()?->headers->get('X-Operator');

        return '' === $operator ? null : $operator;
    }
}
