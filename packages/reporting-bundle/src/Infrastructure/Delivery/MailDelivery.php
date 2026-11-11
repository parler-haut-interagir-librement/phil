<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Delivery;

use Override;
use Phil\ReportingBundle\Application\Contract\ReportDelivery;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * The file attached to an e-mail to each recipient, sent by the mailer's transports from the worker that exported
 * it: the attachment never enters a queue. The file is read once, whole — an e-mail carries a few megabytes at most —, and
 * attached under its media type without parameters. The recipients are e-mail addresses, each sent its own message.
 */
final readonly class MailDelivery implements ReportDelivery
{
    public function __construct(
        private TransportInterface $transport,
        private Reports $reports,
        private string $from,
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'mail';
    }

    #[Override]
    public function deliver(ReportJob $job, StoredExport $export, ReportStream $content): void
    {
        $title = $this->reports->definition($job->report)->title();
        $period = $job->request->period;
        $text = \sprintf(
            "%s, %s to %s: %d rows, attached as %s.\n",
            $title,
            $period->start->format('Y-m-d'),
            $period->end->modify('-1 second')->format('Y-m-d'),
            $export->rows,
            $export->filename,
        );

        $attachment = $content->contents();
        $mediaType = explode(';', $export->mediaType)[0];

        foreach ($job->recipients as $recipient) {
            $this->transport->send(new Email()
                ->from(Address::create($this->from))
                ->to(Address::create($recipient))
                ->subject($title)
                ->text($text)
                ->attach($attachment, $export->filename, $mediaType));
        }
    }
}
