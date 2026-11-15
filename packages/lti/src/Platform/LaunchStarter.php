<?php

declare(strict_types=1);

namespace Phil\Lti\Platform;

use Phil\Lti\Contract\ToolRegistrations;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Exception\UnknownRegistration;
use Phil\Lti\Http\UrlQuery;
use Phil\Lti\Registration\ToolRegistration;
use Phil\Url\Url;

/**
 * The first step of a launch on the platform: the address of the tool's login initiation the browser is sent to, with
 * the issuer, the hint of the person, the target of the launch, the platform's own hint, the client and the deployment.
 */
final readonly class LaunchStarter
{
    public function __construct(
        private string $issuer,
        private ToolRegistrations $tools,
        private bool $allowLocalhost = false,
    ) {
    }

    /**
     * @param string      $loginHint   what the platform recognises the person by when the tool sends them back,
     *                                 compared when it does
     * @param string|null $messageHint what the platform recognises the launch by — a resource link, a deep linking
     *                                 placement —, opaque to the tool
     *
     * @throws UnknownRegistration when the tool or the deployment is not registered
     * @throws InvalidRequest      when the target is not on the tool
     */
    public function loginUrl(ToolRegistration $tool, string $deploymentId, string $loginHint, Url $targetLinkUri, ?string $messageHint = null): Url
    {
        if (!$this->tools->find($tool->clientId) instanceof ToolRegistration) {
            throw UnknownRegistration::tool($tool->clientId);
        }

        if (!$this->tools->hasDeployment($tool, $deploymentId)) {
            throw UnknownRegistration::deployment($deploymentId);
        }

        if (!$tool->owns($targetLinkUri)) {
            throw InvalidRequest::because('The target of the launch is not on an origin of the tool.');
        }

        $parameters = [
            'iss' => $this->issuer,
            'login_hint' => $loginHint,
            'target_link_uri' => $targetLinkUri->toString(),
            'client_id' => $tool->clientId,
            'lti_deployment_id' => $deploymentId,
        ];

        if (null !== $messageHint) {
            $parameters['lti_message_hint'] = $messageHint;
        }

        return UrlQuery::with($tool->loginUrl, $parameters, $this->allowLocalhost);
    }
}
