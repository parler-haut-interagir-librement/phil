<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Controller;

use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\LtiException;
use Phil\Lti\Http\FormPost;
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Platform\DeepLinkingReturn;
use Phil\Lti\Platform\LaunchIssuer;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Application\DeepLinkingHandler;
use Phil\LtiBundle\Infrastructure\Http\FormPostResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Environment;

/**
 * The browser routes of the platform: the authorization endpoint, which answers the tool's authentication request with
 * the signed launch for the person signed in, and the return of a deep linking response, kept before the application
 * handles it so that a handling that fails can be resumed.
 *
 * An authentication request whose client or redirect address is not registered answers 400; once the redirect address
 * is known, a refusal is posted back to it as OpenID Connect writes errors, with the `state`.
 */
final readonly class PlatformLaunchController
{
    public const string AUTHORIZE_ROUTE = 'phil_lti_platform_authorize';

    public const string DEEP_LINKING_ROUTE = 'phil_lti_platform_deep_linking';

    public function __construct(
        private LaunchIssuer $issuer,
        private LaunchMessages $messages,
        private DeepLinkingReturn $deepLinking,
        private TokenStorageInterface $tokens,
        private FormPostResponder $responder,
        private DeepLinkingHandler $handler,
        private Environment $twig,
    ) {
    }

    public function authorize(Request $request): Response
    {
        $user = $this->tokens->getToken()?->getUser();

        if (!$user instanceof UserInterface) {
            return $this->text('A launch is made for a person signed in on the platform.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $authentication = AuthenticationRequest::fromParameters($request->isMethod('POST') ? $request->request->all() : $request->query->all());
            [$tool, $redirect] = $this->issuer->check($authentication);
        } catch (LtiException $ltiException) {
            return $this->text('The authentication request is refused: '.$ltiException->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        try {
            $post = $this->issuer->authorize($authentication, $this->messages->loginHint($user), $this->messages->message($authentication, $tool, $user));
        } catch (LtiException $ltiException) {
            $fields = ['error' => $ltiException instanceof InvalidRequest ? 'invalid_request' : 'access_denied', 'error_description' => $ltiException->getMessage()];

            if (null !== $authentication->state) {
                $fields['state'] = $authentication->state;
            }

            $post = new FormPost($redirect, $fields);
        }

        return $this->responder->respond($post);
    }

    public function deepLinking(Request $request): Response
    {
        $token = $request->request->get('JWT');

        if (!\is_string($token)) {
            return $this->text('A deep linking response carries its JWT.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->deepLinking->read($token);
        } catch (LtiException $ltiException) {
            return $this->text('The deep linking response is refused: '.$ltiException->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $event = $this->handler->handle($result);

        return $event->response() ?? new Response($this->twig->render('@PhilLti/deep_linking_returned.html.twig', ['result' => $result]), Response::HTTP_OK, ['Cache-Control' => 'no-store']);
    }

    private function text(string $message, int $status): Response
    {
        return new Response($message, $status, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
