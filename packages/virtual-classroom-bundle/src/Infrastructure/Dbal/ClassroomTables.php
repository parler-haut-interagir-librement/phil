<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Dbal;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Domain\Model\Room;

/**
 * The three tables, built for the platform of a connection, their names starting with a prefix,
 * `phil_virtual_classroom` by default: `_room`, one row per room; `_stay`, one row per stay of a participant in a
 * room, its key the room, the participant and the moment they arrived; `_recording`, one row per recording and format.
 * The identifiers compare byte for byte — `utf8mb4_bin` on MySQL and MariaDB, the `C` collation on PostgreSQL —, so
 * that `User-7` is never `user-7`. The moments are Unix timestamps.
 */
final readonly class ClassroomTables
{
    public const string PREFIX = 'phil_virtual_classroom';

    /**
     * @param non-empty-string $prefix
     */
    private function __construct(
        private AbstractPlatform $platform,
        private string $prefix,
    ) {
    }

    /**
     * @param non-empty-string $prefix
     */
    public static function for(AbstractPlatform $platform, string $prefix = self::PREFIX): self
    {
        return new self($platform, $prefix);
    }

    /**
     * @return non-empty-string
     */
    public static function rooms(string $prefix): string
    {
        return $prefix.'_room';
    }

    /**
     * @return non-empty-string
     */
    public static function stays(string $prefix): string
    {
        return $prefix.'_stay';
    }

    /**
     * @return non-empty-string
     */
    public static function recordings(string $prefix): string
    {
        return $prefix.'_recording';
    }

    /**
     * Adds the tables a schema lacks: the one the ORM generates, through `ClassroomSchemaListener`, or one a migration
     * builds.
     */
    public function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();
        $changed = false;

        foreach ([$this->roomTable(), $this->stayTable(), $this->recordingTable()] as $table) {
            if (!$schema->hasTable($table->getObjectName()->toString())) {
                $editor->addTable($table);
                $changed = true;
            }
        }

        return $changed ? $editor->create() : $schema;
    }

    public function roomTable(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::rooms($this->prefix))
            ->setColumns(
                $this->key('id', RoomId::MAX_LENGTH)->create(),
                $this->key('provider', Room::PROVIDER_LENGTH)->create(),
                Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(255)->create(),
                Column::editor()->setUnquotedName('record')->setTypeName(Types::BOOLEAN)->create(),
                Column::editor()->setUnquotedName('max_minutes')->setTypeName(Types::INTEGER)->setNotNull(false)->create(),
                $this->key('owner', Room::OWNER_LENGTH)->setNotNull(false)->create(),
                $this->key('status', 16)->create(),
                $this->key('session_key', Room::SESSION_KEY_LENGTH)->setNotNull(false)->create(),
                $this->moment('created_at')->create(),
                $this->moment('opened_at')->setNotNull(false)->create(),
                $this->moment('ended_at')->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
            ->setIndexes(
                Index::editor()->setUnquotedName(self::rooms($this->prefix).'_owner')->addUnquotedColumnName('owner')->create(),
                Index::editor()->setUnquotedName(self::rooms($this->prefix).'_status')->addUnquotedColumnName('status')->addUnquotedColumnName('created_at')->create(),
            )
            ->create();
    }

    public function stayTable(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::stays($this->prefix))
            ->setColumns(
                $this->key('room_id', RoomId::MAX_LENGTH)->create(),
                $this->key('participant_id', ParticipantId::MAX_LENGTH)->create(),
                $this->moment('joined_at')->create(),
                $this->moment('left_at')->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('room_id', 'participant_id', 'joined_at')->create())
            ->setIndexes(Index::editor()->setUnquotedName(self::stays($this->prefix).'_participant')->addUnquotedColumnName('participant_id')->create())
            ->create();
    }

    public function recordingTable(): Table
    {
        return Table::editor()
            ->setUnquotedName(self::recordings($this->prefix))
            ->setColumns(
                $this->key('room_id', RoomId::MAX_LENGTH)->create(),
                $this->key('recording_id', Recording::ID_LENGTH)->create(),
                $this->key('format', 32)->create(),
                Column::editor()->setUnquotedName('url')->setTypeName(Types::TEXT)->setNotNull(false)->create(),
                Column::editor()->setUnquotedName('published')->setTypeName(Types::BOOLEAN)->create(),
                $this->moment('started_at')->setNotNull(false)->create(),
                $this->moment('ended_at')->setNotNull(false)->create(),
                $this->moment('expires_at')->setNotNull(false)->create(),
            )
            ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('room_id', 'recording_id', 'format')->create())
            ->create();
    }

    /**
     * A column that compares byte for byte on the platform.
     *
     * @param non-empty-string $name
     */
    private function key(string $name, int $length): ColumnEditor
    {
        $column = Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length);

        if ($this->platform instanceof AbstractMySQLPlatform) {
            $column->setCharset('utf8mb4')->setCollation('utf8mb4_bin');
        } elseif ($this->platform instanceof PostgreSQLPlatform) {
            $column->setCollation('C');
        }

        return $column;
    }

    /**
     * @param non-empty-string $name
     */
    private function moment(string $name): ColumnEditor
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::BIGINT);
    }
}
