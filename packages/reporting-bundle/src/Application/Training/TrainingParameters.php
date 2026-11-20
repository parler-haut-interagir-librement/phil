<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

use DateTimeImmutable;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;

/**
 * The parameters the training reports read from a request, each checked.
 */
final class TrainingParameters
{
    /**
     * The `step` parameter: `month`, `quarter` or `year`; the whole period without it.
     *
     * @throws InvalidReportJob when the parameter is another value
     */
    public static function step(ReportRequest $request): ?TrainingStep
    {
        $step = $request->parameter('step');

        if (null === $step) {
            return null;
        }

        return (\is_string($step) ? TrainingStep::tryFrom($step) : null)
            ?? throw InvalidReportJob::because('the parameter "step" is "month", "quarter" or "year"');
    }

    /**
     * @throws InvalidReportJob when the parameter is not a boolean
     */
    public static function flag(ReportRequest $request, string $name, bool $default): bool
    {
        $value = $request->parameter($name, $default);

        return \is_bool($value) ? $value : throw InvalidReportJob::because(\sprintf('the parameter "%s" is true or false', $name));
    }

    /**
     * @throws InvalidReportJob when the parameter is not a text
     */
    public static function text(ReportRequest $request, string $name): ?string
    {
        $value = $request->parameter($name);

        return null === $value || \is_string($value) ? $value : throw InvalidReportJob::because(\sprintf('the parameter "%s" is a text', $name));
    }

    /**
     * @throws InvalidReportJob when the parameter is not a whole number of zero or more
     */
    public static function amount(ReportRequest $request, string $name): ?int
    {
        $value = $request->parameter($name);

        return null === $value || (\is_int($value) && $value >= 0) ? $value : throw InvalidReportJob::because(\sprintf('the parameter "%s" is a whole number of zero or more', $name));
    }

    /**
     * The last day of a period, its end excluded: `2026-12-31` for a period that ends on the first of January.
     */
    public static function lastDay(DateTimeImmutable $end): string
    {
        return $end->modify('-1 second')->format('Y-m-d');
    }
}
