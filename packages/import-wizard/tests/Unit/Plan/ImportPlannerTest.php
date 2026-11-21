<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Plan;

use DateTimeImmutable;
use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Exception\MappingIncomplete;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\ImportPlan;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Plan\NaturalKey;
use Phil\ImportWizard\Plan\PlannedRow;
use Phil\ImportWizard\Plan\Planning;
use Phil\ImportWizard\Plan\RowAction;
use Phil\ImportWizard\Plan\RowRejection;
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnMapping;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Profile\Reference;
use Phil\ImportWizard\Rule\DateOrder;
use Phil\ImportWizard\Tests\Support\MemoryTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImportPlanner::class)]
#[CoversClass(Planning::class)]
final class ImportPlannerTest extends TestCase
{
    private function plan(string $csv, ImportProfile $profile, MemoryTarget $target, ?ColumnMapping $mapping = null): ImportPlan
    {
        return new ImportPlanner()->plan(CsvRowSource::fromString($csv), $profile, $target, $mapping);
    }

    /**
     * @return array<int, list<array{string|null, string, int|null, string|null}>>
     */
    private function errors(ImportPlan $plan): array
    {
        $errors = [];

        foreach ($plan->rejections as $rejection) {
            $errors[$rejection->line] = array_map(static fn (CellError $error): array => [$error->column, $error->reason->value, $error->relatedLine, $error->detail], $rejection->errors);
        }

        return $errors;
    }

    /**
     * @return list<array{int, string}>
     */
    private function actions(ImportPlan $plan): array
    {
        return array_map(static fn (PlannedRow $row): array => [$row->line, $row->action->value], $plan->rows);
    }

    public function testItPlansCreatesAndUpdatesByNaturalKey(): void
    {
        $target = new MemoryTarget('person', ['person' => ['["bo@example.org"]']]);
        $plan = $this->plan("Email,First name,Last name\nana@example.org,Ana,Lee\nBO@example.org,Bo,Ng\n", LmsProfiles::people(), $target);

        self::assertSame([[2, 'create'], [3, 'update']], $this->actions($plan));
        self::assertSame([], $plan->rejections);
        self::assertSame(['ana@example.org', 'Ana', 'Lee'], $plan->rows[0]->cells);
        self::assertSame(['email' => 'ana@example.org', 'given_name' => 'Ana', 'family_name' => 'Lee'], $plan->rows[0]->values);
        self::assertEquals(new NaturalKey(['bo@example.org']), $plan->rows[1]->key);
        self::assertSame([['person', ['["ana@example.org"]', '["bo@example.org"]']]], $target->lookups);
        self::assertSame([], $target->batches);
    }

