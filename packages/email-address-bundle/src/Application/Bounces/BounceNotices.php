<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Application\Bounces;

use Phil\BounceParser\BounceAction;
use Phil\BounceParser\BounceReport;
use Phil\BounceParser\Verp;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Domain\ValueObject\BounceNotice;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\Clock\NativeClock;

/**
 * Turns what the bounce parser read, or what a provider's webhook said, into the notices the bounce rules decide.
 *
 * Of a report, the action the parser or the application's parser rules chose decides the kind: a hard bounce or a
 * suppression is hard, a soft bounce soft, an unsubscription a complaint — authenticated only when the parser's
 * authenticator vouched for the abuse report —, a review a review; an ignored report gives no notice. The recipient
 * identifier is read from the return path when it carries a tag signed with the VERP key.
 */
final readonly class BounceNotices
{
    private ClockInterface $clock;

    /**
     * @param ClockInterface|null $clock the clock that dates the notices; null reads the system's
     */
    public function __construct(
        private EmailAddresses $addresses,
        private ?Verp $verp = null,
        #[SensitiveParameter]
        private string $verpKey = '',
        ?ClockInterface $clock = null,
    ) {
        $this->clock = $clock ?? new NativeClock();
    }

    /**
     * @param string $reportKey what names the report: a hash of the raw message
     *
     * @return list<BounceNotice> one per address the report speaks of, one without an address when it names none
     */
    public function fromReport(BounceReport $report, string $reportKey, NoticeSource $source): array
    {
        $kind = match ($report->action) {
            BounceAction::Ignore => null,
            BounceAction::CountHard, BounceAction::Suppress => NoticeKind::Hard,
            BounceAction::CountSoft => NoticeKind::Soft,
            BounceAction::Unsubscribe => NoticeKind::Complaint,
            BounceAction::Review => NoticeKind::Review,
        };

        if (null === $kind) {
            return [];
        }

        $recipientId = $this->verp instanceof Verp && '' !== $this->verpKey ? $this->verp->decodeSigned($report->verpTag, $this->verpKey) : null;
        $now = $this->clock->now();
        $notices = [];

        foreach ($report->addresses() as $address) {
            $notices[] = new BounceNotice($kind, $source, $reportKey, $address, $this->addresses->fingerprint($address), $report->authentic, $now, $recipientId);
        }

        return [] === $notices ? [new BounceNotice($kind, $source, $reportKey, null, null, $report->authentic, $now, $recipientId)] : $notices;
    }

    /**
     * A notice of a provider's webhook, whose signature the Webhook component checked: its sender is authenticated.
     *
     * @param string $eventId the provider's identifier of the event, the same when it sends it again
     */
    public function fromWebhook(NoticeKind $kind, #[SensitiveParameter] string $address, string $eventId, ?string $recipientId = null): BounceNotice
    {
        return new BounceNotice($kind, NoticeSource::Webhook, 'webhook:'.$eventId, $address, $this->addresses->fingerprint($address), true, $this->clock->now(), $recipientId);
    }
}
