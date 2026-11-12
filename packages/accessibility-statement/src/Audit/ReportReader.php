<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Audit;

use JsonException;
use Phil\AccessibilityCheck\Finding;
use Phil\AccessibilityCheck\Location;
use Phil\AccessibilityCheck\Message;
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityCheck\RuleResult;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Exception\InvalidReport;

/**
 * Reads back the JSON of a report of phil/accessibility-check — what `phil:accessibility:check --format=json` writes
 * and `json_encode($report)` gives —: the outcome of each rule and each finding, with its criterion, severity, message
 * key, parameters and location. The text of a message is written again by the translator of the report read.
 *
 * A report larger than its limits, deeper than a report is, or not shaped as one is refused with an `InvalidReport`.
 */
final readonly class ReportReader
{
    public const int MAX_BYTES = 5_242_880;

    public const int MAX_DEPTH = 8;

    public const int MAX_ENTRIES = 50_000;

    private const array LOCATION_STRINGS = ['element', 'snippet', 'block', 'blockType', 'pointer'];

    public function __construct(
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    public function fromJson(string $json): Report
    {
        if (\strlen($json) > $this->maxBytes) {
            throw InvalidReport::because(\sprintf('A report holds at most %d bytes.', $this->maxBytes));
        }

        try {
            $data = json_decode($json, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidReport::because('The report is not JSON: '.$jsonException->getMessage());
        }

        return $this->fromArray(\is_array($data) ? $data : throw InvalidReport::because('A report is a JSON object.'));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function fromArray(array $data): Report
    {
        $locale = $data['locale'] ?? 'en';

        return new Report(
            array_map($this->result(...), $this->entries($data, 'rules')),
            array_map($this->finding(...), $this->entries($data, 'findings')),
            locale: \is_string($locale) && '' !== $locale ? $locale : throw InvalidReport::because('The locale of a report is a language tag.'),
        );
    }

    private function result(mixed $entry): RuleResult
    {
        $entry = $this->map($entry, 'rule');
        $findings = $entry['findings'] ?? null;

        return new RuleResult(
            $this->string($entry, 'rule'),
            $this->criterion($entry),
            $this->severity($entry),
            \is_int($findings) && $findings >= 0 ? $findings : throw InvalidReport::because('The findings of a rule are counted from 0.'),
        );
    }

    private function finding(mixed $entry): Finding
    {
        $entry = $this->map($entry, 'finding');
        $parameters = $this->map($entry['parameters'] ?? [], 'parameters');

        foreach ($parameters as $name => $value) {
            if (!\is_string($name) || !(\is_string($value) || \is_int($value) || \is_float($value))) {
                throw InvalidReport::because('The parameters of a message are named strings and numbers.');
            }
        }

        /** @var array<string, int|float|string> $parameters */
        return new Finding(
            $this->string($entry, 'rule'),
            $this->criterion($entry),
            $this->severity($entry),
            new Message($this->string($entry, 'messageKey'), $parameters),
            $this->location($this->map($entry['location'] ?? [], 'location')),
        );
    }

    /**
     * @param array<array-key, mixed> $location
     */
    private function location(array $location): Location
    {
        $line = $location['line'] ?? null;

        if (null !== $line && (!\is_int($line) || $line < 1)) {
            throw InvalidReport::because('The line of a location is counted from 1.');
        }

        $strings = [];

        foreach (self::LOCATION_STRINGS as $field) {
            $value = $location[$field] ?? null;
            $strings[$field] = null === $value || \is_string($value) ? $value : throw InvalidReport::because(\sprintf('The %s of a location is a string.', $field));
        }

        return new Location($line, ...$strings);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private function entries(array $data, string $key): array
    {
        $entries = $data[$key] ?? [];

        if (!\is_array($entries) || !array_is_list($entries) || \count($entries) > self::MAX_ENTRIES) {
            throw InvalidReport::because(\sprintf('The %s of a report are a list of at most %d entries.', $key, self::MAX_ENTRIES));
        }

        return $entries;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function map(mixed $value, string $what): array
    {
        return \is_array($value) ? $value : throw InvalidReport::because(\sprintf('A %s of a report is an object.', $what));
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function string(array $entry, string $key): string
    {
        $value = $entry[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : throw InvalidReport::because(\sprintf('The %s of a report entry is a string.', $key));
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function criterion(array $entry): SuccessCriterion
    {
        return SuccessCriterion::tryFrom($this->string($entry, 'criterion')) ?? throw InvalidReport::because('The criterion of a report entry is a success criterion of WCAG 2.2.');
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private function severity(array $entry): Severity
    {
        return Severity::tryFrom($this->string($entry, 'severity')) ?? throw InvalidReport::because('The severity of a report entry is error, warning or notice.');
    }
}
