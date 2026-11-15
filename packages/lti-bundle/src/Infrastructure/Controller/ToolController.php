<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Controller;

use Phil\Lti\Exception\LtiException;
use Phil\Lti\Tool\LoginInitiator;
use Phil\Lti\Tool\LoginRequest;
use Phil\LtiBundle\Application\Event\LaunchReceived;
use Phil\LtiBundle\Infrastructure\Security\LtiLaunchAuthenticator;
use Phil\LtiBundle\Infrastructure\Security\LtiToken;
use Phil\Url\Url;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The two routes of the tool: the login initiation, which sends the browser to the platform with an authentication
 * request, and the launch, which the authenticator verified before: the application answers it through
 * `LaunchReceived`, or the browser goes to the target of the launch.
 */
final readonly class ToolController
{
    public const string LOGIN_ROUTE = 'phil_lti_login';

    public function __construct(
        private LoginInitiator $initiator,
        private UrlGeneratorInterface $router,
        private TokenStorageInterface $tokens,
        private EventDispatcherInterface $events,
        private bool $allowLocalhost = false,
    ) {
    }

    public function login(Request $request): Response
    {
        try {
            $redirect = $this->initiator->initiate(
                LoginRequest::fromParameters($request->isMethod('POST') ? $request->request->all() : $request->query->all()),
                Url::fromString($this->router->generate(LtiLaunchAuthenticator::ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL), $this->allowLocalhost),
            );
        } catch (LtiException $ltiException) {
            return $this->refused($ltiException->getMessage());
        }

        return new RedirectResponse($redirect->url->toString(), Response::HTTP_FOUND, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function launch(Request $request): Response
    {
        $token = $this->tokens->getToken();

        if (!$token instanceof LtiToken) {
            return $this->refused('No verified launch: the firewall of this route lacks the authenticator of the bundle.');
        }

        $event = new LaunchReceived($token->launch, $request);
        $this->events->dispatch($event);

        return $event->response() ?? new RedirectResponse($token->launch->message->targetLinkUri->toString(), Response::HTTP_SEE_OTHER);
    }

    private function refused(string $reason): Response
    {
        return new Response('The launch is refused: '.$reason, Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
