<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Tests\Functional\App;

use Override;
use Phil\Lti\Exception\InvalidRequest;
use Phil\Lti\Message\Context;
use Phil\Lti\Message\ContextRole;
use Phil\Lti\Message\LaunchMessage;
use Phil\Lti\Message\MessageType;
use Phil\Lti\Message\Person;
use Phil\Lti\Message\ResourceLink;
use Phil\Lti\Message\Roles;
use Phil\Lti\Platform\AuthenticationRequest;
use Phil\Lti\Registration\ToolRegistration;
use Phil\LtiBundle\Application\Contract\LaunchMessages;
use Phil\LtiBundle\Infrastructure\Routing\PlatformUrls;
use Phil\Url\Url;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The launches of the test platform: the message hint names a resource link of the course `course-1`.
 */
final readonly class CourseLaunches implements LaunchMessages
{
    public function __construct(
        private PlatformUrls $urls,
    ) {
    }

    #[Override]
    public function loginHint(UserInterface $user): string
    {
        return 'hint-'.$user->getUserIdentifier();
    }

    #[Override]
    public function message(AuthenticationRequest $request, ToolRegistration $tool, UserInterface $user): LaunchMessage
    {
        if ('link-1' !== $request->messageHint) {
            throw InvalidRequest::because('The resource link is unknown.');
        }

        return new LaunchMessage(
            MessageType::ResourceLink,
            '1',
            Url::fromString('https://localhost/course/1', true),
            new Person('sub-'.$user->getUserIdentifier()),
            Roles::of([ContextRole::Learner]),
            new ResourceLink('link-1', 'Week 1'),
            new Context('course-1', 'CS101'),
            ags: $this->urls->agsEndpoint($tool, 'course-1'),
            nrps: $this->urls->nrpsEndpoint('course-1'),
        );
    }
}
