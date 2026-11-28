<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Tenancy;

use Override;
use Phil\EmailTemplatesBundle\Application\Contract\SiteScope;
use Phil\EmailTemplatesBundle\Domain\ValueObject\Site;
use Phil\TenancyBundle\Application\TenantContext;

/**
 * The site is the tenant of `phil/tenancy-bundle`'s context — the one the request, the message or the command acts
 * in —, read and never changed. Outside any tenant, the shared scope.
 */
final readonly class TenancySiteScope implements SiteScope
{
    public function __construct(
        private TenantContext $context,
    ) {
    }

    #[Override]
    public function current(): Site
    {
        return Site::fromString($this->context->current()->value ?? '');
    }
}
