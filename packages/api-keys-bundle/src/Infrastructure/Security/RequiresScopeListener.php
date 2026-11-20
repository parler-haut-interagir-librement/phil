<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\Security;

use Phil\ApiKeysBundle\Infrastructure\Attribute\RequiresScope;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Checks the `#[RequiresScope]` of a controller before it runs. A request nobody is authenticated on is answered
 * `401 Unauthorized` with the challenge of RFC 6750; otherwise each scope named must be granted by `ScopeVoter`, or the
 * request is answered `403 Forbidden` with the `insufficient_scope` challenge. Without Symfony's security, nobody can
 * be authenticated and every such controller is answered `401`.
 */
final readonly class RequiresScopeListener
{
    /** The attribute of Symfony's `AuthenticatedVoter` that any authenticated request holds. */
    public const string AUTHENTICATED = 'IS_AUTHENTICATED';

    public function __construct(
        private ?AuthorizationCheckerInterface $authorization,
        private string $realm,
    ) {
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $required = $event->getAttributes(RequiresScope::class);

        if ([] === $required) {
            return;
        }

        $realm = \sprintf('Bearer realm="%s"', addcslashes($this->realm, '"\\'));

        if (true !== $this->authorization?->isGranted(self::AUTHENTICATED)) {
            throw new UnauthorizedHttpException($realm, 'An API token is required.');
        }

        foreach ($required as $scope) {
            if (!$this->authorization->isGranted(ScopeVoter::ATTRIBUTE, $scope->scope)) {
                throw new AccessDeniedHttpException(\sprintf('The token does not hold the scope "%s".', $scope->scope), null, 0, ['WWW-Authenticate' => \sprintf('%s,error="insufficient_scope",scope="%s"', $realm, addcslashes($scope->scope, '"\\'))]);
            }
        }
    }
}
