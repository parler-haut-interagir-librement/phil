<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * Reads a statement from plain data — a configuration, a JSON document, a row — with the keys `Statement::toArray()`
 * writes. Every key is checked: an unknown key, a value of the wrong type or out of its bounds refuses the whole
 * statement with an `InvalidStatement`, never a warning nor a statement half read.
 *
 * `standard` is `wcag-2.2` and `level` is `AA` when left out; `status`, left out, is the one the non-conformities
 * support (`ConformanceStatus::derive()`). A moment is `2026-11-08` or `2026-11-08T10:00:00+00:00`.
 */
final class StatementReader
{
    public const int MAX_JSON_BYTES = 1_048_576;

    public const int MAX_JSON_DEPTH = 16;

    private const array KEYS = ['version', 'published_at', 'standard', 'level', 'status', 'entity', 'scope', 'feedback', 'non_conformities', 'alternatives', 'enforcement', 'evaluation'];

    private const array FEEDBACK_KEYS = ['email', 'phone', 'url', 'postal_address', 'response_days'];

    private const array NON_CONFORMITY_KEYS = ['criterion', 'reason', 'description', 'alternative', 'fix_by', 'occurrences', 'from_audit'];

    private const array ENFORCEMENT_KEYS = ['procedure', 'authority', 'url'];

    private const array EVALUATION_KEYS = ['evaluated_at', 'method', 'pages', 'report_url'];

    private function __construct()
    {
    }

    public static function fromJson(string $json): Statement
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidStatement::because('json', \sprintf('A statement in JSON holds at most %d bytes.', self::MAX_JSON_BYTES));
        }

        try {
            $data = json_decode($json, true, self::MAX_JSON_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidStatement::because('json', 'The statement is not JSON: '.$jsonException->getMessage());
        }

        return self::fromArray(self::map($data, 'statement'));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): Statement
    {
        self::keys($data, self::KEYS, 'statement');
        $target = new ConformanceTarget(
            Standard::tryFrom(self::string($data, 'standard', Standard::Wcag22->value)) ?? throw self::invalid('standard', 'a version of WCAG: wcag-2.0, wcag-2.1 or wcag-2.2'),
            ConformanceLevel::tryFrom(self::string($data, 'level', ConformanceLevel::AA->value)) ?? throw self::invalid('level', 'A, AA or AAA'),
        );
        $nonConformities = array_map(self::nonConformity(...), self::list($data, 'non_conformities'));
        $status = self::optionalString($data, 'status');

        return new Statement(
            self::integer($data, 'version') ?? throw self::invalid('version', 'a number from 1'),
            self::moment($data, 'published_at') ?? throw self::invalid('published_at', 'a moment'),
            $target,
            null === $status ? ConformanceStatus::derive($target, $nonConformities) : ConformanceStatus::tryFrom($status) ?? throw self::invalid('status', 'full, partial or non-conformant'),
            self::feedback(self::map($data['feedback'] ?? null, 'feedback')),
            $nonConformities,
            array_map(static fn (mixed $text): LocalizedText => new LocalizedText(self::map($text, 'alternatives')), self::list($data, 'alternatives')),
            self::text($data, 'scope'),
            isset($data['enforcement']) ? self::enforcement(self::map($data['enforcement'], 'enforcement')) : null,
            isset($data['evaluation']) ? self::evaluation(self::map($data['evaluation'], 'evaluation')) : null,
            self::optionalString($data, 'entity'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function feedback(array $data): FeedbackContact
    {
        self::keys($data, self::FEEDBACK_KEYS, 'feedback');

        return new FeedbackContact(
            self::optionalString($data, 'email'),
            self::optionalString($data, 'phone'),
            self::optionalString($data, 'url'),
            self::text($data, 'postal_address'),
            self::integer($data, 'response_days'),
        );
    }

    private static function nonConformity(mixed $value): NonConformity
    {
        $data = self::map($value, 'non_conformities');
        self::keys($data, self::NON_CONFORMITY_KEYS, 'non_conformities');

        return new NonConformity(
            SuccessCriterion::tryFrom(self::string($data, 'criterion', '')) ?? throw self::invalid('criterion', 'the number of a success criterion of WCAG 2.2, such as 1.4.3'),
            NonConformityReason::tryFrom(self::string($data, 'reason', NonConformityReason::NonCompliance->value)) ?? throw self::invalid('reason', 'non-compliance, disproportionate-burden or out-of-scope'),
            self::text($data, 'description'),
            self::text($data, 'alternative'),
            self::moment($data, 'fix_by'),
            self::integer($data, 'occurrences') ?? 0,
            self::flag($data, 'from_audit'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function enforcement(array $data): Enforcement
    {
        self::keys($data, self::ENFORCEMENT_KEYS, 'enforcement');

        return new Enforcement(
            self::text($data, 'procedure') ?? throw self::invalid('procedure', 'a text'),
            self::text($data, 'authority'),
            self::optionalString($data, 'url'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function evaluation(array $data): Evaluation
    {
        self::keys($data, self::EVALUATION_KEYS, 'evaluation');

        return new Evaluation(
            self::moment($data, 'evaluated_at') ?? throw self::invalid('evaluated_at', 'a moment'),
            EvaluationMethod::tryFrom(self::string($data, 'method', EvaluationMethod::SelfAssessment->value)) ?? throw self::invalid('method', 'self-assessment, third-party or automated'),
            self::integer($data, 'pages') ?? 0,
            self::optionalString($data, 'report_url'),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $known
     */
    private static function keys(array $data, array $known, string $where): void
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, $known, true)) {
                throw InvalidStatement::because('unknown_key', \sprintf('The %s has no key "%s".', $where, $key));
            }
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value, string $key): array
    {
        return \is_array($value) ? $value : throw self::invalid($key, 'a map');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     */
    private static function list(array $data, string $key): array
    {
        $value = $data[$key] ?? [];

        return \is_array($value) && array_is_list($value) ? $value : throw self::invalid($key, 'a list');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function string(array $data, string $key, string $default): string
    {
        return self::optionalString($data, $key) ?? $default;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return null === $value || \is_string($value) ? $value : throw self::invalid($key, 'a string');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function integer(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return null === $value || \is_int($value) ? $value : throw self::invalid($key, 'an integer');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function flag(array $data, string $key): bool
    {
        $value = $data[$key] ?? false;

        return \is_bool($value) ? $value : throw self::invalid($key, 'true or false');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function text(array $data, string $key): ?LocalizedText
    {
        $value = $data[$key] ?? null;

        return null === $value ? null : new LocalizedText(self::map($value, $key));
    }

    /**
     * A moment written as a date, read at midnight UTC, or as a date and a time with its offset; a date that does not
     * exist, such as `2026-02-30`, is refused.
     *
     * @param array<array-key, mixed> $data
     */
    private static function moment(array $data, string $key): ?DateTimeImmutable
    {
        $value = self::optionalString($data, $key);

        if (null === $value) {
            return null;
        }

        $format = match (1) {
            preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) => '!Y-m-d',
            preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value) => 'Y-m-d\TH:i:sP',
            default => throw self::invalid($key, 'a moment such as 2026-11-08 or 2026-11-08T10:00:00+00:00'),
        };

        $moment = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone('UTC'));

        if (false === $moment || false !== DateTimeImmutable::getLastErrors()) {
            throw self::invalid($key, 'a moment that exists');
        }

        return $moment;
    }

    private static function invalid(string $key, string $expected): InvalidStatement
    {
        return InvalidStatement::because('invalid_value', \sprintf('The value of "%s" is %s.', $key, $expected));
    }
}
