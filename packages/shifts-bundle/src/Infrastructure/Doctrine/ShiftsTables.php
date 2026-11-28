<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\ShiftsBundle\Domain\Model\GuardianContact;

/**
 * The bundle's tables, their instants in `datetime_utc_immutable` columns:
 *
 * - the shifts, by identifier, each with its definition and its places as the fields of the library's formats, its
 *   window and the revision a write compares;
 * - the places, one row per volunteer and shift with its status, rewritten with the roster, which a volunteer's page
 *   and the privacy requests search;
 * - the offers of the swap board, each as the fields of the format, with its shift, its volunteers, its status and its
 *   revision apart;
 * - the contacts of the guardians of volunteers who are minors, one per volunteer, with the consent once given;
 * - the unannounced absences, one per volunteer and shift, with the coordinator who recorded each and its lift.
 *
 * On MySQL and MariaDB the keys and identifiers are stored with the `utf8mb4_bin` collation, so that they compare byte
 * by byte, as PostgreSQL and SQLite compare them.
 */
final class ShiftsTables
{
    public const string SHIFTS = 'phil_shifts_shifts';

    public const string PLACES = 'phil_shifts_places';

    public const string SWAPS = 'phil_shifts_swaps';

    public const string GUARDIANS = 'phil_shifts_guardians';

    public const string ABSENCES = 'phil_shifts_absences';

    public const array ALL = [self::SHIFTS, self::PLACES, self::SWAPS, self::GUARDIANS, self::ABSENCES];

    private const int KEY = 64;

    private const int PERSON = 191;

    private function __construct()
    {
    }

    /**
     * Adds the tables the schema lacks.
     */
    public static function configure(Schema $schema, ?AbstractPlatform $platform = null): Schema
    {
        $binary = $platform instanceof AbstractMySQLPlatform;
        $editor = $schema->edit();

        foreach (self::tables($binary) as $name => $table) {
            if (!$schema->hasTable($name)) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return array<string, Table>
     */
    private static function tables(bool $binary): array
    {
        return [
            self::SHIFTS => self::table(self::SHIFTS, [
                self::string('shift_id', self::KEY, $binary),
                self::text('definition'),
                self::text('places'),
                self::instant('starts_at'),
                self::instant('ends_at'),
                self::integer('revision'),
                self::instant('updated_at'),
            ], ['shift_id'], ['ends' => ['ends_at'], 'starts' => ['starts_at']]),
            self::PLACES => self::table(self::PLACES, [
                self::string('shift_id', self::KEY, $binary),
                self::string('volunteer', self::PERSON, $binary),
                self::string('status', 16, $binary),
            ], ['shift_id', 'volunteer'], ['volunteer' => ['volunteer']]),
            self::SWAPS => self::table(self::SWAPS, [
                self::string('id', self::KEY, $binary),
                self::string('shift_id', self::KEY, $binary),
                self::string('offered_by', self::PERSON, $binary),
                self::string('taken_by', self::PERSON, $binary, true),
                self::string('status', 16, $binary),
                self::text('state'),
                self::instant('created_at'),
                self::integer('revision'),
            ], ['id'], ['status' => ['status', 'created_at'], 'shift' => ['shift_id'], 'offered' => ['offered_by'], 'taken' => ['taken_by']]),
            self::GUARDIANS => self::table(self::GUARDIANS, [
                self::string('volunteer', self::PERSON, $binary),
                self::string('name', GuardianContact::NAME_LENGTH, false),
                self::string('phone', GuardianContact::PHONE_LENGTH, false, true),
                self::string('email', GuardianContact::EMAIL_LENGTH, false, true),
                self::string('recorded_by', self::PERSON, $binary),
                self::instant('consented_at', true),
                self::string('consent_recorded_by', self::PERSON, $binary, true),
            ], ['volunteer'], []),
            self::ABSENCES => self::table(self::ABSENCES, [
                self::string('shift_id', self::KEY, $binary),
                self::string('volunteer', self::PERSON, $binary),
                self::instant('recorded_at'),
                self::string('recorded_by', self::PERSON, $binary),
                self::instant('lifted_at', true),
                self::string('lifted_by', self::PERSON, $binary, true),
            ], ['shift_id', 'volunteer'], ['volunteer' => ['volunteer', 'recorded_at']]),
        ];
    }

    /**
     * @param non-empty-string                                $name
     * @param list<Column>                                    $columns
     * @param non-empty-list<non-empty-string>                $primary
     * @param array<non-empty-string, list<non-empty-string>> $indexes
     */
    private static function table(string $name, array $columns, array $primary, array $indexes): Table
    {
        $built = [];

        foreach ($indexes as $suffix => $indexed) {
            $built[] = Index::editor()->setUnquotedName($name.'_'.$suffix)->setUnquotedColumnNames(...$indexed)->create();
        }

        return Table::editor()
            ->setUnquotedName($name)
            ->setColumns(...$columns)
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$primary)->create())
            ->setIndexes(...$built)
            ->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $binary, bool $nullable = false): Column
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable);

        if ($binary) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        }

        return $column->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function integer(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::INTEGER)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function text(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::TEXT)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function instant(string $name, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->setNotNull(!$nullable)->create();
    }
}
