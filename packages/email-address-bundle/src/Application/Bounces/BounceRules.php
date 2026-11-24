<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Bounces;

use DateInterval;
use InvalidArgumentException;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Contract\BounceHistoryRepository;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\NativeClock;
use Throwable;

/**
 * Decides what a bounce notice does to its address, then dispatches the decision as a `BounceDecided` event:
 *
 * - a hard bounce suspends the address;
 * - soft bounces are counted within a sliding window, and the one that reaches the threshold suspends it;
 * - a complaint or a request to leave unsubscribes it when its sender was authenticated, and goes to review otherwise:
 *   anyone can write an abuse report to the bounce mailbox;
 * - a notice that names neither an address nor a recipient, or that asks for review, goes to review.
 *
 * Each notice is decided once: the same report received again — a provider that retries its webhook, a message
 * piped twice — is a `Duplicate`, and nothing is dispatched. The rules keep no state of their own between calls:
 * the history repository does.
 */
final readonly class BounceRules
{
    private DateInterval $window;

    private ClockInterface $clock;

    /**
     * @param ClockInterface|null $clock               the clock the window is counted on; null reads the system's
     * @param int                 $softBounceThreshold the soft bounces within the window that suspend the address, at least 1
     * @param string              $softBounceWindow    the window, an ISO 8601 duration: `P30D`
     *
     * @throws InvalidArgumentException when the threshold is below one or the window is no positive duration
     */
    public function __construct(
        private BounceHistoryRepository $history,
        private EventDispatcherInterface $dispatcher,
        ?ClockInterface $clock = null,
        private int $softBounceThreshold = 3,
        string $softBounceWindow = 'P30D',
    ) {
        if ($softBounceThreshold < 1) {
            throw new InvalidArgumentException('The soft bounce threshold is at least 1.');
        }

        try {
            $window = new DateInterval($softBounceWindow);
        } catch (Throwable) {
            throw new InvalidArgumentException(\sprintf('The soft bounce window "%s" is not an ISO 8601 duration such as P30D.', $softBounceWindow));
        }

        $this->clock = $clock ?? new NativeClock();
        $now = $this->clock->now();

        if ($now->add($window) <= $now) {
            throw new InvalidArgumentException(\sprintf('The soft bounce window "%s" is no positive duration.', $softBounceWindow));
        }

        $this->window = $window;
    }

    public function apply(BounceNotice $notice): BounceDecision
    {
        $now = $this->clock->now();

        if (!$this->history->claim($notice->key(), $now->add($this->window))) {
            return BounceDecision::Duplicate;
        }

        $subject = $notice->subject();
        $softBounces = 0;

        if (null === $subject) {
            $decision = BounceDecision::Review;
        } else {
            $decision = match ($notice->kind) {
                NoticeKind::Hard => BounceDecision::Suspend,
                NoticeKind::Soft => ($softBounces = $this->history->recordSoftBounce($subject, $now, $now->sub($this->window))) >= $this->softBounceThreshold ? BounceDecision::Suspend : BounceDecision::Count,
                NoticeKind::Complaint, NoticeKind::Unsubscribe => $notice->authenticated ? BounceDecision::Unsubscribe : BounceDecision::Review,
                NoticeKind::Review => BounceDecision::Review,
            };
        }

        if (null !== $subject && \in_array($decision, [BounceDecision::Suspend, BounceDecision::Unsubscribe], true)) {
            $this->history->forgetSoftBounces($subject);
        }

        $this->dispatcher->dispatch(new BounceDecided($notice, $decision, $softBounces));

        return $decision;
    }
}
