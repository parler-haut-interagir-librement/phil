<?php

declare(strict_types=1);

namespace Phil\Lti\Platform;

use Phil\Lti\Contract\KeyStore;
use Phil\Lti\Contract\ToolRegistrations;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Http\FormPost;
use Phil\Lti\Jwt\JwtSigner;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;

/**
 * The second step of a launch on the platform: answers the tool's authentication request with the `id_token` — the
 * message signed with the platform's key, `iss` the platform, `aud` and `azp` the client, `sub` the person, the
 * request's `nonce`, five minutes of life — posted by the browser to the redirect address with the `state`.
 *
 * The client must be registered; the redirect address one of its own, compared whole; the login hint the one the
 * platform gave for the person signed in; the deployment of the message one of the tool's.
 */
final readonly class LaunchIssuer
{
    public const int LIFETIME = 300;

    public function __construct(
        private string $issuer,
        private ToolRegistrations $tools,
        private KeyStore $keys,
        private ClockInterface $clock,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * The tool and the redirect address of a request, checked: what the platform reads before it builds the message.
     *
     * @return array{ToolRegistration, Url}
     *
     * @throws UnknownRegistration|InvalidRequest
     */
    public function check(AuthenticationRequest $request): array
    {
        $tool = $this->tools->find($request->clientId) ?? throw UnknownRegistration::tool($request->clientId);

        try {
            $redirect = Url::fromString($request->redirectUri, $this->allowLocalhost);
        } catch (UrlException) {
            throw InvalidRequest::because('The redirect address is not a web address.');
        }

        if (!$tool->allowsRedirect($redirect)) {
            throw InvalidRequest::because('The redirect address is not registered for the tool.');
        }

        return [$tool, $redirect];
    }

    /**
     * @param string $loginHint the login hint the platform gave when it started the launch for the person signed in
     *
     * @throws UnknownRegistration|InvalidRequest
     */
    public function authorize(AuthenticationRequest $request, string $loginHint, LaunchMessage $message): FormPost
    {
        [$tool, $redirect] = $this->check($request);

        if (!hash_equals($loginHint, $request->loginHint)) {
            throw InvalidRequest::because('The login hint is not the one of the person signed in.');
        }

        if (!$this->tools->hasDeployment($tool, $message->deploymentId)) {
            throw UnknownRegistration::deployment($message->deploymentId);
        }

        if (null === $message->person->subject && null !== $message->person->name) {
            throw InvalidRequest::because('An anonymous launch carries nothing of the person.');
        }

        $now = $this->clock->now()->getTimestamp();
        $claims = [
            'iss' => $this->issuer,
            'aud' => $tool->clientId,
            'azp' => $tool->clientId,
            'iat' => $now,
            'exp' => $now + self::LIFETIME,
            'nonce' => $request->nonce,
        ] + $message->toClaims();

        $fields = ['id_token' => JwtSigner::sign($claims, $this->keys->signingKey())];

        if (null !== $request->state) {
            $fields['state'] = $request->state;
        }

        return new FormPost($redirect, $fields);
    }
}
