<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Infrastructure\Http;

use DateTimeImmutable;
use DateTimeZone;
use Phil\AuditBundle\Domain\Chain\Subject;
use Phil\AuditBundle\Domain\Read\AuditQuery;
use Phil\AuditBundle\Domain\Read\Cursor;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * The filters of the log's page, read from its query string or from the export form: each one empty or an
 * identifier — the tenant a reference, for a viewer who reads every site; in a tenant, the log stays held to it —,
 * the period as two local date-times of the viewer's time zone. A value that does not read is said, and
 * the query is left without that filter's rows: the page shows the refusal and nothing else.
 */
final readonly class AuditFilters
{
    /** The fields, in the order the form shows them. */
    public const array FIELDS = ['actor', 'action', 'prefix', 'subject_type', 'subject_id', 'segment', 'tenant', 'since', 'until'];

    private const string LOCAL = '!Y-m-d\TH:i';

    /**
     * @param array<string, string> $values what was asked, to show again in the form
     * @param list<string>          $errors the fields whose value did not read
     */
    private function __construct(
        public array $values,
        public ?AuditQuery $query,
        public array $errors,
    ) {
    }

    /**
     * @param InputBag<string> $input
     */
    public static function read(InputBag $input, DateTimeZone $timezone, int $limit): self
    {
        $values = [];

        foreach (self::FIELDS as $field) {
            $value = trim($input->getString($field));

            if ('' !== $value) {
                $values[$field] = $value;
            }
        }

        $query = AuditQuery::all()->limitedTo($limit);
        $errors = [];

        foreach (['actor', 'action', 'subject_type', 'subject_id', 'segment', 'tenant', 'since', 'until', 'cursor'] as $field) {
            try {
                $query = self::apply($query, $field, $values, $input, $timezone);
            } catch (InvalidValue) {
                $errors[] = $field;
            }
        }

        return new self($values, [] === $errors ? $query : null, $errors);
    }

    /**
     * The filters as the fields of a form or a query string, without the cursor.
     *
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return $this->values;
    }

    /**
     * The filters as the fields of the row the export records: the names of what was asked, never a value of the log.
     *
     * @return array<string, string>
     */
    public function journal(): array
    {
        $journal = [];

        foreach ($this->values as $field => $value) {
            $journal['filter_'.$field] = $value;
        }

        return $journal;
    }

    /**
     * @param array<string, string> $values
     * @param InputBag<string>      $input
     */
    private static function apply(AuditQuery $query, string $field, array $values, InputBag $input, DateTimeZone $timezone): AuditQuery
    {
        $value = $values[$field] ?? null;

        return match (true) {
            'cursor' === $field => '' === $input->getString('cursor') ? $query : $query->after(Cursor::fromToken($input->getString('cursor'))),
            null === $value => $query,
            'actor' === $field => $query->byActor($value),
            'action' === $field => $query->withAction($value, isset($values['prefix'])),
            'subject_type' === $field => isset($values['subject_id']) ? $query->about(Subject::of($value, $values['subject_id'])) : $query->aboutType($value),
            'subject_id' === $field => isset($values['subject_type']) ? $query : throw InvalidValue::because('A subject identifier needs its type.'),
            'segment' === $field => $query->inSegment($value),
            'tenant' === $field => $query->inTenant($value),
            'since' === $field => $query->between(self::local($value, $timezone), $query->until),
            default => $query->between($query->since, self::local($value, $timezone)),
        };
    }

    private static function local(string $value, DateTimeZone $timezone): DateTimeImmutable
    {
        // The shape first: `createFromFormat()` throws on a null byte rather than refusing it.
        $instant = 1 === preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value) ? DateTimeImmutable::createFromFormat(self::LOCAL, $value, $timezone) : false;

        if (false === $instant || $instant->format('Y-m-d\TH:i') !== $value) {
            throw InvalidValue::because(\sprintf('"%s" is not a local date and time.', $value));
        }

        return $instant;
    }
}
