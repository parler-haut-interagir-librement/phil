<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns of each table as the schema declares them: name, type, required, length, fixed length, autoincrement.
 */
#[CoversClass(ConversationTables::class)]
final class ConversationSchemaTest extends TestCase
{
    public function testEachColumnHasItsTypeItsLengthAndWhetherItIsRequired(): void
    {
        $schema = ConversationTables::configure(Schema::editor()->create());

        self::assertSame([
            'phil_conversation' => [
                'id:string:1:36:1:0',
                'kind:string:1:8:0:0',
                'direct_key:string:0:64:1:0',
                'subject:text:0::0:0',
                'subject_key:string:0:190:0:0',
                'started_at:datetime_utc_immutable:1::0:0',
                'last_message_at:datetime_utc_immutable:1::0:0',
                'last_sequence:bigint:1::0:0',
            ],
            'phil_conversation_participant' => [
                'conversation_id:string:1:36:1:0',
                'participant:string:1:190:0:0',
                'joined_at:datetime_utc_immutable:1::0:0',
                'read_up_to:bigint:1::0:0',
                'muted:boolean:1::0:0',
                'left_at:datetime_utc_immutable:0::0:0',
            ],
            'phil_conversation_message' => [
                'seq:bigint:1::0:1',
                'id:string:1:36:1:0',
                'conversation_id:string:1:36:1:0',
                'author:string:0:190:0:0',
                'body:text:0::0:0',
                'body_key:string:0:190:0:0',
                'sent_at:datetime_utc_immutable:1::0:0',
            ],
            'phil_conversation_reception' => [
                'participant:string:1:190:0:0',
                'who_can_write:string:1:16:0:0',
            ],
            'phil_conversation_block' => [
                'participant:string:1:190:0:0',
                'blocked:string:1:190:0:0',
            ],
        ], $this->columns($schema));
    }

    /**
     * @return array<string, list<string>>
     */
    private function columns(Schema $schema): array
    {
        $tables = [];

        foreach ($schema->getTables() as $table) {
            $tables[$table->getObjectName()->toString()] = array_map(
                static fn (Column $column): string => \sprintf(
                    '%s:%s:%s:%s:%s:%s',
                    $column->getObjectName()->toString(),
                    $column->getTypeName(),
                    $column->getNotnull() ? '1' : '0',
                    $column->getLength() ?? '',
                    $column->getFixed() ? '1' : '0',
                    $column->getAutoincrement() ? '1' : '0',
                ),
                $table->getColumns(),
            );
        }

        return $tables;
    }
}
