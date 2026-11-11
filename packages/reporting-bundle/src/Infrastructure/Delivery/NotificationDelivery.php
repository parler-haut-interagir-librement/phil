<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Delivery;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportDelivery;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;

/**
 * The report announced on the channels of Notifier — chat, SMS, the browser — with its title, period and number of
 * rows; no channel attaches the file, which the export store keeps when `keep_delivered` is on. The recipients are
 * e-mail addresses, or nothing for the chat channels, which need none.
 */
final readonly class NotificationDelivery implements ReportDelivery
{
    /**
     * @param list<string> $channels
     */
    public function __construct(
        private NotifierInterface $notifier,
        private Reports $reports,
        private array $channels = [],
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'notification';
    }

    #[Override]
    public function deliver(ReportJob $job, StoredExport $export, ReportStream $content): void
    {
        $period = $job->request->period;
        $notification = new Notification($this->reports->definition($job->report)->title(), $this->channels)
            ->content(\sprintf('%s to %s: %d rows, %s.', $period->start->format('Y-m-d'), $period->end->modify('-1 second')->format('Y-m-d'), $export->rows, $export->filename));
        $recipients = array_map(static fn (string $email): Recipient => new Recipient($email), $job->recipients);

        $this->notifier->send($notification, ...$recipients);
    }
}
