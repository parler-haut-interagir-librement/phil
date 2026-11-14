<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Report\ReportChart;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Infrastructure\Delivery\MailDelivery;
use Phil\ReportingBundle\Infrastructure\Delivery\NotificationDelivery;
use Phil\ReportingBundle\Infrastructure\Format\CsvFormat;
use Phil\ReportingBundle\Tests\Unit\Fixture\ChartedSalesReport;
use Phil\ReportingBundle\Tests\Unit\Fixture\RecordingRasterizer;
use Phil\ReportingBundle\Tests\Unit\Fixture\RecordingTransport;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

#[CoversClass(MailDelivery::class)]
#[CoversClass(NotificationDelivery::class)]
#[CoversClass(ReportChart::class)]
final class DeliveriesTest extends TestCase
{
    public function testTheFileAttachedToAnEmailForEachRecipient(): void
    {
        $transport = new class implements TransportInterface {
            /** @var list<Email> */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                Assert::assertInstanceOf(Email::class, $message);
                // The body is read as the transport would, once per message.
                $message->toString();
                $this->sent[] = $message;

                return null;
            }

            public function __toString(): string
            {
                return 'recording://';
            }
        };
        $job = new ReportJob('sales', 'csv', Reporting::request(), 'mail', ['ada@example.com', 'Grace <grace@example.com>']);
        $content = $this->stream("a,b\r\n");

        new MailDelivery($transport, Reporting::reports(), 'Reports <reports@example.com>')->deliver($job, new StoredExport(str_repeat('a', 32), 'sales-20261001-20261031.csv', 'text/csv; charset=UTF-8', 5, 1), $content);

        self::assertSame('mail', MailDelivery::key());
        self::assertCount(2, $transport->sent);

