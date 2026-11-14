<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\FormsBundle\Application\HmacEmailDigester;
use Phil\FormsBundle\Application\Retention;
use Phil\FormsBundle\Application\Submissions;
use Phil\FormsBundle\Infrastructure\Console\PurgeEntriesCommand;
use Phil\FormsBundle\Infrastructure\Doctrine\DoctrineFormRepository;
use Phil\FormsBundle\Tests\Unit\Fixtures\Database;
use Phil\FormsBundle\Tests\Unit\Fixtures\Forms;
use Phil\FormsBundle\Tests\Unit\Fixtures\MutableClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PurgeEntriesCommand::class)]
final class PurgeEntriesCommandTest extends TestCase
{
    public function testExpiredEntriesAreDeletedABatchAtATime(): void
    {
        $repository = new DoctrineFormRepository(Database::entityManager());
        $form = Forms::registration();
        $form->keepFor(1);

        $repository->save($form);
        $submissions = new Submissions($repository, new MutableClock(new DateTimeImmutable('2026-11-01 UTC')), new HmacEmailDigester('key'));

        for ($i = 0; $i < 5; ++$i) {
            $submissions->submit($form, Forms::values());
        }

        $command = new PurgeEntriesCommand(new Retention($repository, new MutableClock(new DateTimeImmutable('2026-11-04 UTC'))));
        self::assertSame('phil:forms:purge', $command->getName());

        $tester = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $tester->execute(['--batch' => '2']));
        self::assertSame("5 entry(ies) deleted.\n", $tester->getDisplay());
        self::assertSame([], $repository->entries($form));

        $again = new CommandTester($command);
        self::assertSame(Command::SUCCESS, $again->execute([]));
        self::assertSame("0 entry(ies) deleted.\n", $again->getDisplay());

        $submissions->submit($form, Forms::values());
        $exact = new CommandTester($command);
        $exact->execute(['--batch' => '1']);
        self::assertSame("1 entry(ies) deleted.\n", $exact->getDisplay());
    }

    public function testABadBatchIsRefused(): void
    {
        $command = new PurgeEntriesCommand(new Retention(new DoctrineFormRepository(Database::entityManager()), new NativeClock()));

        foreach (['0', '-1', 'a', '1000000', "1\n"] as $batch) {
            $tester = new CommandTester($command);
            self::assertSame(Command::INVALID, $tester->execute(['--batch' => $batch]), $batch);
            self::assertSame("The batch is a whole number from 1 to 999999.\n", $tester->getDisplay());
        }

        self::assertSame(Command::SUCCESS, new CommandTester($command)->execute(['--batch' => '999999']));
    }
}
