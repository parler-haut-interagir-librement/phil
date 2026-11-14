<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Functional\Members;

use Phil\TenancyBundle\Application\TenantContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final readonly class MembersController
{
    public function __construct(
        private Security $security,
        private TenantContext $context,
    ) {
    }

    public function whoami(Request $request): JsonResponse
    {
        return new JsonResponse([
            'account' => $this->security->getUser()?->getUserIdentifier(),
            'firewall' => $this->security->getFirewallConfig($request)?->getName(),
            'tenant' => $this->context->current()?->value,
        ]);
    }
}
