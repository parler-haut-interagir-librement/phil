<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * One version of an accessibility statement, never changed once published: the conformance target and the status
 * claimed, the success criteria not met, the alternatives offered, the feedback contact, the enforcement procedure and
 * the last evaluation.
 *
 * The status agrees with the non-conformities — full only when none counts against the target, partial or
 * non-conformant only when one does —, every non-conformity names a criterion of the target, and the last evaluation
 * comes before the publication.
 */
final readonly class Statement
{
    public const int MAX_NON_CONFORMITIES = 1_000;

    public const int MAX_ALTERNATIVES = 50;

    public const int MAX_ENTITY_LENGTH = 200;

    /**
     * @param int                 $version         from 1, one more for each version published
     * @param list<NonConformity> $nonConformities in the order they are shown
     * @param list<LocalizedText> $alternatives    the ways to reach the content that do not depend on it being accessible
     * @param ?LocalizedText      $scope           what the statement covers, such as a site or an application
     * @param ?string             $entity          the organisation that makes the statement
     */
    public function __construct(
        public int $version,
        public DateTimeImmutable $publishedAt,
        public ConformanceTarget $target,
        public ConformanceStatus $status,
        public FeedbackContact $feedback,
        public array $nonConformities = [],
        public array $alternatives = [],
        public ?LocalizedText $scope = null,
        public ?Enforcement $enforcement = null,
        public ?Evaluation $evaluation = null,
        public ?string $entity = null,
    ) {
        if ($version < 1) {
            throw InvalidStatement::because('version', \sprintf('A version is numbered from 1; got %d.', $version));
        }

        if (\count($nonConformities) > self::MAX_NON_CONFORMITIES || \count($alternatives) > self::MAX_ALTERNATIVES) {
            throw InvalidStatement::because('too_many', \sprintf('A statement lists at most %d non-conformities and %d alternatives.', self::MAX_NON_CONFORMITIES, self::MAX_ALTERNATIVES));
        }

        foreach ($nonConformities as $nonConformity) {
            if (!$target->covers($nonConformity->criterion)) {
                throw InvalidStatement::because('criterion_outside_target', \sprintf('Success criterion %s is not part of %s.', $nonConformity->criterion->value, $target->describe()));
            }
        }

        $failing = [] !== NonConformity::failedCriteria($nonConformities);

        if ($failing === (ConformanceStatus::Full === $status)) {
            throw InvalidStatement::because('status', $failing ? 'A statement that lists a criterion not met does not claim full conformance.' : 'A statement that lists no criterion not met claims full conformance.');
        }

        if ($evaluation instanceof Evaluation && $evaluation->evaluatedAt > $publishedAt) {
            throw InvalidStatement::because('evaluation_date', 'The last evaluation comes before the publication of the statement.');
        }

        if (null !== $entity && ('' === trim($entity) || mb_strlen($entity) > self::MAX_ENTITY_LENGTH)) {
            throw InvalidStatement::because('entity', \sprintf('The organisation is named in 1 to %d characters.', self::MAX_ENTITY_LENGTH));
        }
    }

    /**
     * The non-conformities that keep the content from full conformance, leaving out those outside the scope.
     *
     * @return list<NonConformity>
     */
    public function blocking(): array
    {
        return array_values(array_filter($this->nonConformities, static fn (NonConformity $nonConformity): bool => $nonConformity->reason->countsAgainstConformance()));
    }

    /**
     * The non-conformities of one reason.
     *
     * @return list<NonConformity>
     */
    public function withReason(NonConformityReason $reason): array
    {
        return array_values(array_filter($this->nonConformities, static fn (NonConformity $nonConformity): bool => $reason === $nonConformity->reason));
    }

    /**
     * When the content was last evaluated, or `null` when the statement does not say.
     */
    public function lastEvaluatedAt(): ?DateTimeImmutable
    {
        return $this->evaluation?->evaluatedAt;
    }

    /**
     * The statement as plain data, which `StatementReader::fromArray()` reads back to the same statement.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'version' => $this->version,
            'published_at' => $this->publishedAt->format(DateTimeInterface::ATOM),
            'standard' => $this->target->standard->value,
            'level' => $this->target->level->value,
            'status' => $this->status->value,
            'entity' => $this->entity,
            'scope' => $this->scope?->texts,
            'feedback' => array_filter([
                'email' => $this->feedback->email,
                'phone' => $this->feedback->phone,
                'url' => $this->feedback->url,
                'postal_address' => $this->feedback->postalAddress?->texts,
                'response_days' => $this->feedback->responseDays,
            ], self::given(...)),
            'non_conformities' => array_map(static fn (NonConformity $nonConformity): array => array_filter([
                'criterion' => $nonConformity->criterion->value,
                'reason' => $nonConformity->reason->value,
                'description' => $nonConformity->description?->texts,
                'alternative' => $nonConformity->alternative?->texts,
                'fix_by' => $nonConformity->fixBy?->format(DateTimeInterface::ATOM),
                'occurrences' => $nonConformity->occurrences,
                'from_audit' => $nonConformity->fromAudit,
            ], self::given(...)), $this->nonConformities),
            'alternatives' => array_map(static fn (LocalizedText $alternative): array => $alternative->texts, $this->alternatives),
            'enforcement' => $this->enforcement instanceof Enforcement ? array_filter([
                'procedure' => $this->enforcement->procedure->texts,
                'authority' => $this->enforcement->authority?->texts,
                'url' => $this->enforcement->url,
            ], self::given(...)) : null,
            'evaluation' => $this->evaluation instanceof Evaluation ? array_filter([
                'evaluated_at' => $this->evaluation->evaluatedAt->format(DateTimeInterface::ATOM),
                'method' => $this->evaluation->method->value,
                'pages' => $this->evaluation->pages,
                'report_url' => $this->evaluation->reportUrl,
            ], self::given(...)) : null,
        ], self::given(...));
    }

    /**
     * Whether a value is worth writing: neither null, nor an empty list, nor a zero, nor false.
     */
    private static function given(mixed $value): bool
    {
        return null !== $value && [] !== $value && 0 !== $value && false !== $value;
    }
}