    public function testAnEmptyCellOfAMappedColumnIsNullAndAnUnmappedColumnIsAbsent(): void
    {
        $plan = $this->plan("Email,First name,Last name,Unit\nana@example.org,Ana,Lee,\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertTrue($plan->rows[0]->has('unit_code'));
        self::assertNull($plan->rows[0]->value('unit_code'));
        self::assertFalse($plan->rows[0]->has('locale'));
    }

    public function testItSkipsBlankRowsAndKeepsThePhysicalLines(): void
    {
        $plan = $this->plan("Email,First name,Last name\n\n,,\n\"ana@example.org\",\"Ana\nMarie\",Lee\nbo@example.org,Bo,Ng\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([[4, 'create'], [6, 'create']], $this->actions($plan));
    }

    public function testItRefusesEachCellWithItsReason(): void
    {
        $csv = "Email,First name,Last name,Locale\nnot-an-email,,".str_repeat('x', 101).",fr\n";
        $plan = $this->plan($csv, LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([2 => [
            ['email', 'not-an-email', null, null],
            ['given_name', 'required', null, null],
            ['family_name', 'too-long', null, 'at most 100 characters'],
        ]], $this->errors($plan));
        self::assertSame(['not-an-email', '', str_repeat('x', 101), 'fr'], $plan->rejections[0]->cells);
        self::assertSame([], $plan->rows);
    }

    public function testAChoiceRefusedNamesTheAllowedValues(): void
    {
        $target = new MemoryTarget('enrolment', ['person' => ['["ana@example.org"]'], 'course' => ['["c1"]']]);
        $plan = $this->plan("Email,Course,Role\nana@example.org,c1,admin\n", LmsProfiles::enrolments(), $target);

        self::assertSame([2 => [['role', 'not-allowed', null, 'learner, instructor, observer']]], $this->errors($plan));
    }

    public function testTheRulesRunOnlyOnARowWhoseCellsAreRead(): void
    {
        $target = new MemoryTarget('enrolment', ['person' => ['["ana@example.org"]', '["bo@example.org"]'], 'course' => ['["c1"]']]);
        $plan = $this->plan("Email,Course,Start,End\nana@example.org,c1,2026-12-01,2026-11-01\nbo@example.org,c1,x,2026-11-01\nana@example.org,c2,2026-11-01,2026-11-01\n", LmsProfiles::enrolments(), $target);

        self::assertSame([
            2 => [['ends_on', 'date-order', null, 'starts_on']],
            3 => [['starts_on', 'not-a-date', null, null]],
            4 => [['course_code', 'unknown-reference', null, 'course']],
        ], $this->errors($plan));
    }

    public function testTheRulesDoNotRunOnARowWithAnError(): void
    {
        $target = new MemoryTarget('enrolment', ['person' => ['["ana@example.org"]'], 'course' => ['["c1"]']]);
        $plan = self::plan("Email,Course,Role,Start,End\nana@example.org,c1,admin,2026-12-01,2026-11-01\n", LmsProfiles::enrolments(), $target);

        self::assertSame([2 => [['role', 'not-allowed', null, 'learner, instructor, observer']]], self::errors($plan));
    }

    public function testAReferenceToAnotherTypeIsLookedUpEvenWhenItsKeyIsOneOfTheFile(): void
    {
        $profile = new ImportProfile('thing', [new Column('code', required: true), new Column('twin', reference: new Reference('other'))], ['code']);
        $target = new MemoryTarget('thing', ['other' => ['["a"]']]);
        $plan = self::plan("code,twin\na,a\nb,a\nc,b\n", $profile, $target);

        self::assertSame([[2, 'create'], [3, 'create']], self::actions($plan));
        self::assertSame([], $plan->rows[1]->dependsOn);
        self::assertSame([4 => [['twin', 'unknown-reference', null, 'other']]], self::errors($plan));
    }

    public function testARowKeepsItsOwnErrorsOverARefusedReference(): void
    {
        $profile = new ImportProfile('unit', [
            new Column('code', required: true),
            new Column('parent', reference: new Reference('unit')),
            new Column('other', reference: new Reference('unit')),
        ], ['code']);
        $plan = self::plan("code,parent,other\na,nowhere,\nb,a,elsewhere\n", $profile, new MemoryTarget('unit'));

        self::assertSame([
            2 => [['parent', 'unknown-reference', null, 'unit']],
            3 => [['other', 'unknown-reference', null, 'unit']],
        ], self::errors($plan));
    }

    public function testARowWithMoreCellsThanTheHeaderIsRefusedUnlessTheyAreBlank(): void
    {
        $plan = $this->plan("Email,First name,Last name\nana@example.org,Ana,Lee, \nbo@example.org,Bo,Ng,x\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([[2, 'create']], $this->actions($plan));
        self::assertSame([3 => [[null, 'too-many-cells', null, null]]], $this->errors($plan));
    }

    public function testAMalformedRowIsRefused(): void
    {
        $plan = $this->plan("Email,First name,Last name\n\"ana@example.org\" x,Ana,Lee\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([2 => [[null, 'malformed-row', null, null], ['email', 'not-an-email', null, null]]], $this->errors($plan));
    }

    public function testAShortRowReadsItsMissingCellsAsEmpty(): void
    {
        $plan = $this->plan("Email,First name,Last name\nana@example.org,Ana\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([2 => [['family_name', 'required', null, null]]], $this->errors($plan));
    }

    public function testCellsAreReadTrimmed(): void
    {
        $plan = $this->plan("Email,First name,Last name\n  ana@example.org , Ana ,Lee\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame('ana@example.org', $plan->rows[0]->value('email'));
        self::assertSame('Ana', $plan->rows[0]->value('given_name'));
    }

    public function testASecondRowWithTheSameKeyIsRefused(): void
    {
        $plan = $this->plan("Email,First name,Last name\nana@example.org,Ana,Lee\nbad,X,Y\nANA@example.org,Ana,Lee\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([[2, 'create']], $this->actions($plan));
        self::assertSame([3 => [['email', 'not-an-email', null, null]], 4 => [[null, 'duplicate-key', 2, null]]], $this->errors($plan));
    }

    public function testAKeyOfTwoColumnsIsReadInTheOrderOfTheProfile(): void
    {
        $target = new MemoryTarget('enrolment', ['person' => ['["ana@example.org"]'], 'course' => ['["c1"]'], 'enrolment' => ['["ana@example.org","c1"]']]);
        $plan = $this->plan("Course,Email\nc1,ana@example.org\n", LmsProfiles::enrolments(), $target);

        self::assertEquals(new NaturalKey(['ana@example.org', 'c1']), $plan->rows[0]->key);
        self::assertSame([[2, 'update']], $this->actions($plan));
    }

    public function testTypedValuesMakeTheKey(): void
    {
        $profile = new ImportProfile('day', [new Column('on', ColumnType::Date, true), new Column('open', ColumnType::Boolean, true), new Column('n', ColumnType::Integer)], ['on', 'open']);
        $plan = $this->plan("on,open,n\n2026-11-19,oui,\n2026-11-19,1,3\n", $profile, new MemoryTarget('day'));

        self::assertEquals(new NaturalKey(['2026-11-19', '1']), $plan->rows[0]->key);
        self::assertEquals(new DateTimeImmutable('2026-11-19 00:00 UTC'), $plan->rows[0]->value('on'));
        self::assertSame([3 => [[null, 'duplicate-key', 2, null]]], $this->errors($plan));
    }

    public function testItRefusesAFileThatLeavesARequiredColumnUnmapped(): void
    {
        try {
            $this->plan("Email,Notes\nana@example.org,x\n", LmsProfiles::people(), new MemoryTarget('person'));
            self::fail('A file without the required columns was planned.');
        } catch (MappingIncomplete $mappingIncomplete) {
            self::assertSame(['given_name', 'family_name'], $mappingIncomplete->columns);
            self::assertSame('No header is mapped to the required columns: given_name, family_name.', $mappingIncomplete->getMessage());
        }
    }

    public function testTheMappingGivenWinsOverTheProfileAndTheGuess(): void
    {
        $profile = LmsProfiles::people()->withMapping(new ColumnMapping([['header' => 'A', 'column' => 'email']]));
        $mapping = new ColumnMapping([['header' => 'B', 'column' => 'email']]);
        $plan = $this->plan("A,B,First name,Last name\nana@example.org,bo@example.org,Bo,Ng\n", $profile, new MemoryTarget('person'), $mapping);

        self::assertSame('bo@example.org', $plan->rows[0]->value('email'));
        self::assertSame('B', $plan->mapping->headerOf('email'));
        self::assertSame('First name', $plan->mapping->headerOf('given_name'));
    }

    public function testTheProfileMappingWinsOverTheGuess(): void
    {
        $profile = LmsProfiles::people()->withMapping(new ColumnMapping([['header' => 'A', 'column' => 'email']]));
        $plan = $this->plan("Email,A,First name,Last name\nana@example.org,bo@example.org,Bo,Ng\n", $profile, new MemoryTarget('person'));

        self::assertSame('bo@example.org', $plan->rows[0]->value('email'));
    }

    public function testAUnitTreeIsWrittenParentsFirstWhateverTheOrderOfTheFile(): void
    {
        $csv = "Code,Name,Parent\nmath,Mathematics,sci\nsci,Sciences,\nalg,Algebra,math\ngeo,Geography,root\n";
        $target = new MemoryTarget('organisational-unit', ['organisational-unit' => ['["root"]']]);
        $plan = $this->plan($csv, LmsProfiles::organisationalUnits(), $target);

        self::assertSame([[3, 'create'], [2, 'create'], [4, 'create'], [5, 'create']], $this->actions($plan));
        self::assertSame(['parent_code' => 3], $plan->rows[1]->dependsOn);
        self::assertSame(['parent_code' => 2], $plan->rows[2]->dependsOn);
        self::assertSame([], $plan->rows[3]->dependsOn);
        self::assertSame([], $plan->rows[0]->dependsOn);
        self::assertSame([['organisational-unit', ['["root"]', '["math"]', '["sci"]', '["alg"]', '["geo"]']]], $target->lookups);
    }

    public function testAUnitThatIsItsOwnParentIsRefused(): void
    {
        $plan = $this->plan("Code,Name,Parent\nsci,Sciences,sci\nmath,Mathematics,sci\n", LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame([2 => [['parent_code', 'self-reference', null, null]], 3 => [['parent_code', 'reference-rejected', 2, null]]], $this->errors($plan));
        self::assertSame([], $plan->rows);
    }

    public function testACycleOfUnitsIsRefusedAndWhatHangsFromIt(): void
    {
        $csv = "Code,Name,Parent\na,A,c\nb,B,a\nc,C,b\nd,D,b\ne,E,\n";
        $plan = $this->plan($csv, LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame([
            2 => [['parent_code', 'reference-cycle', 4, null]],
            3 => [['parent_code', 'reference-cycle', 2, null]],
            4 => [['parent_code', 'reference-cycle', 3, null]],
            5 => [['parent_code', 'reference-rejected', 3, null]],
        ], $this->errors($plan));
        self::assertSame([[6, 'create']], $this->actions($plan));
    }

    public function testTwoCyclesSharingAUnitAreEachRefusedOnce(): void
    {
        $profile = new ImportProfile('unit', [
            new Column('code', required: true),
            new Column('parent', reference: new Reference('unit')),
            new Column('other', reference: new Reference('unit')),
        ], ['code']);
        $plan = $this->plan("code,parent,other\na,b,c\nb,a,\nc,a,\n", $profile, new MemoryTarget('unit'));

        self::assertSame([
            2 => [['parent', 'reference-cycle', 3, null], ['other', 'reference-cycle', 4, null]],
            3 => [['parent', 'reference-cycle', 2, null]],
            4 => [['parent', 'reference-cycle', 2, null]],
        ], $this->errors($plan));
    }

    public function testAChainLongerThanTheStackOfPhpIsOrdered(): void
    {
        $lines = ['Code,Name,Parent'];

        for ($i = 20_000; $i > 0; --$i) {
            $lines[] = 'u'.$i.',U,'.(1 === $i ? '' : 'u'.($i - 1));
        }

        $plan = $this->plan(implode("\n", $lines), LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertCount(20_000, $plan->rows);
        self::assertSame(20_001, $plan->rows[0]->line);
        self::assertSame(2, $plan->rows[19_999]->line);
    }

    public function testAReferenceToARefusedRowIsRefusedInTurn(): void
    {
        $plan = $this->plan("Code,Name,Parent\nsci,,\nmath,Mathematics,sci\n", LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame([2 => [['name', 'required', null, null]], 3 => [['parent_code', 'unknown-reference', null, 'organisational-unit']]], $this->errors($plan));
    }

    public function testAReferenceToARowRefusedForItsReferenceIsRefusedInTurn(): void
    {
        $plan = $this->plan("Code,Name,Parent\nsci,Sciences,nowhere\nmath,Mathematics,sci\nalg,Algebra,math\n", LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame([
            2 => [['parent_code', 'unknown-reference', null, 'organisational-unit']],
            3 => [['parent_code', 'reference-rejected', 2, null]],
            4 => [['parent_code', 'reference-rejected', 3, null]],
        ], $this->errors($plan));
    }

    public function testReferencesToAnotherTypeAreLookedUpOncePerKey(): void
    {
        $target = new MemoryTarget('enrolment', ['person' => ['["ana@example.org"]'], 'course' => ['["c1"]', '["c2"]']]);
        $plan = $this->plan("Email,Course\nana@example.org,c1\nana@example.org,c2\nbo@example.org,c1\n", LmsProfiles::enrolments(), $target);

        self::assertSame([[2, 'create'], [3, 'create']], $this->actions($plan));
        self::assertSame([4 => [['person_email', 'unknown-reference', null, 'person']]], $this->errors($plan));
        self::assertSame([
            ['person', ['["ana@example.org"]', '["bo@example.org"]']],
            ['course', ['["c1"]', '["c2"]']],
            ['enrolment', ['["ana@example.org","c1"]', '["ana@example.org","c2"]', '["bo@example.org","c1"]']],
        ], $target->lookups);
    }

    public function testLookupsAreChunked(): void
    {
        $lines = ['Code,Name'];

        for ($i = 1; $i <= Planning::LOOKUP_CHUNK + 1; ++$i) {
            $lines[] = 'u'.$i.',U';
        }

        $target = new MemoryTarget('organisational-unit');
        $this->plan(implode("\n", $lines), LmsProfiles::organisationalUnits(), $target);

        self::assertSame(500, Planning::LOOKUP_CHUNK);
        self::assertSame([500, 1], array_map(static fn (array $lookup): int => \count($lookup[1]), $target->lookups));
    }

    public function testAFileWithoutRowsPlansNothingAndLooksNothingUp(): void
    {
        $target = new MemoryTarget('person');
        $plan = $this->plan("Email,First name,Last name\n", LmsProfiles::people(), $target);

        self::assertSame([], $plan->rows);
        self::assertSame([], $plan->rejections);
        self::assertSame([], $target->lookups);
    }

    public function testRejectionsAreInTheOrderOfTheFile(): void
    {
        $plan = $this->plan("Code,Name,Parent\nb,B,a\nx,,\na,A,b\n", LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame([2, 3, 4], array_map(static fn (RowRejection $rejection): int => $rejection->line, $plan->rejections));
        self::assertSame(RowAction::Refuse, $plan->preview()[0]->action);
    }

    public function testABlankRowIsNeitherWrittenNorRefused(): void
    {
        $plan = $this->plan("Email,First name,Last name\n,,\n  , ,\n", LmsProfiles::people(), new MemoryTarget('person'));

        self::assertSame([], $plan->rows);
        self::assertSame([], $plan->rejections);
    }

    public function testEveryRuleOfTheProfileSpeaksOnARowThatBreaksSeveral(): void
    {
        $profile = new ImportProfile('span', [
            new Column('code', required: true),
            new Column('starts_on', ColumnType::Date),
            new Column('ends_on', ColumnType::Date),
        ], ['code'], [new DateOrder('starts_on', 'ends_on'), new DateOrder('starts_on', 'ends_on')]);
        $plan = $this->plan("Code,Starts on,Ends on\na,2026-11-19,2026-11-01\n", $profile, new MemoryTarget('span'));

        self::assertCount(2, $plan->rejections[0]->errors);
    }

    public function testEveryRepeatOfAKeyPointsToTheFirstLineOfIt(): void
    {
        $plan = $this->plan("Code,Name\na,A\na,B\na,C\n", LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame([3 => [[null, 'duplicate-key', 2, null]], 4 => [[null, 'duplicate-key', 2, null]]], $this->errors($plan));
    }

    public function testARowReferringToACycleIsRejectedForItsParentAndNotAsPartOfTheCycle(): void
    {
        $plan = $this->plan("Code,Name,Parent\na,A,b\nb,B,c\nc,C,b\n", LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame([], $plan->rows);
        self::assertSame([
            2 => [['parent_code', 'reference-rejected', 3, null]],
            3 => [['parent_code', 'reference-cycle', 4, null]],
            4 => [['parent_code', 'reference-cycle', 3, null]],
        ], $this->errors($plan));
    }
}
