<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Controller;

use Phil\SamlSp\Binding\HttpPost;
use Phil\SamlSp\Binding\HttpRedirect;
use Phil\SamlSp\Binding\Outbound;
use Phil\SamlSp\Exception\SamlException;
use Phil\SamlSp\Message\LogoutRequest;
use Phil\SamlSp\Message\ReceivedMessage;
use Phil\SamlSp\Metadata\ServiceProviderMetadata;
use Phil\SamlSp\Provider\SecurityPolicy;
use Phil\SamlSpBundle\Application\Event\SamlResponseRefused;
use Phil\SamlSpBundle\Application\IdentityProviders;
use Phil\SamlSpBundle\Application\SamlLogin;
use Phil\SamlSpBundle\Application\SamlLogout;
use Phil\SamlSpBundle\Infrastructure\Http\OutboundResponder;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAttributes;
use Phil\SamlSpBundle\Infrastructure\Security\SamlAuthenticator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * The routes of each identity provider: the metadata of the service provider, the start of a sign-in, the assertion
 * consumer service — which only answers when the firewall does not run the authenticator — and the logout service.
 */
final readonly class SamlController
{
    public function __construct(
        private IdentityProviders $providers,
        private SamlLogin $login,
        private SamlLogout $logout,
        private EventDispatcherInterface $events,
        private SecurityPolicy $policy,
        private ?TokenStorageInterface $tokens = null,
        private bool $bindToBrowser = true,
        private bool $secureCookie = true,
        private int $requestLifetime = 600,
    ) {
    }

    public function metadata(string $idp): Response
    {
        $this->known($idp);
        $settings = $this->providers->settings($idp);
        $xml = ServiceProviderMetadata::xml($this->providers->serviceProvider($idp), $settings->wantAssertionsSigned);

        return new Response($xml, Response::HTTP_OK, ['Content-Type' => 'application/samlmetadata+xml', 'Content-Disposition' => 'inline; filename="'.$idp.'-metadata.xml"']);
    }

    public function login(string $idp, Request $request): Response
    {
        $this->known($idp);
        $target = $request->query->all()['target'] ?? null;

        try {
            $start = $this->login->start($idp, \is_string($target) ? $target : null, $this->bindToBrowser);
        } catch (SamlException $samlException) {
            return $this->refused($idp, $samlException, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $response = OutboundResponder::respond($start->outbound);

        if (null !== $start->browserToken) {
            $response->headers->setCookie(Cookie::create(SamlAuthenticator::COOKIE, $start->browserToken, time() + $this->requestLifetime, '/', null, $this->secureCookie, true, false, $this->secureCookie ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX));
        }

        return $response;
    }

    public function assertionConsumerService(string $idp): Response
    {
        $this->known($idp);

        return new Response('The firewall of this address does not run the SAML authenticator of phil/saml-sp-bundle.', Response::HTTP_BAD_REQUEST, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function singleLogout(string $idp, Request $request): Response
    {
        $this->known($idp);

        try {
            $outcome = $this->logout->receive($idp, $this->received($idp, $request));
        } catch (SamlException $samlException) {
            return $this->refused($idp, $samlException, Response::HTTP_BAD_REQUEST);
        }

        if ($outcome->request instanceof LogoutRequest) {
            $this->endSession($idp, $outcome->request->nameId, $request);
        }

        return $outcome->response instanceof Outbound ? OutboundResponder::respond($outcome->response) : new RedirectResponse($outcome->target ?? '/', Response::HTTP_SEE_OTHER);
    }

    private function received(string $idp, Request $request): ReceivedMessage
    {
        if ($request->isMethod('GET')) {
            return HttpRedirect::receive($request->server->getString('QUERY_STRING'), $this->providers->identityProvider($idp)->metadata->signingCertificates, $this->policy->signatures, $this->policy->budget);
        }

        $fields = $request->request->all();
        $parameter = isset($fields['SAMLRequest']) ? 'SAMLRequest' : 'SAMLResponse';
        $relayState = $fields['RelayState'] ?? null;
        $field = $fields[$parameter] ?? null;

        return HttpPost::receive(\is_string($field) ? $field : '', \is_string($relayState) ? $relayState : null, $this->policy->budget, $parameter);
    }

    /**
     * Ends the session of this browser when it is the person the identity provider signs out.
     */
    private function endSession(string $idp, string $nameId, Request $request): void
    {
        $token = $this->tokens?->getToken();

        if (!$token instanceof TokenInterface || !$token->hasAttribute(SamlAttributes::IDENTITY_PROVIDER) || $idp !== $token->getAttribute(SamlAttributes::IDENTITY_PROVIDER) || $nameId !== $token->getAttribute(SamlAttributes::NAME_ID)) {
            return;
        }

        $this->tokens->setToken(null);

        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }
    }

    private function refused(string $idp, SamlException $exception, int $status): Response
    {
        $this->events->dispatch(new SamlResponseRefused($idp, $exception->getMessage(), $exception::class));

        return new Response('The identity provider cannot be reached or its message was refused.', $status, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function known(string $idp): void
    {
        if (!$this->providers->has($idp)) {
            throw new NotFoundHttpException(\sprintf('No identity provider is configured under "%s".', $idp));
        }
    }
}
