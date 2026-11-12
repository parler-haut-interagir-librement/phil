<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Infrastructure\Security;

use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

/**
 * Answers a denied assurance level with the challenge of RFC 9470 when the client is not a page navigation: a redirect
 * to the provider means nothing to a script.
 */
final readonly class AssuranceChallengeListener
{
    public function __construct(
        private AssuranceLevels $levels,
        private TokenStorageInterface $tokens,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isScripted($request) || !$this->tokens->getToken()?->getUser() instanceof UserInterface) {
            return;
        }

        $level = $this->deniedLevel($event->getThrowable());
        if (!$level instanceof AssuranceLevel) {
            return;
        }

        $event->allowCustomResponseCode();
        $event->setResponse(new Response('', Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => $this->challenge($level)]));
    }

    private function isScripted(Request $request): bool
    {
        return $request->isXmlHttpRequest() || 'html' !== $request->getPreferredFormat('html');
    }

    private function deniedLevel(Throwable $exception): ?AssuranceLevel
    {
        for ($current = $exception; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof AccessDeniedException) {
                foreach ($current->getAttributes() as $attribute) {
                    if (\is_string($attribute) && str_starts_with($attribute, AssuranceVoter::PREFIX)) {
                        return $this->levels->get(substr($attribute, \strlen(AssuranceVoter::PREFIX)));
                    }
                }
            }
        }

        return null;
    }

    private function challenge(AssuranceLevel $level): string
    {
        $parameters = ['error="insufficient_user_authentication"', 'error_description="A stronger authentication is required."'];
        $classes = $this->levels->acceptableClasses($level);
        if ([] !== $classes) {
            $parameters[] = 'acr_values="'.implode(' ', $classes).'"';
        }

        if (null !== $level->maxAge) {
            $parameters[] = 'max_age="'.$level->maxAge.'"';
        }

        return 'Bearer '.implode(', ', $parameters);
    }
}