        foreach ($transport->sent as $i => $email) {
            self::assertSame('"Reports" <reports@example.com>', $email->getFrom()[0]->toString());
            self::assertSame([['ada@example.com'], ['grace@example.com']][$i], array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));
            self::assertSame('Sales', $email->getSubject());
            self::assertSame("Sales, 2026-10-01 to 2026-10-31: 1 rows, attached as sales-20261001-20261031.csv.\n", $email->getTextBody());
            $attachment = $email->getAttachments()[0];
            self::assertInstanceOf(DataPart::class, $attachment);
            self::assertSame(['sales-20261001-20261031.csv', 'text/csv', "a,b\r\n"], [$attachment->getFilename(), $attachment->getContentType(), $attachment->getBody()]);
        }
    }

    public function testTheChartOfAReportAsAnImageOfTheEmail(): void
    {
        $transport = $this->transport();
        $rasterizer = new RecordingRasterizer();
        $job = new ReportJob('charted', 'csv', Reporting::request(), 'mail', ['ada@example.com']);
        $export = new StoredExport(str_repeat('a', 32), 'charted.csv', 'text/csv', 2, 1);

        new MailDelivery($transport, $this->reports(new ChartedSalesReport()), 'reports@example.com', new ChartDrawing(rasterizer: $rasterizer), Reporting::sources())->deliver($job, $export, $this->stream('Day'));

        $email = $transport->sent[0];
        self::assertSame("Sales & visits, 2026-10-01 to 2026-10-31: 1 rows, attached as charted.csv.\n", $email->getTextBody());
        self::assertSame('<p>Sales &amp; visits, 2026-10-01 to 2026-10-31: 1 rows, attached as charted.csv.</p><p><img src="cid:chart.png" alt="Visits"></p>', $email->getHtmlBody());
        self::assertSame('chart.png', MailDelivery::CHART_FILENAME);
        self::assertCount(2, $email->getAttachments());
        [$file, $image] = $email->getAttachments();
        self::assertInstanceOf(DataPart::class, $file);
        self::assertSame('charted.csv', $file->getFilename());
        self::assertSame(['chart.png', 'image/png', 'PNG 2', 'inline'], [$image->getFilename(), $image->getContentType(), $image->getBody(), $image->getDisposition()]);
        self::assertStringContainsString('<title>Visits</title>', $rasterizer->drawn[0][0]);
        self::assertStringContainsString('<path ', $rasterizer->drawn[0][0]);

        new MailDelivery($transport, $this->reports(new ChartedSalesReport('Pages <seen>')), 'reports@example.com', new ChartDrawing(rasterizer: $rasterizer), Reporting::sources())->deliver($job, $export, $this->stream('Day'));
        self::assertStringEndsWith('<img src="cid:chart.png" alt="Pages &lt;seen&gt;"></p>', (string) $transport->sent[1]->getHtmlBody());
        self::assertStringContainsString('<title>Pages &lt;seen&gt;</title>', $rasterizer->drawn[1][0]);
    }

    public function testWithoutAPngTheEmailHasItsFileOnly(): void
    {
        $transport = $this->transport();
        $job = new ReportJob('charted', 'csv', Reporting::request(), 'mail', ['ada@example.com']);
        $export = new StoredExport(str_repeat('a', 32), 'charted.csv', 'text/csv', 2, 1);
        $reports = $this->reports(new ChartedSalesReport('', 'nope'));

        foreach ([
            new MailDelivery($transport, $reports, 'reports@example.com'),
            new MailDelivery($transport, $reports, 'reports@example.com', new ChartDrawing(), Reporting::sources()),
            new MailDelivery($transport, $reports, 'reports@example.com', new ChartDrawing(rasterizer: new RecordingRasterizer())),
            new MailDelivery($transport, $this->reports(new ChartedSalesReport()), 'reports@example.com', new ChartDrawing(rasterizer: new RecordingRasterizer(refuses: true)), Reporting::sources()),
        ] as $delivery) {
            $delivery->deliver($job, $export, $this->stream('Day'));
        }

        self::assertCount(4, $transport->sent);

        foreach ($transport->sent as $email) {
            self::assertNull($email->getHtmlBody());
            self::assertCount(1, $email->getAttachments());
        }
    }

    public function testTheReportAnnouncedByNotifier(): void
    {
        $notifier = new class implements NotifierInterface {
            /** @var list<array{Notification, list<RecipientInterface>}> */
            public array $sent = [];

            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->sent[] = [$notification, array_values($recipients)];
            }
        };
        $job = new ReportJob('sales', 'csv', Reporting::request(), 'notification', ['ada@example.com']);
        $export = new StoredExport(str_repeat('a', 32), 'sales-20261001-20261031.csv', 'text/csv', 5, 3);

        new NotificationDelivery($notifier, Reporting::reports(), ['chat/slack'])->deliver($job, $export, $this->stream(''));
        new NotificationDelivery($notifier, Reporting::reports())->deliver(new ReportJob('sales', 'csv', Reporting::request(), 'notification'), $export, $this->stream(''));

        self::assertSame('notification', NotificationDelivery::key());
        [$notification, $recipients] = $notifier->sent[0];
        self::assertSame(['Sales', '2026-10-01 to 2026-10-31: 3 rows, sales-20261001-20261031.csv.', ['chat/slack']], [$notification->getSubject(), $notification->getContent(), $notification->getChannels(new Recipient('ada@example.com'))]);
        self::assertEquals([new Recipient('ada@example.com')], $recipients);
        self::assertSame([], $notifier->sent[1][1]);
        self::assertSame([], $notifier->sent[1][0]->getChannels(new Recipient('ada@example.com')));
    }

    private function transport(): RecordingTransport
    {
        return new RecordingTransport();
    }

    private function reports(ReportDefinition $report): Reports
    {
        return new Reports(
            new ServiceLocator([$report::key() => static fn (): ReportDefinition => $report]),
            new ServiceLocator(['csv' => static fn (): CsvFormat => new CsvFormat()]),
            100,
        );
    }

    private function stream(string $content): ReportStream
    {
        $stream = ReportStream::temporary();
        $stream->write($content);
        $stream->rewind();

        return $stream;
    }
}
