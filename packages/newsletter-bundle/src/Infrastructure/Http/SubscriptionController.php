<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Http;

use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\NewsletterBundle\Application\Exception\LinkRefused;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Exception\SubscriptionRefused;
use Phil\NewsletterBundle\Application\SignedLinks;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Domain\Contract\MailingListRepository;
use Phil\NewsletterBundle\Domain\Contract\SubscriberRepository;
use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;
use Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\MailingList;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Infrastructure\RateLimiter\SubscriptionRateLimiter;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The public pages of the newsletter: the subscription form, the confirmation of a double opt-in, the unsubscription —
 * one click from the mail client's button, RFC 8058 —, and the preference center, where a subscriber chooses their
 * lists, pauses, comes back or leaves.
 *
 * A page reached by a link of a message reads its signed token; a link that is not the newsletter's, or expired, gets a
 * `404`. Following a link never changes anything by itself — mail scanners follow links — : a `POST` from the page, or
 * the mail client's one-click `POST`, does. The subscription form gives the same answer whatever became of the address,
 * so that nobody learns from it who is subscribed; with the limiter, a client or an address that posts too often gets a
 * `429`, counted whether the address is subscribed or not.
 */
final readonly class SubscriptionController
{
    public const string SUBSCRIBE_TOKEN = 'phil_newsletter_subscribe';

    public const string PREFERENCES_TOKEN = 'phil_newsletter_preferences';

    /** The pauses the preference center offers, in weeks. */
    public const array PAUSES = [1, 4, 12];

    public function __construct(
        private Subscriptions $subscriptions,
        private MailingListRepository $lists,
        private SubscriberRepository $subscribers,
        private SignedLinks $links,
        private CurrentSite $site,
        private CsrfTokenManagerInterface $csrf,
        private Environment $twig,
        private ClockInterface $clock,
        private ?SubscriptionRateLimiter $limiter = null,
    ) {
    }

    public function subscribe(Request $request, string $list): Response
    {
        $mailingList = $this->lists->byKey($this->site->site(), $list);

        if (!$mailingList instanceof MailingList) {
            return $this->page('missing', [], 404);
        }

        if (!$request->isMethod('POST')) {
            return $this->page('subscribe', ['list' => $mailingList]);
        }

        if (!$this->validToken(self::SUBSCRIBE_TOKEN, $request)) {
            return $this->page('subscribe', ['list' => $mailingList, 'error' => 'expired'], 400);
        }

        if ('' !== (string) $request->request->get('website', '')) {
            return $this->page('subscribe', ['list' => $mailingList, 'sent' => true]);
        }

        $email = (string) $request->request->get('email', '');

        if ($this->limiter instanceof SubscriptionRateLimiter && !$this->limiter->allowsSubscription($request, $mailingList->site->key, $email)) {
            return $this->page('subscribe', ['list' => $mailingList, 'error' => 'too_many'], 429);
        }

        try {
            $this->subscriptions->subscribe($mailingList, $email);
        } catch (SubscriptionRefused $subscriptionRefused) {
            return $this->page('subscribe', ['list' => $mailingList, 'error' => 'address', 'suggestion' => $subscriptionRefused->suggestion], 422);
        }

        return $this->page('subscribe', ['list' => $mailingList, 'sent' => true]);
    }

    public function confirm(Request $request, string $token): Response
    {
        try {
            $link = $this->links->verify($token, LinkPurpose::Confirm);
        } catch (LinkRefused) {
            return $this->page('missing', [], 404);
        }

        if (!$request->isMethod('POST')) {
            return $this->page('confirm', ['token' => $token]);
        }

        try {
            $this->subscriptions->confirm($link->subject, $link->extra);
        } catch (NotFound) {
            return $this->page('missing', [], 404);
        }

        return $this->page('confirm', ['token' => $token, 'done' => true]);
    }

    public function unsubscribe(Request $request, string $token): Response
    {
        try {
            $link = $this->links->verify($token, LinkPurpose::Unsubscribe);
        } catch (LinkRefused) {
            return $this->page('missing', [], 404);
        }

        $listId = '' === $link->extra ? null : $link->extra;

        if ('One-Click' === $request->request->get('List-Unsubscribe')) {
            $this->leave($link->subject, $listId, null);

            return new Response('', Response::HTTP_OK);
        }

        if (!$request->isMethod('POST')) {
            return $this->page('unsubscribe', ['token' => $token, 'reasons' => UnsubscribeReason::cases(), 'list' => null === $listId ? null : $this->lists->get($listId)]);
        }

        $everything = 'all' === $request->request->get('scope');
        $this->leave($link->subject, $everything ? null : $listId, UnsubscribeReason::tryFrom((string) $request->request->get('reason', '')));

        return $this->page('unsubscribe', ['token' => $token, 'done' => true]);
    }

    public function preferences(Request $request, string $token): Response
    {
        try {
            $link = $this->links->verify($token, LinkPurpose::Preferences);
        } catch (LinkRefused) {
            return $this->page('missing', [], 404);
        }

        $subscriber = $this->subscribers->get($link->subject);

        if (!$subscriber instanceof Subscriber) {
            return $this->page('missing', [], 404);
        }

        $saved = false;

        if ($request->isMethod('POST')) {
            if (!$this->validToken(self::PREFERENCES_TOKEN, $request)) {
                return $this->preferencesPage($token, $subscriber, false, 400);
            }

            $saved = $this->change($subscriber, $request);
            $subscriber = $this->subscribers->get($link->subject) ?? $subscriber;
        }

        return $this->preferencesPage($token, $subscriber, $saved);
    }

    private function change(Subscriber $subscriber, Request $request): bool
    {
        $action = (string) $request->request->get('action', '');

        try {
            match ($action) {
                'pause' => $this->subscriptions->pause($subscriber->id, $this->clock->now()->modify(\sprintf('+%d weeks', $this->weeks($request)))),
                'resume' => $this->subscriptions->resume($subscriber->id),
                'leave' => $this->subscriptions->leave($subscriber->id, null, UnsubscribeReason::tryFrom((string) $request->request->get('reason', ''))),
                default => $this->choose($subscriber, $request),
            };
        } catch (InvalidNewsletterValue) {
            return false;
        }

        return true;
    }

    /**
     * Joins the lists chosen first, then leaves the others, so that a subscriber who swaps their only list for another
     * is never left with none, which would unsubscribe them.
     */
    private function choose(Subscriber $subscriber, Request $request): void
    {
        $chosen = $request->request->all('lists');
        $leave = [];

        foreach ($this->lists->ofSite($subscriber->site) as $list) {
            $membership = $this->subscribers->membership($subscriber->id, $list->id);
            $active = $membership instanceof Membership && $membership->isActive();
            $wanted = \in_array($list->key, $chosen, true);

            if ($wanted && !$active) {
                $this->subscriptions->join($subscriber->id, $list);
            } elseif (!$wanted && $active) {
                $leave[] = $list->id;
            }
        }

        foreach ($leave as $listId) {
            $this->subscriptions->leave($subscriber->id, $listId);
        }
    }

    private function weeks(Request $request): int
    {
        $weeks = (int) $request->request->get('weeks', 4);

        return \in_array($weeks, self::PAUSES, true) ? $weeks : 4;
    }

    private function leave(string $subscriberId, ?string $listId, ?UnsubscribeReason $reason): void
    {
        try {
            $this->subscriptions->leave($subscriberId, $listId, $reason);
        } catch (NotFound) {
            // Already forgotten: leaving is done.
        }
    }

    private function preferencesPage(string $token, Subscriber $subscriber, bool $saved, int $status = 200): Response
    {
        $lists = [];

        foreach ($this->lists->ofSite($subscriber->site) as $list) {
            $membership = $this->subscribers->membership($subscriber->id, $list->id);
            $lists[] = ['list' => $list, 'active' => $membership instanceof Membership && $membership->isActive()];
        }

        return $this->page('preferences', ['token' => $token, 'subscriber' => $subscriber, 'lists' => $lists, 'pauses' => self::PAUSES, 'reasons' => UnsubscribeReason::cases(), 'saved' => $saved], $status);
    }

    private function validToken(string $id, Request $request): bool
    {
        return $this->csrf->isTokenValid(new CsrfToken($id, (string) $request->request->get('_token', '')));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function page(string $name, array $context, int $status = 200): Response
    {
        $response = new Response($this->twig->render('@PhilNewsletter/pages/'.$name.'.html.twig', $context), $status);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
