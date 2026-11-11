<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * What a report is asked for: its period, and the parameters its definition reads — a category, a status —, each a
 * scalar under a short key.
 */
final readonly class ReportRequest
{
    public const int MAX_PARAMETERS = 32;

    public const int MAX_TEXT = 200;

    private const string KEY = '/^[A-Za-z0-9_.-]{1,64}$/D';

    /**
     * @param array<string, bool|float|int|string> $parameters
     *
     * @throws InvalidReportJob when there are more than 32 parameters, a key is out of shape or a text longer than 200
     *                          characters
     */
    public function __construct(
        public Period $period,
        public array $parameters = [],
    ) {
        if (\count($parameters) > self::MAX_PARAMETERS) {
            throw InvalidReportJob::because(\sprintf('more than %d parameters', self::MAX_PARAMETERS));
        }

        foreach ($parameters as $key => $value) {
            if (1 !== preg_match(self::KEY, $key)) {
                throw InvalidReportJob::because(\sprintf('the parameter "%s" is not named by 1 to 64 letters, digits, dots, dashes or underscores', $key));
            }

            if (\is_string($value) && mb_strlen($value) > self::MAX_TEXT) {
                throw InvalidReportJob::because(\sprintf('the parameter "%s" is longer than %d characters', $key, self::MAX_TEXT));
            }
        }
    }

    public function parameter(string $key, bool|float|int|string|null $default = null): bool|float|int|string|null
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * @return array{start: string, end: string, timezone: string, parameters: array<string, bool|float|int|string>}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->period->start->format(\DATE_ATOM),
            'end' => $this->period->end->format(\DATE_ATOM),
            'timezone' => $this->period->start->getTimezone()->getName(),
            'parameters' => $this->parameters,
        ];
    }

    /**
     * The request `toArray()` wrote, or null for anything else.
     */
    public static function fromArray(mixed $data): ?self
    {
        if (!\is_array($data) || !\is_string($data['start'] ?? null) || !\is_string($data['end'] ?? null) || !\is_string($data['timezone'] ?? null) || !\is_array($data['parameters'] ?? null)) {
            return null;
        }

        $parameters = [];

        foreach ($data['parameters'] as $key => $value) {
            if (!\is_string($key) || !\is_scalar($value)) {
                return null;
            }

            $parameters[$key] = $value;
        }

        try {
            $timezone = new DateTimeZone($data['timezone']);

            return new self(new Period(
                new DateTimeImmutable($data['start'])->setTimezone($timezone),
                new DateTimeImmutable($data['end'])->setTimezone($timezone),
            ), $parameters);
        } catch (Exception) {
            return null;
        }
    }
}
