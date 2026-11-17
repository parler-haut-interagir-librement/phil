<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

use Doctrine\DBAL\DriverManager;
use Phil\OutboxBundle\Infrastructure\Console\SchemaCommand;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(SchemaCommand::class)]
final class SchemaCommandTest extends TestCase
{
    public function testTheMissingTableIsShownThenCreated(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $application = new Application();
        $application->addCommand(new SchemaCommand($connection, new OutboxTable('events_out')));

        $tester = new CommandTester($application->find('phil:outbox:schema'));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertSees('CREATE TABLE events_out (position INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL', $tester);
        self::assertStringContainsString(');', $tester->getDisplay());
        $this->assertSees('The outbox table is missing: run again with --force to create it.', $tester);
        self::assertFalse($connection->createSchemaManager()->tableExists('events_out'));

        self::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));
        $this->assertSees('Created: events_out.', $tester);
        self::assertTrue($connection->createSchemaManager()->tableExists('events_out'));
        self::assertCount(4, $connection->createSchemaManager()->introspectTableIndexesByUnquotedName('events_out'));

        self::assertSame(Command::SUCCESS, $tester->execute(['--force' => true]));
        $this->assertSees('The outbox table events_out is there.', $tester);
    }

    /**
     * Whether the display holds a sentence, wherever a console block wrapped it at the terminal's width.
     */
    private function assertSees(string $expected, CommandTester $tester): void
    {
        self::assertStringContainsString($this->flat($expected), $this->flat($tester->getDisplay()));
    }

    private function flat(string $text): string
    {
        return (string) preg_replace('/[\s!]+/', '', $text);
    }
}
