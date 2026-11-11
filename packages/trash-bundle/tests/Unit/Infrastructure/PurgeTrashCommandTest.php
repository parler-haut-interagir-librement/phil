<?php

declare(strict_types=1);

namespace Phil\TrashBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Phil\TrashBundle\Application\Trash;
use Phil\TrashBundle\Domain\Model\TrashItem;
use Phil\TrashBundle\Infrastructure\Console\PurgeTrashCommand;
use Phil\TrashBundle\Tests\Unit\Fixtures\InMemoryTrash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PurgeTrashCommand::class)]
final class PurgeTrashCommandTest extends TestCase
{
    public function testTheExpiredItemsArePurged(): void
    {
        $items = new InMemoryTrash();

        foreach (['1', '2', '3'] as $id) {
            $items->save(TrashItem::of(ResourceReference::of('post', $id), 'Post', [], null, new DateTimeImmutable('2026-01-01 UTC'), null, new DateTimeImmutable('2026-02-01 UTC')));
        }

        $command = new PurgeTrashCommand(new Trash($items, [], new NativeClock()));
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['--limit' => '2']));
        self::assertSame("2 resource(s) purged.\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, new CommandTester($command)->execute([]));
        self::assertSame([], $items->items);
        self::assertSame(['phil:trash:purge', 'Purges the trashed resources whose retention is over', '500'], [$command->getName(), $command->getDescription(), $command->getDefinition()->getOption('limit')->getDefault()]);

        foreach (['0', 'x', '1234567', '01', "5\n"] as $limit) {
            $invalid = new CommandTester($command);
            self::assertSame(Command::INVALID, $invalid->execute(['--limit' => $limit]), $limit);
            self::assertSame("The limit is a whole number from 1 to 999999.\n", $invalid->getDisplay());
        }

        self::assertSame(Command::SUCCESS, new CommandTester($command)->execute(['--limit' => '999999']));
    }
}
