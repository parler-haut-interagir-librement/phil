<?php

declare(strict_types=1);

namespace Phil\Lti\Tool;

use Phil\Lti\Contract\LoginStates;
use Phil\Lti\Contract\PlatformRegistrations;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Http\UrlQuery;
use Phil\Lti\Security\LoginState;
use Phil\Lti\Security\Random;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;

/**
 * The first step of a launch on the tool: answers the platform's login initiation with an OpenID Connect authentication
 * request — `scope=openid`, `response_type=id_token`, `response_mode=form_post`, `prompt=none` —, a fresh `state` and
 * `nonce` remembered for the launch, for a few minutes.
 *
 * The platform must be registered, and its deployment when it names one; the target of the launch must be on the
 * origin of the tool's launch address, so that a forged initiation cannot send the person elsewhere.
 */
final readonly class LoginInitiator
{
    public const int DEFAULT_STATE_LIFETIME = 600;

    public function __construct(
        private PlatformRegistrations $platforms,
        private LoginStates $states,
        private ClockInterface $clock,
        private int $stateLifetime = self::DEFAULT_STATE_LIFETIME,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @param Url $redirectUrl the launch address of the tool, registered with the platform
     *
     * @throws InvalidRequest      when the target of the launch is not on the tool
     * @throws UnknownRegistration when the platform or its deployment is not registered
     */
    public function initiate(LoginRequest $request, Url $redirectUrl): LoginRedirect
    {
        $platform = $this->platforms->find($request->issuer, $request->clientId) ?? throw UnknownRegistration::platform($request->issuer, $request->clientId);

        if (null !== $request->deploymentId && !$this->platforms->hasDeployment($platform, $request->deploymentId)) {
            throw UnknownRegistration::deployment($request->deploymentId);
        }

        try {
            $target = Url::fromString($request->targetLinkUri, $this->allowLocalhost);
        } catch (UrlException) {
            throw InvalidRequest::because('The target of the launch is not a web address.');
        }

        if ($target->scheme !== $redirectUrl->scheme || $target->host !== $redirectUrl->host || $target->port !== $redirectUrl->port) {
            throw InvalidRequest::because('The target of the launch is not on the origin of the tool.');
        }

        $state = new LoginState(Random::token(), Random::token(), $platform->issuer, $platform->clientId, $target->toString(), $this->clock->now()->modify(\sprintf('+%d seconds', $this->stateLifetime)));
        $this->states->save($state);

        $parameters = [
            'scope' => 'openid',
            'response_type' => 'id_token',
            'response_mode' => 'form_post',
            'prompt' => 'none',
            'client_id' => $platform->clientId,
            'redirect_uri' => $redirectUrl->toString(),
            'login_hint' => $request->loginHint,
            'state' => $state->state,
            'nonce' => $state->nonce,
        ];

        if (null !== $request->messageHint) {
            $parameters['lti_message_hint'] = $request->messageHint;
        }

        return new LoginRedirect(UrlQuery::with($platform->authorizationUrl, $parameters, $this->allowLocalhost), $state);
    }
}
