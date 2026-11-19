<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Functional\App;

use Phil\ApiKeysBundle\Infrastructure\Attribute\RequiresScope;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * An API that reads projects with `projects:read` and changes them with `projects:write`, counted by token.
 */
#[RequiresScope('projects:read')]
#[RateLimit('api')]
final readonly class ProjectController
{
    public function __construct(
        private TokenStorageInterface $tokens,
    ) {
    }

    public function list(): JsonResponse
    {
        return new JsonResponse(['user' => $this->tokens->getToken()?->getUserIdentifier(), 'roles' => $this->tokens->getToken()?->getRoleNames()]);
    }

    #[RequiresScope('projects:write')]
    public function update(): JsonResponse
    {
        return new JsonResponse(['updated' => true]);
    }
}
