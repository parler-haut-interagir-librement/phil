<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessibilityCheck\ConformanceLevel;
use Phil\AccessibilityCheck\SuccessCriterion;
use Phil\AccessibilityStatement\ConformanceStatus;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\Exception\InvalidStatement;
use Phil\AccessibilityStatement\NonConformityReason;
use Phil\AccessibilityStatement\Standard;
use Phil\AccessibilityStatement\StatementReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementReader::class)]
#[CoversClass(InvalidStatement::class)]
final class StatementReaderTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    public static function complete(): array
    {
        return [
            'version' => 4,
            'published_at' => '2026-11-08T10:00:00-05:00',
            'standard' => 'wcag-2.1',
            'level' => 'AAA',
            'status' => 'partial',
            'entity' => 'Example Org',
            'scope' => ['en' => 'The site.', 'fr' => 'Le site.'],
            'feedback' => ['email' => 'a11y@example.com', 'phone' => '+1 514 555 0100', 'url' => '/contact', 'postal_address' => ['en' => '1 Main St.'], 'response_days' => 5],
            'non_conformities' => [
                ['criterion' => '1.4.6', 'reason' => 'disproportionate-burden', 'description' => ['en' => 'Faint.'], 'alternative' => ['en' => 'Call.'], 'fix_by' => '2027-01-31T00:00:00+00:00', 'occurrences' => 4, 'from_audit' => true],
                ['criterion' => '1.2.2', 'reason' => 'out-of-scope'],
            ],
            'alternatives' => [['en' => 'A printed copy.']],
            'enforcement' => ['procedure' => ['en' => 'Complain.'], 'authority' => ['en' => 'The ombudsman'], 'url' => 'https://example.org/'],
            'evaluation' => ['evaluated_at' => '2026-11-01T00:00:00+00:00', 'method' => 'third-party', 'pages' => 12, 'report_url' => '/report'],
        ];
    }

    public function testAStatementReadsBackToTheSameData(): void
    {
        $statement = StatementReader::fromArray(self::complete());

        self::assertSame(self::complete(), $statement->toArray());
        self::assertSame(Standard::Wcag21, $statement->target->standard);
        self::assertSame(ConformanceLevel::AAA, $statement->target->level);
        self::assertSame(NonConformityReason::OutOfScope, $statement->nonConformities[1]->reason);
        self::assertSame(EvaluationMethod::ThirdParty, $statement->evaluation?->method);
        self::assertEquals($statement, StatementReader::fromJson((string) json_encode(self::complete())));
    }

    public function testTheLeftOutKeysTakeTheirDefaults(): void
    {
        $statement = StatementReader::fromArray([
            'version' => 1,
            'published_at' => '2026-11-08',
            'feedback' => ['email' => 'a11y@example.com'],
            'non_conformities' => [['criterion' => '1.4.3']],
            'evaluation' => ['evaluated_at' => '2026-11-08'],
        ]);

        self::assertSame(Standard::Wcag22, $statement->target->standard);
        self::assertSame(ConformanceLevel::AA, $statement->target->level);
        self::assertSame(ConformanceStatus::Partial, $statement->status);
        self::assertSame(SuccessCriterion::ContrastMinimum, $statement->nonConformities[0]->criterion);
        self::assertSame(NonConformityReason::NonCompliance, $statement->nonConformities[0]->reason);
        self::assertSame(0, $statement->nonConformities[0]->occurrences);
        self::assertFalse($statement->nonConformities[0]->fromAudit);
        self::assertSame(EvaluationMethod::SelfAssessment, $statement->evaluation?->method);
        self::assertSame(0, $statement->evaluation->pages);
        self::assertSame('2026-11-08T00:00:00+00:00', $statement->publishedAt->format(\DATE_ATOM));
        self::assertSame([], $statement->alternatives);
        self::assertNull($statement->enforcement);
        self::assertSame(ConformanceStatus::Full, StatementReader::fromArray(['version' => 1, 'published_at' => '2026-11-08', 'feedback' => ['email' => 'a11y@example.com']])->status);
    }

    public function testAMomentInUtcIsReadWithItsZ(): void
    {
        $statement = StatementReader::fromArray(['version' => 1, 'published_at' => '2026-11-08T10:00:00Z', 'feedback' => ['email' => 'a11y@example.com']]);

        self::assertEquals(new DateTimeImmutable('2026-11-08T10:00:00+00:00'), $statement->publishedAt);
    }

    /**
     * @param array<string, mixed> $changes
     */
    #[DataProvider('invalidData')]
    public function testInvalidDataIsRefused(array $changes, string $message): void
    {
        $data = array_filter(array_replace(self::complete(), $changes), static fn (mixed $value): bool => '__unset' !== $value);

        try {
            StatementReader::fromArray($data);
            self::fail('The data was accepted.');
        } catch (InvalidStatement $invalidStatement) {
            self::assertSame($message, $invalidStatement->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidData(): iterable
    {
        $nonConformity = static fn (array $changes): array => ['non_conformities' => [['criterion' => '1.4.3', ...$changes]]];

        yield 'an unknown key' => [['colour' => 'red'], 'The statement has no key "colour".'];
        yield 'an unknown feedback key' => [['feedback' => ['email' => 'a@example.com', 'fax' => '1']], 'The feedback has no key "fax".'];
        yield 'an unknown non-conformity key' => [$nonConformity(['page' => '/']), 'The non_conformities has no key "page".'];
        yield 'an unknown enforcement key' => [['enforcement' => ['procedure' => ['en' => 'x'], 'phone' => '1']], 'The enforcement has no key "phone".'];
        yield 'an unknown evaluation key' => [['evaluation' => ['evaluated_at' => '2026-11-01', 'tool' => 'x']], 'The evaluation has no key "tool".'];
        yield 'no version' => [['version' => '__unset'], 'The value of "version" is a number from 1.'];
        yield 'a version as text' => [['version' => '4'], 'The value of "version" is an integer.'];
        yield 'no publication' => [['published_at' => '__unset'], 'The value of "published_at" is a moment.'];
        yield 'a relative moment' => [['published_at' => 'tomorrow'], 'The value of "published_at" is a moment such as 2026-11-08 or 2026-11-08T10:00:00+00:00.'];
        yield 'a date after something' => [['published_at' => 'x2026-11-08'], 'The value of "published_at" is a moment such as 2026-11-08 or 2026-11-08T10:00:00+00:00.'];
        yield 'a time after something' => [['published_at' => 'x2026-11-08T10:00:00Z'], 'The value of "published_at" is a moment such as 2026-11-08 or 2026-11-08T10:00:00+00:00.'];
        yield 'a moment with a line' => [['published_at' => "2026-11-08\n"], 'The value of "published_at" is a moment such as 2026-11-08 or 2026-11-08T10:00:00+00:00.'];
        yield 'a time with a line' => [['published_at' => "2026-11-08T10:00:00Z\n"], 'The value of "published_at" is a moment such as 2026-11-08 or 2026-11-08T10:00:00+00:00.'];
        yield 'a day that does not exist' => [['published_at' => '2026-02-30'], 'The value of "published_at" is a moment that exists.'];
        yield 'an hour that does not exist' => [['published_at' => '2026-02-03T25:00:00Z'], 'The value of "published_at" is a moment that exists.'];
        yield 'a moment as a number' => [['published_at' => 20261108], 'The value of "published_at" is a string.'];
        yield 'an unknown standard' => [['standard' => 'wcag-3.0'], 'The value of "standard" is a version of WCAG: wcag-2.0, wcag-2.1 or wcag-2.2.'];
        yield 'an unknown level' => [['level' => 'AAAA'], 'The value of "level" is A, AA or AAA.'];
        yield 'an unknown status' => [['status' => 'great'], 'The value of "status" is full, partial or non-conformant.'];
        yield 'no feedback' => [['feedback' => '__unset'], 'The value of "feedback" is a map.'];
        yield 'a feedback as text' => [['feedback' => 'a@example.com'], 'The value of "feedback" is a map.'];
        yield 'non-conformities as a map' => [['non_conformities' => ['a' => []]], 'The value of "non_conformities" is a list.'];
        yield 'non-conformities as text' => [['non_conformities' => 'none'], 'The value of "non_conformities" is a list.'];
        yield 'a non-conformity as text' => [['non_conformities' => ['1.4.3']], 'The value of "non_conformities" is a map.'];
        yield 'an unknown criterion' => [$nonConformity(['criterion' => '4.1.1']), 'The value of "criterion" is the number of a success criterion of WCAG 2.2, such as 1.4.3.'];
        yield 'no criterion' => [['non_conformities' => [['reason' => 'non-compliance']]], 'The value of "criterion" is the number of a success criterion of WCAG 2.2, such as 1.4.3.'];
        yield 'an unknown reason' => [$nonConformity(['reason' => 'later']), 'The value of "reason" is non-compliance, disproportionate-burden or out-of-scope.'];
        yield 'a flag as text' => [$nonConformity(['from_audit' => 'yes']), 'The value of "from_audit" is true or false.'];
        yield 'occurrences as text' => [$nonConformity(['occurrences' => '3']), 'The value of "occurrences" is an integer.'];
        yield 'a text as a string' => [['scope' => 'The site.'], 'The value of "scope" is a map.'];
        yield 'an alternative as a string' => [['alternatives' => ['A copy.']], 'The value of "alternatives" is a map.'];
        yield 'an enforcement as text' => [['enforcement' => 'Complain.'], 'The value of "enforcement" is a map.'];
        yield 'an enforcement without procedure' => [['enforcement' => ['url' => 'https://example.org/']], 'The value of "procedure" is a text.'];
        yield 'an evaluation as text' => [['evaluation' => '2026-11-01'], 'The value of "evaluation" is a map.'];
        yield 'an evaluation without date' => [['evaluation' => ['method' => 'automated']], 'The value of "evaluated_at" is a moment.'];
        yield 'an unknown method' => [['evaluation' => ['evaluated_at' => '2026-11-01', 'method' => 'guess']], 'The value of "method" is self-assessment, third-party or automated.'];
        yield 'an entity as a number' => [['entity' => 42], 'The value of "entity" is a string.'];
    }

    public function testAKeyNamedByANumberIsRefusedByItsName(): void
    {
        $this->expectExceptionMessageIsOrContains('The statement has no key "0".');

        StatementReader::fromArray(['x']);
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('invalidJson')]
    public function testInvalidJsonIsRefused(string $json, string $message): void
    {
        try {
            StatementReader::fromJson($json);
            self::fail('The JSON was accepted.');
        } catch (InvalidStatement $invalidStatement) {
            self::assertStringStartsWith($message, $invalidStatement->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function invalidJson(): iterable
    {
        yield 'not JSON' => ['{', 'The statement is not JSON: Syntax error'];
        yield 'too deep' => [str_repeat('[', StatementReader::MAX_JSON_DEPTH).str_repeat(']', StatementReader::MAX_JSON_DEPTH), 'The statement is not JSON: Maximum stack depth exceeded'];
        yield 'too large' => [str_pad('{}', StatementReader::MAX_JSON_BYTES + 1), 'A statement in JSON holds at most 1048576 bytes.'];
        yield 'not an object' => ['"statement"', 'The value of "statement" is a map.'];
    }

    public function testJsonAtItsLimitsIsRead(): void
    {
        $json = (string) json_encode(self::complete());
        $statement = StatementReader::fromJson(str_pad($json, StatementReader::MAX_JSON_BYTES));

        self::assertSame(4, $statement->version);

        // JSON as deep as the limit is decoded, then read: a list nested 15 times is no statement.
        $this->expectExceptionMessageIsOrContains('The statement has no key "0".');
        StatementReader::fromJson(str_repeat('[', StatementReader::MAX_JSON_DEPTH - 1).str_repeat(']', StatementReader::MAX_JSON_DEPTH - 1));
    }
}
