<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Functional;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\Outbox\Contract\Outbox;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\RecordState;
use Phil\OutboxBundle\Infrastructure\Console\ClearErrorsCommand;
use Phil\OutboxBundle\Infrastructure\Console\RelayCommand;
use Phil\OutboxBundle\Infrastructure\Console\SchemaCommand;
use Phil\OutboxBundle\Infrastructure\DependencyInjection\OutboxServices;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxSchemaListener;
use Phil\OutboxBundle\PhilOutboxBundle;
use Phil\OutboxBundle\Tests\Functional\App\QuizListener;
use Phil\OutboxBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The bundle in an application: the table joins the schema the ORM generates, a record joins the transaction of the
 * write it describes, the relay command tells the listener, and the commands are there.
 */
#[CoversClass(PhilOutboxBundle::class)]
#[CoversClass(OutboxServices::class)]
#[CoversClass(OutboxSchemaListener::class)]
#[CoversClass(SchemaCommand::class)]
#[CoversClass(ClearErrorsCommand::class)]
#[CoversClass(RelayCommand::class)]
final class OutboxBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
        $this->connection()->executeStatement('DROP TABLE IF EXISTS phil_outbox');
        new SchemaTool($this->service('doctrine.orm.entity_manager', EntityManagerInterface::class))->createSchema([]);
    }

    public function testTheTableJoinsTheSchemaOfTheOrm(): void
    {
        self::assertTrue($this->connection()->createSchemaManager()->tableExists('phil_outbox'));

        $tester = $this->command('phil:outbox:schema');
        $tester->execute([]);
        $this->assertSees('The outbox table phil_outbox is there.', $tester);
    }

    public function testTheSchemaCommandCreatesAMissingTable(): void
    {
        $this->connection()->executeStatement('DROP TABLE phil_outbox');
        $tester = $this->command('phil:outbox:schema');

        $tester->execute([]);
        $this->assertSees('CREATE TABLE phil_outbox', $tester);
        $this->assertSees('The outbox table is missing: run again with --force to create it.', $tester);
        self::assertFalse($this->connection()->createSchemaManager()->tableExists('phil_outbox'));

        $tester->execute(['--force' => true]);
        $this->assertSees('Created: phil_outbox.', $tester);
        self::assertTrue($this->connection()->createSchemaManager()->tableExists('phil_outbox'));
    }

    public function testARecordJoinsTheTransactionAndTheRelayTellsTheListener(): void
    {
        $outbox = $this->service(Outbox::class, Outbox::class);
        $connection = $this->connection();

        try {
            $connection->transactional(static function () use ($outbox): never {
                $outbox->record('quiz.completed', ['quiz' => 'lost']);

                throw new RuntimeException('The write of the package failed.');
            });
        } catch (RuntimeException) {
        }

        $connection->transactional(static function () use ($outbox): void {
            $outbox->record('quiz.completed', ['quiz' => 'q1'], 'quiz:q1');
            $outbox->record('quiz.completed', ['quiz' => 'q2'], 'quiz:q1');
            $outbox->record('quiz.completed', ['quiz' => 'q3']);
        });

        $tester = $this->command('phil:outbox:relay');
        $tester->execute(['--stop-when-empty' => true]);

        $this->assertSees('[OK] Claimed 3: 3 delivered', $tester);
        self::assertSame(['q1', 'q3', 'q2'], array_values($this->service(QuizListener::class, QuizListener::class)->handled));
        self::assertSame(['pending' => 0, 'delivered' => 3, 'skipped' => 0, 'dead' => 0], $this->service(Housekeeping::class, Housekeeping::class)->counts());
    }

    public function testTheCommandsOfTheDeadRecordsAndThePurgeAreThere(): void
    {
        $this->service(Outbox::class, Outbox::class)->record('quiz.completed', ['quiz' => 'q1']);
        $this->command('phil:outbox:relay')->execute(['--stop-when-empty' => true]);
        $this->service('clock', MockClock::class)->sleep(604_801);

        $purge = $this->command('phil:outbox:purge');
        $purge->execute([]);
        $this->assertSees('1 delivered records removed.', $purge);

        foreach (['phil:outbox:dead:show' => 'No dead record.', 'phil:outbox:dead:retry' => 'Name the dead records, or give --all.', 'phil:outbox:dead:remove' => 'Name the dead records, or give --all.', 'phil:outbox:errors:clear' => '0 errors cleared of their message.'] as $name => $expected) {
            $tester = $this->command($name);
            $tester->execute([]);
            $this->assertSees($expected, $tester);
        }

        self::assertSame(0, $this->service(Housekeeping::class, Housekeeping::class)->counts()[RecordState::Delivered->value]);
    }

    private function command(string $name): CommandTester
    {
        $kernel = self::$kernel ?? throw new RuntimeException('The kernel is not booted.');

        return new CommandTester(new Application($kernel)->find($name));
    }

    private function connection(): Connection
    {
        return $this->service('doctrine.dbal.default_connection', Connection::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
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
