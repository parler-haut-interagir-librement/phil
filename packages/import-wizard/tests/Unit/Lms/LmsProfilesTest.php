<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Lms;

use Phil\ImportWizard\Csv\CsvRowSource;
use Phil\ImportWizard\Lms\LmsProfiles;
use Phil\ImportWizard\Plan\ImportPlanner;
use Phil\ImportWizard\Plan\PlannedRow;
use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnMapping;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Rule\DateOrder;
use Phil\ImportWizard\Tests\Support\MemoryTarget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(LmsProfiles::class)]
final class LmsProfilesTest extends TestCase
{
    /**
     * @param list<Column> $columns
     *
     * @return array<string, array{string, bool, int|null, string|null, list<string>}>
     */
    private function describe(array $columns): array
    {
        $described = [];

        foreach ($columns as $column) {
            $described[$column->name] = [$column->type->value, $column->required, $column->maxLength, $column->reference?->type, $column->choices];
        }

        return $described;
    }

    public function testThePeopleFormat(): void
    {
        $profile = LmsProfiles::people();

        self::assertSame('person', $profile->type);
        self::assertSame(['email'], $profile->naturalKey);
        self::assertSame([
            'email' => ['email', true, 254, null, []],
            'given_name' => ['text', true, 100, null, []],
            'family_name' => ['text', true, 100, null, []],
            'external_id' => ['text', false, 64, null, []],
            'locale' => ['text', false, 35, null, []],
            'unit_code' => ['text', false, 64, 'organisational-unit', []],
        ], $this->describe($profile->columns));
        self::assertSame([], $profile->rules);
    }

    public function testTheEnrolmentsFormat(): void
    {
        $profile = LmsProfiles::enrolments();

        self::assertSame('enrolment', $profile->type);
        self::assertSame(['person_email', 'course_code'], $profile->naturalKey);
        self::assertSame([
            'person_email' => ['email', true, 254, 'person', []],
            'course_code' => ['text', true, 64, 'course', []],
            'role' => ['text', false, null, null, ['learner', 'instructor', 'observer']],
            'starts_on' => ['date', false, null, null, []],
            'ends_on' => ['date', false, null, null, []],
        ], $this->describe($profile->columns));
        self::assertEquals([new DateOrder('starts_on', 'ends_on')], $profile->rules);
    }

    public function testTheUnitsFormat(): void
    {
        $profile = LmsProfiles::organisationalUnits();

        self::assertSame('organisational-unit', $profile->type);
        self::assertSame(['code'], $profile->naturalKey);
        self::assertSame([
            'code' => ['text', true, 64, null, []],
            'name' => ['text', true, 200, null, []],
            'parent_code' => ['text', false, 64, 'organisational-unit', []],
        ], $this->describe($profile->columns));
    }

    public function testAllFormatsAreListedUnitsFirst(): void
    {
        self::assertSame(['organisational-unit', 'person', 'enrolment'], array_map(static fn (ImportProfile $profile): string => $profile->type, LmsProfiles::all()));
        self::assertSame(['learner', 'instructor', 'observer'], LmsProfiles::ROLES);
        self::assertSame('course', LmsProfiles::COURSE);
    }

    public function testFrenchHeadersAreRecognised(): void
    {
        $csv = "Courriel,Prénom,Nom de famille,Matricule,Langue,Unité\nana@example.org,Ana,Lee,E-1,fr-CA,sci\n";
        $target = new MemoryTarget('person', ['organisational-unit' => ['["sci"]']]);
        $plan = new ImportPlanner()->plan(CsvRowSource::fromString($csv), LmsProfiles::people(), $target);

        self::assertSame(['email' => 'ana@example.org', 'given_name' => 'Ana', 'family_name' => 'Lee', 'external_id' => 'E-1', 'locale' => 'fr-CA', 'unit_code' => 'sci'], $plan->rows[0]->values);
    }

    public function testEnglishHeadersOfOtherPlatformsAreRecognised(): void
    {
        $csv = "Learner email,Course,Role,Start date,End date\nana@example.org,c1,Instructor,2026-11-01,2026-12-01\n";
        $target = new MemoryTarget('enrolment', ['person' => ['["ana@example.org"]'], 'course' => ['["c1"]']]);
        $plan = new ImportPlanner()->plan(CsvRowSource::fromString($csv), LmsProfiles::enrolments(), $target);
        $row = $plan->rows[0];

        self::assertInstanceOf(PlannedRow::class, $row);
        self::assertSame('instructor', $row->value('role'));
        self::assertSame(ColumnType::Date, LmsProfiles::enrolments()->column('starts_on')?->type);
        self::assertSame('2026-12-01', ColumnType::text($row->value('ends_on') ?? ''));
    }

    public function testUnitHeadersAreRecognised(): void
    {
        $plan = new ImportPlanner()->plan(CsvRowSource::fromString("Code unité;Nom;Unité parente\nsci;Sciences;\n"), LmsProfiles::organisationalUnits(), new MemoryTarget('organisational-unit'));

        self::assertSame(['code' => 'sci', 'name' => 'Sciences', 'parent_code' => null], $plan->rows[0]->values);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function headers(): iterable
    {
        $aliases = [
            'people' => [
                'email' => ['E-mail', 'email address', 'mail', 'courriel', 'adresse courriel'],
                'given_name' => ['first name', 'FirstName', 'given name', 'prénom'],
                'family_name' => ['last name', 'LastName', 'surname', 'family name', 'nom', 'nom de famille'],
                'external_id' => ['id', 'identifier', 'employee number', 'student number', 'matricule', 'identifiant'],
                'locale' => ['language', 'langue'],
                'unit_code' => ['unit', 'department', 'unité', 'service', 'code unité'],
            ],
            'enrolments' => [
                'person_email' => ['email', 'E-mail', 'courriel', 'learner email'],
                'course_code' => ['course', 'cours', 'code cours', 'code du cours'],
                'role' => ['rôle'],
                'starts_on' => ['start', 'start date', 'début', 'date de début'],
                'ends_on' => ['end', 'end date', 'fin', 'date de fin'],
            ],
            'organisationalUnits' => [
                'code' => ['unit code', 'code unité', 'id'],
                'name' => ['unit name', 'unit', 'nom', 'unité'],
                'parent_code' => ['parent', 'parent unit', 'code parent', 'unité parente'],
            ],
        ];

        foreach ($aliases as $profile => $columns) {
            foreach ($columns as $column => $headers) {
                foreach ($headers as $header) {
                    yield $profile.' '.$column.' '.$header => [$profile, $column, $header];
                }
            }
        }
    }

    #[DataProvider('headers')]
    public function testEachWayOfWritingAHeaderIsRecognised(string $profile, string $column, string $header): void
    {
        $profile = match ($profile) {
            'people' => LmsProfiles::people(),
            'enrolments' => LmsProfiles::enrolments(),
            default => LmsProfiles::organisationalUnits(),
        };

        self::assertSame($column, ColumnMapping::guess([$header], $profile)->toArray()[0]['column'] ?? null);
    }
}
