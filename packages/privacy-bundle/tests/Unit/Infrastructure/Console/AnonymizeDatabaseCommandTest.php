<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Phil\PrivacyBundle\Application\Anonymization\AnonymizationRun;
use Phil\PrivacyBundle\Application\Anonymization\ProductionGuard;
use Phil\PrivacyBundle\Domain\Anonymization\Anonymizers;
use Phil\PrivacyBundle\Domain\Anonymization\Locale\LocalePacks;
use Phil\PrivacyBundle\Infrastructure\Console\AnonymizeDatabaseCommand;
use Phil\PrivacyBundle\Infrastructure\Database\ConnectionIdentity;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAnonymizationTarget;
use Phil\PrivacyBundle\Infrastructure\Doctrine\AnonymizationPlanReader;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\Entity\Subscriber;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture\OneManagerRegistry;
use Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The anonymize command on a SQLite file.
 */
#[CoversClass(AnonymizeDatabaseCommand::class)]
#[CoversClass(ConnectionIdentity::class)]
final class AnonymizeDatabaseCommandTest extends TestCase
{
    private string $directory;

    private string $path;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->directory = Files::directory();
        $this->path = $this->directory.'/app.sqlite';
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->path]);
        $this->connection->executeStatement('CREATE TABLE member (id INTEGER PRIMARY KEY, email VARCHAR(255) NULL, name VARCHAR(255) NULL)');
        $this->connection->executeStatement("INSERT INTO member VALUES (1, 'ada@example.com', 'Ada'), (2, NULL, 'Bob')");
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        Files::remove($this->directory);
    }

    public function testADryRunPrintsThePlanAndWhatItLeavesInClear(): void
    {
        $tester = new CommandTester($this->command(['member' => ['email' => ['type' => 'email'], 'name' => ['type' => 'constant', 'options' => ['value' => 'Anonymous']]]]));

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertSame("member.email: email (configuration)\nmember.name: constant (configuration)\n", $tester->getDisplay());
        self::assertSame('ada@example.com', $this->connection->fetchOne('SELECT email FROM member WHERE id = 1'));

        $empty = new CommandTester($this->command([]));
        self::assertSame(Command::SUCCESS, $empty->execute(['--dry-run' => true, '--strict' => true]));
        self::assertSame("No column is anonymized: declare #[Anonymize] on the personal fields, or configure phil_privacy.anonymization.\n", $empty->getDisplay());
    }

    public function testTheGuardRunsBeforeAnythingIsChanged(): void
    {
        $tester = new CommandTester($this->command(['member' => ['email' => ['type' => 'email']]], 'prod'));

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]), 'A dry run changes nothing and passes the guard.');
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
        self::assertStringEndsWith("Anonymization is refused in the \"prod\" environment: it rewrites personal data for good. Back the database up, restore the backup elsewhere, and anonymize there.\n", $tester->getDisplay());
        self::assertSame('ada@example.com', $this->connection->fetchOne('SELECT email FROM member WHERE id = 1'));
    }

    public function testAnAnonymizationNeedsAConfirmationAndAnIntegerSeed(): void
    {
        $tester = new CommandTester($this->command(['member' => ['email' => ['type' => 'email']]]));

        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertStringEndsWith(\sprintf("Nothing was anonymized: add --confirm to rewrite the personal data of the sqlite database %s for good.\n", $this->path), $tester->getDisplay());

        foreach (['seven', '7x', 'x7', '1234567890123456789', '', '7.5', "7\n"] as $seed) {
            self::assertSame(Command::INVALID, $tester->execute(['--confirm' => true, '--seed' => $seed]), $seed);
            self::assertSame("The seed is an integer.\n", $tester->getDisplay());
        }

        self::assertSame(Command::INVALID, $tester->execute(['--seed' => '-123456789012345678']), 'A negative seed of eighteen digits is an integer.');
        self::assertStringStartsWith('member.email: email', $tester->getDisplay());
        self::assertSame('ada@example.com', $this->connection->fetchOne('SELECT email FROM member WHERE id = 1'));
    }

    public function testAnAnonymizationRewritesTheRowsRepeatablyWithASeed(): void
    {
        $tester = new CommandTester($this->command(['member' => ['name' => ['type' => 'firstname']]]));

        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--seed' => '7']));
        self::assertSame("member.name: firstname (configuration)\nmember: 2 row(s) anonymized.\n", $tester->getDisplay());

        $names = $this->connection->fetchFirstColumn('SELECT name FROM member ORDER BY id');
        self::assertNotContains('Ada', $names);
        self::assertSame('ada@example.com', $this->connection->fetchOne('SELECT email FROM member WHERE id = 1'), 'A column out of the plan is kept.');

        $this->connection->executeStatement("UPDATE member SET name = CASE id WHEN 1 THEN 'Ada' ELSE 'Bob' END");
        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--seed' => '7']));
        self::assertSame($names, $this->connection->fetchFirstColumn('SELECT name FROM member ORDER BY id'), 'The same seed gives the same copy.');
    }

    public function testStrictModeFailsWhileAPersonalFieldIsLeftInClear(): void
    {
        $registry = new OneManagerRegistry($this->entityManager());
        $command = new AnonymizeDatabaseCommand(new AnonymizationPlanReader($registry), $this->anonymization(), new DbalAnonymizationTarget($this->connection, 500), ConnectionIdentity::of($this->connection), new ProductionGuard('dev'));
        $tester = new CommandTester($command);
        self::assertSame('phil:privacy:anonymize', $command->getName());

        self::assertSame(Command::FAILURE, $tester->execute(['--strict' => true, '--confirm' => true]));
        self::assertSame('No column is anonymized: declare #[Anonymize] on the personal fields, or configure phil_privacy.anonymization.
Left in clear: '.Subscriber::class.'::$email (email).
Nothing was anonymized: personal fields are left in clear.
', $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
    }

    /**
     * @param array<string, array<string, array{type: string, options?: array<string, scalar|null>}>> $tables
     */
    private function command(array $tables, string $environment = 'dev'): AnonymizeDatabaseCommand
    {
        return new AnonymizeDatabaseCommand(new AnonymizationPlanReader(null, [], $tables), $this->anonymization(), new DbalAnonymizationTarget($this->connection, 500), ConnectionIdentity::of($this->connection), new ProductionGuard($environment));
    }

    private function anonymization(): AnonymizationRun
    {
        return new AnonymizationRun(Anonymizers::builtIn(), LocalePacks::builtIn());
    }

    private function entityManager(): EntityManager
    {
        $config = new Configuration();
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__.'/Fixture/Entity']));
        $config->enableNativeLazyObjects(true);

        return new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
    }
}
