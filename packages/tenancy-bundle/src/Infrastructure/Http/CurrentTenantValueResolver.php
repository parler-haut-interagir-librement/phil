<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Http;

use Override;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Attribute\CurrentTenant;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves the arguments marked `#[CurrentTenant]` from the tenant context.
 */
final readonly class CurrentTenantValueResolver implements ValueResolverInterface
{
    public function __construct(
        private TenantContext $context,
    ) {
    }

    /**
     * @return iterable<?TenantId>
     */
    #[Override]
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ([] === $argument->getAttributesOfType(CurrentTenant::class)) {
            return [];
        }

        $tenant = $this->context->current();

        if (!$tenant instanceof TenantId && !$argument->isNullable()) {
            throw new AccessDeniedHttpException('No tenant is active.');
        }

        return [$tenant];
    }
}
