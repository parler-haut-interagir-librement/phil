<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Http;

use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Exception\PngUnavailable;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\SvgChart\ChartType;
use Phil\SvgChart\Exception\RasterizationFailed;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * The chart of a series source as a PNG download, from a controller of the application, which checks first who may
 * see it. Where no PNG is drawn — without the resvg extension or its fonts —, `hasPng()` is false and `png()` fails
 * before anything is drawn; the page still shows the SVG image.
 */
final readonly class ChartResponses
{
    public function __construct(
        private Sources $sources,
        private ChartDrawing $drawing,
    ) {
    }

    /**
     * Whether a chart may be downloaded as a PNG.
     */
    public function hasPng(): bool
    {
        return $this->drawing->hasPng();
    }

    /**
     * @throws PngUnavailable      when no PNG is drawn
     * @throws NotFound            when the source is unknown
     * @throws RasterizationFailed when the image is not drawn
     */
    public function png(string $source, Period $period, ChartType $type = ChartType::Bar, string $title = ''): Response
    {
        if (!$this->drawing->hasPng()) {
            throw PngUnavailable::create();
        }

        $series = $this->sources->series($source);
        $png = $this->drawing->png($series->series($period), $type, '' === $title ? $series->title() : $title);
        $filename = \sprintf('%s-%s-%s.png', $source, $period->start->format('Ymd'), $period->end->modify('-1 second')->format('Ymd'));

        return new Response($png, Response::HTTP_OK, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
