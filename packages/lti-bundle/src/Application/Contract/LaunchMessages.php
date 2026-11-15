<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Application\Contract;

use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Registration\ToolRegistration;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What the platform launches, as the application decides it: the application implements it when the platform side is
 * enabled. The bundle checks the request, the tool and the redirect address before it asks, and signs the message.
 */
interface LaunchMessages
{
    /**
     * The login hint the platform gives a person when it starts a launch, compared when the tool sends them back: an
     * opaque identifier of the account, never its email.
     */
    public function loginHint(UserInterface $user): string;

    /**
     * The message of the launch the request continues — its resource link, context, roles and claims —, found by the
     * request's `lti_message_hint`, for the person signed in.
     *
     * @throws InvalidRequest when the hint names nothing the person may launch
     */
    public function message(AuthenticationRequest $request, ToolRegistration $tool, UserInterface $user): LaunchMessage;
}
