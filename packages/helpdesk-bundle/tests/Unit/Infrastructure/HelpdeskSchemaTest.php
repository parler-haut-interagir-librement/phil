<?php

declare(strict_types=1);

namespace Phil\HelpdeskBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Phil\HelpdeskBundle\Infrastructure\Doctrine\HelpdeskTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The columns of the ticket table as the schema declares them: name, type, required, length, fixed length.
 */
#[CoversClass(HelpdeskTables::class)]
final class HelpdeskSchemaTest extends TestCase
{
    public function testEachColumnHasItsTypeItsLengthAndWhetherItIsRequired(): void
    {
        $columns = array_map(
            static fn (Column $column): string => \sprintf(
                '%s:%s:%s:%s:%s',
                $column->getObjectName()->toString(),
                $column->getTypeName(),
                $column->getNotnull() ? '1' : '0',
                $column->getLength() ?? '',
                $column->getFixed() ? '1' : '0',
            ),
            HelpdeskTables::configure(Schema::editor()->create())->getTable(HelpdeskTables::TICKET)->getColumns(),
        );

        self::assertSame([
            'conversation_id:string:1:36:1',
            'queue:string:1:64:0',
            'status:string:1:16:0',
            'requester:string:0:190:0',
            'assignee:string:0:190:0',
            'opened_at:datetime_utc_immutable:1::0',
            'updated_at:datetime_utc_immutable:1::0',
            'closed_at:datetime_utc_immutable:0::0',
        ], $columns);
    }
}
