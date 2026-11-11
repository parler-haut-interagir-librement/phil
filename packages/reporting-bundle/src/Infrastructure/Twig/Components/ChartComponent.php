<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Twig\Components;

use Locale;
use Phil\ReportingBundle\Application\Dashboard\ChartRendering;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Application\View\MetricReading;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;
use Phil\SvgChart\ChartType;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The series of a series source on a page, `<twig:PhilReporting:Chart source="visits" chart="line" />`: an SVG image
 * with the table of its values beside it (`render="svg"`), bars of HTML and CSS that are a table themselves
 * (`render="html"`), or the table alone (`render="table"`). No script draws anything.
 */
#[AsTwigComponent(name: 'PhilReporting:Chart', template: '@PhilReporting/components/Chart.html.twig', exposePublicProps: false)]
final class ChartComponent
{
    public string $source = '';

    public string $period = 'last_30_days';

    public string $title = '';

    /** `bar`, `line`, `pie` or `doughnut`, for the SVG image. */
    public string $chart = 'bar';

    /** `svg`, `html` or `table`. */
    public string $render = 'svg';

    /** The locale the values are written in; the current one when empty. */
    public string $locale = '';

    public function __construct(
        private readonly Sources $sources,
        private readonly Periods $periods,
        private readonly ChartDrawing $drawing,
        private readonly MetricReading $reading,
    ) {
    }

    public function getHeading(): string
    {
        return '' === $this->title ? $this->sources->series($this->source)->title() : $this->title;
    }

    public function getRendering(): ChartRendering
    {
        return ChartRendering::tryFrom($this->render) ?? ChartRendering::Svg;
    }

    public function getSet(): SeriesSet
    {
        return $this->sources->series($this->source)->series($this->periods->period($this->periods->preset($this->period)));
    }

    public function getSvg(): string
    {
        return $this->drawing->svg($this->getSet(), ChartType::tryFrom($this->chart) ?? ChartType::Bar, $this->getHeading());
    }

    /**
     * @return list<array{label: string, bars: list<array{series: string, value: float|null, share: int, negative: bool}>}>
     */
    public function getBars(): array
    {
        return $this->drawing->bars($this->getSet());
    }

    /**
     * A value written in the locale, or an empty text where there is none.
     */
    public function number(?float $value): string
    {
        return $this->reading->number($value, '' === $this->locale ? Locale::getDefault() : $this->locale);
    }
}
