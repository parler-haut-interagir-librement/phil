<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit\Messenger;

use Phil\BulkActions\Change\ChangeSet;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Messenger\ExecuteBulkRun;
use Phil\BulkActions\Messenger\ExecuteBulkRunHandler;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Tests\Support\Fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExecuteBulkRunHandler::class)]
#[CoversClass(ExecuteBulkRun::class)]
final class ExecuteBulkRunHandlerTest extends TestCase
{
    public function testItAsksForTheNextChunkWhileTheRunHasMoreToDo(): void
    {
        $fixture = new Fixture(new Limits(chunkSize: 2));
        $fixture->products->seed(3);

        $run = $fixture->confirmer->start('ana', 'products', 'archive', Selection::identifiers(['p0001', 'p0002', 'p0003']), ChangeSet::none(), Fixture::now());
        $handler = new ExecuteBulkRunHandler($fixture->executor, $fixture->dispatcher);
        $message = new ExecuteBulkRun($run->id->value);

        $handler($message);
        self::assertSame([$run->id->value, $run->id->value], $fixture->dispatcher->dispatched);

        $handler($message);
        self::assertCount(2, $fixture->dispatcher->dispatched);
        self::assertSame('archived', $fixture->products->rows['p0003']['status']);

        $handler($message);
        self::assertCount(2, $fixture->dispatcher->dispatched, 'A message delivered again changes nothing.');
    }

    public function testAMessageCarriesTheRunIdentifierOnly(): void
    {
        self::assertSame(['run' => 'abc'], get_object_vars(new ExecuteBulkRun('abc')));

        $this->expectException(InvalidInput::class);
        $fixture = new Fixture();
        new ExecuteBulkRunHandler($fixture->executor, $fixture->dispatcher)(new ExecuteBulkRun('abc'));
    }
}
