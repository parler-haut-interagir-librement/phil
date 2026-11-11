<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Twig\Components;

use Locale;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Application\Sources;
use Phil\ReportingBundle\Application\View\MetricReading;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * One figure of a metric source on a page, `<twig:PhilReporting:Metric source="orders" period="this_month" compare />`:
 * its title, its value written in the locale, and its change from the period before when `compare` is on.
 */
#[AsTwigComponent(name: 'PhilReporting:Metric', template: '@PhilReporting/components/Metric.html.twig', exposePublicProps: false)]
final class MetricComponent
{
    public string $source = '';

    public string $period = 'last_30_days';

    public string $title = '';

    public bool $compare = false;

    /** The locale the value is written in; the current one when empty. */
    public string $locale = '';

    public function __construct(
        private readonly Sources $sources,
        private readonly Periods $periods,
        private readonly MetricReading $reading,
    ) {
    }

    public function getHeading(): string
    {
        return '' === $this->title ? $this->sources->metric($this->source)->title() : $this->title;
    }

    public function getMetric(): Metric
    {
        return $this->sources->metric($this->source)->metric($this->periods->period($this->periods->preset($this->period)));
    }

    public function getText(): string
    {
        return $this->reading->format($this->getMetric(), $this->locale());
    }

    /**
     * @return array{direction: 'down'|'flat'|'up', percent: string|null}|null
     */
    public function getChange(): ?array
    {
        if (!$this->compare) {
            return null;
        }

        $previous = $this->sources->metric($this->source)->metric($this->periods->previous($this->periods->preset($this->period)));

        return $this->reading->change($this->getMetric(), $previous, $this->locale());
    }

    private function locale(): string
    {
        return '' === $this->locale ? Locale::getDefault() : $this->locale;
    }
}
