<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Infrastructure\Http;

use Closure;
use Phil\FollowBundle\Application\Exception\FollowRefused;
use Phil\FollowBundle\Application\Exception\UnknownFollowRequest;
use Phil\FollowBundle\Application\Followers;
use Phil\FollowBundle\Application\FollowRequests;
use Phil\FollowBundle\Application\Follows;
use Phil\FollowBundle\Application\Mutuals;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Phil\FollowBundle\Domain\Identifier;
use Phil\FollowBundle\Domain\Status\FollowLevel;
use Phil\FollowBundle\Domain\ValueObject\EventPreferences;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The forms of the bundle, which work without JavaScript: each posts to a signed address with the stateless CSRF token,
 * changes what the current member asked for, and comes back to its page; a page that says what stopped it otherwise.
 *
 * - `level`: follow a subject at a level, change the level, or stop following (`none`);
 * - `request`: accept, reject or withdraw a request;
 * - `visibility`: show or hide one's followers;
 * - `mutual`: ask for, or end, a mutual relationship.
 */
final readonly class FollowController
{
    public const string DOMAIN = 'phil_follow';

    public function __construct(
        private Follows $follows,
        private FollowRequests $requests,
        private Followers $followers,
        private Mutuals $mutuals,
        private CurrentRecipient $member,
        private FollowGuard $guard,
        private Environment $twig,
        private TranslatorInterface $translator,
    ) {
    }

    public function level(Request $request): Response
    {
        return $this->handle($request, function (string $member) use ($request): void {
            $subject = Identifier::parseSubject($request->query->getString('subject'));
            $level = $request->request->getString('level');

            if ('none' === $level) {
                $this->follows->unfollow($member, $subject);

                return;
            }

            $events = $request->request->all('events');
            $this->follows->follow(
                $member,
                $subject,
                FollowLevel::tryFrom($level) ?? throw InvalidFollow::because('The level is "watching", "mentions", "ignoring", "custom" or "none".'),
                EventPreferences::fromArray(array_filter($events, static fn (mixed $choice): bool => '' !== $choice)),
            );
        });
    }

    public function request(Request $request): Response
    {
        return $this->handle($request, function (string $member) use ($request): void {
            $id = $request->request->getString('request');

            match ($request->request->getString('decision')) {
                'accept' => $this->requests->accept($member, $id),
                'reject' => $this->requests->reject($member, $id),
                'withdraw' => $this->requests->withdraw($member, $id),
                default => throw InvalidFollow::because('The decision is "accept", "reject" or "withdraw".'),
            };
        });
    }

    public function visibility(Request $request): Response
    {
        return $this->handle($request, function (string $member) use ($request): void {
            $this->followers->hideFollowers($member, '1' === $request->request->getString('hidden'));
        });
    }

    public function mutual(Request $request): Response
    {
        return $this->handle($request, function (string $member) use ($request): void {
            $other = Identifier::id($request->query->getString('member'), 'member');

            match ($request->request->getString('action')) {
                'request' => $this->mutuals->request($member, $other),
                'end' => $this->mutuals->end($member, $other),
                default => throw InvalidFollow::because('The action is "request" or "end".'),
            };
        });
    }

    /**
     * @param Closure(string): void $change
     */
    private function handle(Request $request, Closure $change): Response
    {
        if (!$this->guard->isSigned($request)) {
            return $this->stopped('follow.refused', '/', Response::HTTP_FORBIDDEN);
        }

        $back = $request->query->getString('back', '/');

        try {
            $this->guard->assertTrusted($request->request->getString('_token'));
            $change($this->member->id() ?? throw FollowRefused::noMember());

            return new RedirectResponse($back, Response::HTTP_SEE_OTHER);
        } catch (FollowRefused) {
            return $this->stopped('follow.refused', $back, Response::HTTP_FORBIDDEN);
        } catch (UnknownFollowRequest) {
            return $this->stopped('follow.unknown_request', $back, Response::HTTP_NOT_FOUND);
        } catch (InvalidFollow) {
            return $this->stopped('follow.invalid', $back, Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    private function stopped(string $message, string $back, int $status): Response
    {
        return new Response($this->twig->render('@PhilFollow/stopped.html.twig', [
            'message' => $this->translator->trans($message, [], self::DOMAIN),
            'back' => $back,
        ]), $status);
    }
}
