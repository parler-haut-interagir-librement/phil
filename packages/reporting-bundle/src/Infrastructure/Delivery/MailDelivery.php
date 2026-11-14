<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Delivery;

use Override;
use Phil\ReportingBundle\Application\Contract\ChartedReport;
use Phil\ReportingBundle\Application\Contract\ReportDelivery;
use Phil\ReportingBundle\Application\Report\ReportChart;
use Phil\ReportingBundle\Application\Report\ReportJob;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Application\Report\StoredExport;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\SvgChart\Exception\SvgChartException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * The file attached to an e-mail to each recipient, sent by the mailer's transports from the worker that exported
 * it: the attachment never enters a queue. The file is read once, whole — an e-mail carries a few megabytes at most —, and
 * attached under its media type without parameters. The recipients are e-mail addresses, each sent its own message.
 *
 * A report that is a `ChartedReport` has its chart in the e-mail, as a PNG image shown in its HTML part, where PNG
 * charts are drawn: e-mail clients do not show SVG. Where they are not, or when the image cannot be drawn, the e-mail
 * goes as text with its file, as any other.
 */
final readonly class MailDelivery implements ReportDelivery
{
    /** The name of the chart's image in the e-mail, which its HTML part shows. */
    public const string CHART_FILENAME = 'chart.png';

    public function __construct(
        private TransportInterface $transport,
        private Reports $reports,
        private string $from,
        private ?ChartDrawing $drawing = null,
        private ?Sources $sources = null,
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
        $definition = $this->reports->definition($job->report);
        $title = $definition->title();
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
        $chart = $definition instanceof ChartedReport ? $this->chart($definition->chart(), $period) : null;

        foreach ($job->recipients as $recipient) {
            $email = new Email()
                ->from(Address::create($this->from))
                ->to(Address::create($recipient))
                ->subject($title)
                ->text($text)
                ->attach($attachment, $export->filename, $mediaType);

            if (null !== $chart) {
                $email
                    ->html(\sprintf('<p>%s</p><p><img src="cid:%s" alt="%s"></p>', htmlspecialchars(trim($text), \ENT_QUOTES), self::CHART_FILENAME, htmlspecialchars($chart[1], \ENT_QUOTES)))
                    ->addPart(new DataPart($chart[0], self::CHART_FILENAME, 'image/png')->asInline());
            }

            $this->transport->send($email);
        }
    }

    /**
     * The PNG of the chart and its title, or null where no PNG is drawn or the image cannot be drawn.
     *
     * @return array{string, string}|null
     */
    private function chart(ReportChart $chart, Period $period): ?array
    {
        if (!$this->drawing instanceof ChartDrawing || !$this->drawing->hasPng() || !$this->sources instanceof Sources) {
            return null;
        }

        $source = $this->sources->series($chart->source);
        $title = '' === $chart->title ? $source->title() : $chart->title;

        try {
            return [$this->drawing->png($source->series($period), $chart->type, $title), $title];
        } catch (SvgChartException) {
            return null;
        }
    }
}
