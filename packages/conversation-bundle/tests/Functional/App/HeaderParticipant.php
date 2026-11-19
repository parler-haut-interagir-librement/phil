<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Functional\App;

use Override;
use Phil\ConversationBundle\Application\Contract\CurrentParticipant;
use Phil\ConversationBundle\Domain\ValueObject\ParticipantId;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The participant the request names in its `X-User` header, for the tests.
 */
final readonly class HeaderParticipant implements CurrentParticipant
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): ?ParticipantId
    {
        $user = $this->requests->getMainRequest()?->headers->get('X-User');

        return null === $user ? null : ParticipantId::fromString($user);
    }
}
