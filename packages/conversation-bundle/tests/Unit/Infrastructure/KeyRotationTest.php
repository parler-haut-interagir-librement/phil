<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Infrastructure;

use Phil\ConversationBundle\Domain\Exception\UnreadableMessage;
use Phil\ConversationBundle\Domain\Model\Message;
use Phil\ConversationBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\ConversationBundle\Infrastructure\Doctrine\ConversationTables;
use Phil\ConversationBundle\Infrastructure\Doctrine\DbalConversationRepository;
use Phil\ConversationBundle\Infrastructure\Doctrine\KeyRotation;
use Phil\ConversationBundle\Tests\Unit\Fixture\Keys;
use Phil\ConversationBundle\Tests\Unit\Fixture\RacingSealer;
use Phil\ConversationBundle\Tests\Unit\Fixture\SealerStandIn;
use Phil\ConversationBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(KeyRotation::class)]
#[CoversClass(RotateKeysCommand::class)]
#[CoversClass(DbalConversationRepository::class)]
#[CoversClass(ConversationTables::class)]
final class KeyRotationTest extends TestCase
{
    public function testTheTextsOfThePreviousKeyAreSealedAgainUntilItCanLeave(): void
    {
        $before = new Store(sealer: new SealerStandIn('k2025'));
        $group = $before->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'Plans', 'One');
        $before->conversations->reply($group->id, Store::id('bob'), 'Two');
        $before->conversations->reply($group->id, Store::id('ada'), 'Three');
        $before->conversations->startOpen(Store::id('cy'), null, 'Four');

        $after = new Store($before->connection);
        $rotation = new KeyRotation($after->connection, $after->sealer);
        self::assertSame(['subjects' => 1, 'bodies' => 4], $rotation->pending());

        self::assertSame(['subjects' => 1, 'bodies' => 3], $rotation->rotate(3));
        self::assertSame(['subjects' => 0, 'bodies' => 1], $rotation->pending());
        self::assertSame(['subjects' => 0, 'bodies' => 1], $rotation->rotate());
        self::assertSame(['subjects' => 0, 'bodies' => 0], $rotation->rotate());
        self::assertSame(['k2026'], array_values(array_unique(array_map(static fn (mixed $key): string => \is_string($key) ? $key : '', $after->connection->fetchFirstColumn('SELECT body_key FROM phil_conversation_message')))));

        // The previous key leaves the key management service: everything still opens.
        $without = new Store($before->connection, Keys::sealer('k2026', ['k2026']));
        self::assertSame('Plans', $without->repository->find($group->id)?->subject());
        self::assertSame(['One', 'Two', 'Three'], array_map(static fn (Message $message): ?string => $message->body, $without->repository->messages($group->id, 10)));
    }

    public function testATextWhoseKeyLeftTooSoonStopsTheRotation(): void
    {
        $before = new Store(sealer: new SealerStandIn('k2025'));
        $before->conversations->startOpen(Store::id('ada'), null, 'One');

        $this->expectException(UnreadableMessage::class);

        new KeyRotation($before->connection, Keys::sealer('k2026', ['k2026']))->rotate();
    }

    public function testATextChangedMeanwhileIsLeftToItsWriter(): void
    {
        $before = new Store(sealer: new SealerStandIn('k2025'));
        $before->conversations->startOpen(Store::id('ada'), 'Subject', 'One');

        $after = new Store($before->connection);

        $racing = new class($after) extends RacingSealer {
        };
        self::assertSame(['subjects' => 0, 'bodies' => 0], new KeyRotation($after->connection, $racing)->rotate());
    }

    public function testTheCommandSealsEverythingAgainOrSaysWhatRemains(): void
    {
        $before = new Store(sealer: new SealerStandIn('k2025'));
        $group = $before->conversations->startGroup(Store::id('ada'), [Store::id('bob')], 'Plans', 'One');
        $before->conversations->reply($group->id, Store::id('bob'), 'Two');
        $after = new Store($before->connection);
        $tester = $this->tester(new KeyRotation($after->connection, $after->sealer));

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('1 subject(s) and 2 message(s) are sealed under a previous key.', $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['--batch' => '0']));
        self::assertStringContainsString('A batch seals at least one text.', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--batch' => '1']));
        self::assertStringContainsString(' 1 subject(s) and 2 message(s) sealed again under the current key.', $tester->getDisplay());
        self::assertSame(['subjects' => 0, 'bodies' => 0], new KeyRotation($after->connection, $after->sealer)->pending());
    }

    public function testEveryRowOfABatchIsCounted(): void
    {
        $before = new Store(sealer: new SealerStandIn('k2025'));
        $before->conversations->startOpen(Store::id('ada'), 'First', 'One');
        $before->conversations->startOpen(Store::id('bob'), 'Second', 'Two');
        $before->conversations->startOpen(Store::id('cy'), 'Third', 'Three');

        $after = new Store($before->connection);

        self::assertSame(['subjects' => 3, 'bodies' => 3], new KeyRotation($after->connection, $after->sealer)->rotate());
    }

    private function tester(KeyRotation $rotation): CommandTester
    {
        $application = new Application();
        $application->addCommand(new RotateKeysCommand($rotation));

        return new CommandTester($application->find('phil:conversation:rotate-keys'));
    }
}
