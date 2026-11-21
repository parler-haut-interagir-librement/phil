<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Lms;

use Phil\ImportWizard\Profile\Column;
use Phil\ImportWizard\Profile\ColumnType;
use Phil\ImportWizard\Profile\ImportProfile;
use Phil\ImportWizard\Profile\Reference;
use Phil\ImportWizard\Rule\DateOrder;

/**
 * The three CSV files of a learning platform: the people, their enrolments in courses, and the tree of the
 * organisational units they belong to. Each profile reads the headers of its template and the usual English and French
 * headers of the files exported elsewhere.
 */
final class LmsProfiles
{
    public const string PERSON = 'person';

    public const string ENROLMENT = 'enrolment';

    public const string UNIT = 'organisational-unit';

    public const string COURSE = 'course';

    public const array ROLES = ['learner', 'instructor', 'observer'];

    private function __construct()
    {
    }

    /**
     * People, by email address: their names, an identifier of another system, a locale and the code of their unit.
     */
    public static function people(): ImportProfile
    {
        return new ImportProfile(self::PERSON, [
            new Column('email', ColumnType::Email, true, aliases: ['email address', 'mail', 'courriel', 'adresse courriel'], maxLength: 254),
            new Column('given_name', required: true, aliases: ['first name', 'given name', 'prénom'], maxLength: 100),
            new Column('family_name', required: true, aliases: ['last name', 'surname', 'family name', 'nom', 'nom de famille'], maxLength: 100),
            new Column('external_id', aliases: ['id', 'identifier', 'employee number', 'student number', 'matricule', 'identifiant'], maxLength: 64),
            new Column('locale', aliases: ['language', 'langue'], maxLength: 35),
            new Column('unit_code', aliases: ['unit', 'department', 'unité', 'service', 'code unité'], maxLength: 64, reference: new Reference(self::UNIT)),
        ], ['email']);
    }

    /**
     * Enrolments, by person and course: the role in the course and the dates it starts and ends, the end never before
     * the start.
     */
    public static function enrolments(): ImportProfile
    {
        return new ImportProfile(self::ENROLMENT, [
            new Column('person_email', ColumnType::Email, true, aliases: ['email', 'courriel', 'learner email'], maxLength: 254, reference: new Reference(self::PERSON)),
            new Column('course_code', required: true, aliases: ['course', 'cours', 'code cours', 'code du cours'], maxLength: 64, reference: new Reference(self::COURSE)),
            new Column('role', choices: self::ROLES),
            new Column('starts_on', ColumnType::Date, aliases: ['start', 'start date', 'début', 'date de début']),
            new Column('ends_on', ColumnType::Date, aliases: ['end', 'end date', 'fin', 'date de fin']),
        ], ['person_email', 'course_code'], [new DateOrder('starts_on', 'ends_on')]);
    }

    /**
     * The tree of organisational units, by code: each unit's name and the code of its parent, in the file or already
     * known, a unit without a parent at the root.
     */
    public static function organisationalUnits(): ImportProfile
    {
        return new ImportProfile(self::UNIT, [
            new Column('code', required: true, aliases: ['unit code', 'code unité', 'id'], maxLength: 64),
            new Column('name', required: true, aliases: ['unit name', 'unit', 'nom', 'unité'], maxLength: 200),
            new Column('parent_code', aliases: ['parent', 'parent unit', 'code parent', 'unité parente'], maxLength: 64, reference: new Reference(self::UNIT)),
        ], ['code']);
    }

    /**
     * @return list<ImportProfile>
     */
    public static function all(): array
    {
        return [self::organisationalUnits(), self::people(), self::enrolments()];
    }
}
