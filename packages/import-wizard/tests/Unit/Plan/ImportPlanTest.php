<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Plan;

use DateTimeImmutable;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\ImportPlan;
use Phil\ImportWizard\Plan\NaturalKey;
use Phil\ImportWizard\Plan\PlannedRow;
use Phil\ImportWizard\Plan\PreviewLine;
use Phil\ImportWizard\Plan\Reason;
use Phil\ImportWizard\Plan\RowAction;
use Phil\ImportWizard\Plan\RowRejection;
use Phil\ImportWizard\Profile\ColumnMapping;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportPlan::class)]
#[CoversClass(PlannedRow::class)]
#[CoversClass(PreviewLine::class)]
#[CoversClass(RowRejection::class)]
#[CoversClass(NaturalKey::class)]
#[CoversClass(CellError::class)]
final class ImportPlanTest extends TestCase
{
    /**
     * @param array<string, string|int|bool|DateTimeImmutable|null> $values
     */
    private function row(int $line, RowAction $action, string $key, array $values = []): PlannedRow
    {
        return new PlannedRow($line, $action, new NaturalKey([$key]), $values, [], [$key]);
    }

    /**
     * @param list<PlannedRow>   $rows
     * @param list<RowRejection> $rejections
     * @param list<string>       $headers
     */
    private static function plan(array $rows, array $rejections = [], ?ColumnMapping $mapping = null, array $headers = ['Email']): ImportPlan
    {
        return new ImportPlan(LmsProfiles::people(), $headers, $mapping ?? new ColumnMapping([['header' => 'Email', 'column' => 'email']]), $rows, $rejections);
    }

    public function testItCountsWhatItWillDo(): void
    {
        $plan = self::plan(
            [$this->row(2, RowAction::Create, 'a'), $this->row(3, RowAction::Update, 'b'), $this->row(5, RowAction::Create, 'c')],
            [new RowRejection(4, ['x'], [new CellError('email', Reason::NotAnEmail)])],
        );

        self::assertSame(2, $plan->creates());
        self::assertSame(1, $plan->updates());
        self::assertSame(1, $plan->refusals());
    }

    public function testThePreviewIsInTheOrderOfTheFile(): void
    {
        $error = new CellError('email', Reason::NotAnEmail);
        $plan = self::plan([$this->row(5, RowAction::Create, 'c'), $this->row(2, RowAction::Update, 'a')], [new RowRejection(3, ['x'], [$error])]);

        self::assertEquals([
            new PreviewLine(2, RowAction::Update),
            new PreviewLine(3, RowAction::Refuse, [$error]),
            new PreviewLine(5, RowAction::Create),
        ], $plan->preview());
        self::assertSame([], new PreviewLine(1, RowAction::Create)->errors);
    }

    public function testItDescribesAnErrorWithTheHeaderOfTheFile(): void
    {
        $plan = self::plan([]);

        self::assertSame('Email: is not an email address', $plan->describe(new CellError('email', Reason::NotAnEmail)));
        self::assertSame('given_name: is required', $plan->describe(new CellError('given_name', Reason::Required)));
        self::assertSame('Row: repeats the key of another row (line 2)', $plan->describe(new CellError(null, Reason::DuplicateKey, null, 2)));
        self::assertSame('Row: is refused — the unit is closed', $plan->describe(CellError::refusedByTarget(null, 'the unit is closed')));
        self::assertSame('Email: is refused (line 4) — closed', $plan->describe(new CellError('email', Reason::RefusedByTarget, 'closed', 4)));
    }

    public function testTheFingerprintFollowsWhatThePlanDoes(): void
    {
        $date = new DateTimeImmutable('2026-11-19');
        $base = self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'a', 'on' => $date, 'x' => null])]);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $base->fingerprint);
        self::assertSame($base->fingerprint, self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'a', 'on' => new DateTimeImmutable('2026-11-19'), 'x' => null])])->fingerprint);

        $others = [
            self::plan([$this->row(2, RowAction::Update, 'a', ['email' => 'a', 'on' => $date, 'x' => null])]),
            self::plan([$this->row(3, RowAction::Create, 'a', ['email' => 'a', 'on' => $date, 'x' => null])]),
            self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'b', 'on' => $date, 'x' => null])]),
            self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'a', 'on' => new DateTimeImmutable('2026-11-20'), 'x' => null])]),
            self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'a', 'on' => $date, 'x' => ''])]),
            self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'a', 'on' => $date, 'x' => null])], [new RowRejection(3, [], [new CellError('email', Reason::Required)])]),
            self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'a', 'on' => $date, 'x' => null])], mapping: new ColumnMapping([['header' => 'Mail', 'column' => 'email']])),
            self::plan([$this->row(2, RowAction::Create, 'a', ['email' => 'a', 'on' => $date, 'x' => null])], headers: ['Email', 'Notes']),
        ];

        foreach ($others as $other) {
            self::assertNotSame($base->fingerprint, $other->fingerprint);
        }

        $rejected = static fn (CellError $error): string => self::plan([], [new RowRejection(3, [], [$error])])->fingerprint;
        $reference = $rejected(new CellError('email', Reason::Required, null, 2));

        self::assertNotSame($reference, $rejected(new CellError('given_name', Reason::Required, null, 2)));
        self::assertNotSame($reference, $rejected(new CellError('email', Reason::TooLong, null, 2)));
        self::assertNotSame($reference, $rejected(new CellError('email', Reason::Required, null, 3)));
        self::assertNotSame($reference, self::plan([], [new RowRejection(4, [], [new CellError('email', Reason::Required, null, 2)])])->fingerprint);
    }

    public function testAPlannedRowTellsTheColumnsTheFileMaps(): void
    {
        $row = $this->row(2, RowAction::Create, 'a', ['email' => 'a', 'unit_code' => null]);

        self::assertTrue($row->has('unit_code'));
        self::assertNull($row->value('unit_code'));
        self::assertFalse($row->has('locale'));
        self::assertNull($row->value('locale'));
        self::assertSame('a', $row->value('email'));
    }

    public function testANaturalKeyIsOneText(): void
    {
        $key = new NaturalKey(['été/a', 'b"c']);

        self::assertSame('["été/a","b\"c"]', $key->toString());
        self::assertTrue($key->equals(new NaturalKey(['été/a', 'b"c'])));
        self::assertFalse($key->equals(new NaturalKey(['été/a'])));
        self::assertNotSame(new NaturalKey(['a,b'])->toString(), new NaturalKey(['a', 'b'])->toString());
    }

    public function testTheFingerprintIsTheHashOfTheTextsOfTheProfileHeadersRowsAndRejections(): void
    {
        $mapping = new ColumnMapping([['header' => 'Email', 'column' => 'email']]);
        $plan = self::plan(
            [$this->row(2, RowAction::Create, 'a', ['n' => 12, 'ok' => true, 'on' => new DateTimeImmutable('2026-11-19'), 'none' => null, 'name' => 'x'])],
            [new RowRejection(3, ['b'], [new CellError('email', Reason::NotAnEmail, 'detail', 2)])],
            $mapping,
        );

        $expected = hash('sha256', json_encode([
            LmsProfiles::people()->withMapping($mapping)->toArray(),
            ['Email'],
            [[2, 'create', ['n' => '12', 'ok' => '1', 'on' => '2026-11-19', 'none' => null, 'name' => 'x']]],
            [[3, [['email', 'not-an-email', 2]]]],
        ], \JSON_THROW_ON_ERROR));

        self::assertSame($expected, $plan->fingerprint);
    }
}
