<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Twig;

use DateTimeImmutable;
use Phil\FollowBundle\Application\Followers;
use Phil\FollowBundle\Application\FollowRequests;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Model\FollowRequest;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Infrastructure\Http\FollowGuard;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Phil\NotificationsBundle\Application\Contract\RecipientDirectory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

/**
 * What the bundle's templates read, for the current member, each a plain array: the state of their follow of a
 * subject and the form that changes it, the requests they have to answer, the followers of a subject as they may see
 * them, their choice to hide their own followers, and their mutual relationship with another member. Each form posts
 * to a signed address that comes back to the current page.
 */
final readonly class FollowExtension
{
    private const array LEVELS = [FollowLevel::Watching, FollowLevel::Mentions, FollowLevel::Ignoring];

    /**
     * @param list<string> $events the kinds of event a member chooses a level for at the custom level
     */
    public function __construct(
        private Follows $follows,
        private FollowRequests $requests,
        private Followers $followers,
        private Mutuals $mutuals,
        private CurrentRecipient $member,
        private FollowGuard $guard,
        private RequestStack $requestStack,
        private ?RecipientDirectory $directory = null,
        private array $events = [],
    ) {
    }

    /**
     * @return array{subject: string, level: ?string, inherited: ?string, pending: bool, levels: list<string>, events: list<array{key: string, level: string}>, action: string, token: string}|null
     */
    #[AsTwigFunction(name: 'phil_follow_button')]
    public function button(string $subject): ?array
    {
        $member = $this->member->id();

        if (null === $member) {
            return null;
        }

        $reference = Identifier::parseSubject($subject);
        $state = $this->follows->state($member, $reference);
        $events = $state->own?->events();

        return [
            'subject' => $reference->key(),
            'level' => $state->own?->level()->value,
            'inherited' => $state->inherited?->level()->value,
            'pending' => $state->pending,
            'levels' => array_map(static fn (FollowLevel $level): string => $level->value, self::LEVELS),
            'events' => array_map(static fn (string $event): array => ['key' => $event, 'level' => isset($events->levels[$event]) ? $events->levels[$event]->value : ''], $this->events),
            'action' => $this->guard->action(FollowGuard::LEVEL, ['subject' => $reference->key(), 'back' => $this->back()]),
            'token' => $this->guard->token(),
        ];
    }

    /**
     * @return array{requests: list<array{id: string, kind: string, requester: string, name: string, subject: string, requested_at: DateTimeImmutable}>, action: string, token: string}|null
     */
    #[AsTwigFunction(name: 'phil_follow_requests')]
    public function pending(int $limit = 50): ?array
    {
        $member = $this->member->id();

        if (null === $member) {
            return null;
        }

        return [
            'requests' => array_map(fn (FollowRequest $request): array => [
                'id' => $request->id,
                'kind' => $request->kind->value,
                'requester' => $request->requester,
                'name' => $this->name($request->requester),
                'subject' => $request->subject->key(),
                'requested_at' => $request->requestedAt,
            ], $this->requests->pendingFor($member, null, $limit)),
            'action' => $this->guard->action(FollowGuard::REQUEST, ['back' => $this->back()]),
            'token' => $this->guard->token(),
        ];
    }

    /**
     * @return array{hidden: bool, count: ?int, followers: list<array{id: string, name: string}>}
     */
    #[AsTwigFunction(name: 'phil_follow_followers')]
    public function followersOf(string $subject, int $limit = 50): array
    {
        $list = $this->followers->followersOf(Identifier::parseSubject($subject), $this->member->id(), null, $limit);

        return [
            'hidden' => $list->hidden,
            'count' => $list->count,
            'followers' => array_map(fn (string $member): array => ['id' => $member, 'name' => $this->name($member)], $list->members),
        ];
    }

    /**
     * @return array{hidden: bool, action: string, token: string}|null
     */
    #[AsTwigFunction(name: 'phil_follow_visibility')]
    public function visibility(): ?array
    {
        $member = $this->member->id();

        if (null === $member) {
            return null;
        }

        return [
            'hidden' => $this->followers->followersHidden($member),
            'action' => $this->guard->action(FollowGuard::VISIBILITY, ['back' => $this->back()]),
            'token' => $this->guard->token(),
        ];
    }

    /**
     * @return array{mutual: bool, pending: bool, action: string, token: string}|null
     */
    #[AsTwigFunction(name: 'phil_follow_mutual')]
    public function mutual(string $other): ?array
    {
        $member = $this->member->id();

        if (null === $member || $member === $other || !$this->mutuals->enabled()) {
            return null;
        }

        return [
            'mutual' => $this->mutuals->areMutual($member, $other),
            'pending' => $this->mutuals->pending($member, $other),
            'action' => $this->guard->action(FollowGuard::MUTUAL, ['member' => Identifier::id($other, 'member'), 'back' => $this->back()]),
            'token' => $this->guard->token(),
        ];
    }

    private function name(string $member): string
    {
        return $this->directory?->displayName($member) ?? $member;
    }

    private function back(): string
    {
        $request = $this->requestStack->getMainRequest();

        return $request instanceof Request ? $request->getRequestUri() : '/';
    }
}
