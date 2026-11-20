<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\NotificationsBundle\Application\Preferences\DigestFrequency;
use Phil\NotificationsBundle\Application\Preferences\QuietHours;
use Phil\NotificationsBundle\Application\Preferences\RecipientSettings;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Infrastructure\Doctrine\DbalPreferences;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationSchemaListener;
use Phil\NotificationsBundle\Infrastructure\Doctrine\NotificationTables;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DbalPreferences::class)]
#[CoversClass(NotificationTables::class)]
#[CoversClass(NotificationSchemaListener::class)]
final class DbalPreferencesTest extends TestCase
{
    public function testARecipientWithoutRowHasTheDefaults(): void
    {
        $defaults = new RecipientSettings(DigestFrequency::Weekly);

        self::assertSame($defaults, new DbalPreferences($this->connection(), $defaults)->settingsFor('42'));
        self::assertSame(DigestFrequency::Never, new DbalPreferences($this->connection())->settingsFor('42')->digest);
    }

    public function testSettingsAreSavedReplacedAndForgotten(): void
    {
        $connection = $this->connection();
        $preferences = new DbalPreferences($connection);
        $preferences->save('42', new RecipientSettings(DigestFrequency::Daily, QuietHours::of('22:00', '07:00', 'America/Toronto'), ['*:web_push'], 'fr'));
        $preferences->save('43', new RecipientSettings());

        $settings = $preferences->settingsFor('42');

        self::assertSame(DigestFrequency::Daily, $settings->digest);
        self::assertSame(['22:00', '07:00', 'America/Toronto'], [$settings->quietHours?->start, $settings->quietHours?->end, $settings->quietHours?->timezone->getName()]);
        self::assertSame(['*:web_push'], $settings->optOuts);
        self::assertSame('fr', $settings->locale);
        self::assertSame('{"digest":"daily","quiet_hours":{"start":"22:00","end":"07:00","timezone":"America\/Toronto"},"opt_outs":["*:web_push"],"locale":"fr"}', $connection->fetchOne('SELECT settings FROM phil_notification_preferences WHERE recipient = ?', ['42']));

        $preferences->save('42', new RecipientSettings(DigestFrequency::Weekly));
        $preferences->save('42', new RecipientSettings(DigestFrequency::Weekly));
        self::assertSame(DigestFrequency::Weekly, $preferences->settingsFor('42')->digest);
        self::assertNull($preferences->settingsFor('42')->quietHours);
        self::assertSame(DigestFrequency::Never, $preferences->settingsFor('43')->digest);
        self::assertSame(['42', '43'], $connection->fetchFirstColumn('SELECT recipient FROM phil_notification_preferences ORDER BY recipient'));

        $preferences->forget('42');
        self::assertSame(DigestFrequency::Never, $preferences->settingsFor('42')->digest);
        self::assertSame(['43'], $connection->fetchFirstColumn('SELECT recipient FROM phil_notification_preferences'));
    }

    public function testASavedRecipientIsAnIdentifier(): void
    {
        $this->expectExceptionMessageIsOrContains('The recipient (length 3) is not an identifier');

        new DbalPreferences($this->connection())->save('a b', new RecipientSettings());
    }

    public function testARowThatIsNoSettingsIsRefused(): void
    {
        foreach ([
            '{"digest":"hourly","opt_outs":[]}' => 'The stored preferences of "42" are not settings.',
            '{"digest":7}' => 'The stored preferences of "42" are not settings.',
            '{"opt_outs":[]}' => 'The stored preferences of "42" are not settings.',
            '{"digest":"daily","opt_outs":"x"}' => 'The stored preferences of "42" are not settings.',
            '{"digest":"daily","opt_outs":{"a":"x"}}' => 'The stored preferences of "42" are not settings.',
            '{"digest":"daily","locale":5}' => 'The stored preferences of "42" are not settings.',
            '{"digest":"daily","opt_outs":[5]}' => 'The opted-out type (length 0) is not a key',
            '{"digest":"daily","quiet_hours":"x"}' => 'The stored quiet hours of "42" have a start, an end and a time zone.',
            '{"digest":"daily","quiet_hours":{"end":"07:00","timezone":"UTC"}}' => 'The stored quiet hours of "42" have a start, an end and a time zone.',
            '{"digest":"daily","quiet_hours":{"start":"22:00","timezone":"UTC"}}' => 'The stored quiet hours of "42" have a start, an end and a time zone.',
            '{"digest":"daily","quiet_hours":{"start":"22:00","end":"07:00"}}' => 'The stored quiet hours of "42" have a start, an end and a time zone.',
            '"x"' => 'The column "settings" of a stored preference is a JSON object.',
        ] as $json => $message) {
            $connection = $this->connection();
            $connection->insert(NotificationTables::PREFERENCES, ['recipient' => '42', 'settings' => $json]);

            try {
                new DbalPreferences($connection)->settingsFor('42');
                self::fail($json);
            } catch (InvalidNotification $invalidNotification) {
                self::assertStringStartsWith($message, $invalidNotification->getMessage(), $json);
            }
        }

        $connection = $this->connection();
        $connection->insert(NotificationTables::PREFERENCES, ['recipient' => '42', 'settings' => '{"digest":"daily"}']);
        self::assertSame([], new DbalPreferences($connection)->settingsFor('42')->optOuts);
    }

    public function testTheTablesJoinTheSchemaOfTheirConnection(): void
    {
        $connection = $this->connection(false);
        $listener = new NotificationSchemaListener($connection);
        $schema = Schema::editor()->create();
        $ignored = new GenerateSchemaEventArgs($this->entityManager($this->connection(false)), $schema);
        $listener->postGenerateSchema($ignored);

        self::assertSame($schema, $ignored->getSchema());
        self::assertFalse($schema->hasTable(NotificationTables::NOTIFICATIONS));

        $event = new GenerateSchemaEventArgs($this->entityManager($connection), $schema);
        $listener->postGenerateSchema($event);
        $schema = $event->getSchema();
        $table = $schema->getTable(NotificationTables::NOTIFICATIONS);

        self::assertTrue($schema->hasTable(NotificationTables::PREFERENCES));
        self::assertSame([190, true], [$table->getColumn('recipient')->getLength(), $table->getColumn('recipient')->getNotnull()]);
        self::assertSame([64, false], [$table->getColumn('origin')->getLength(), $table->getColumn('origin')->getNotnull()]);
        self::assertSame(320, $table->getColumn('group_key')->getLength());
        self::assertSame(['datetime_utc_immutable', true], [$table->getColumn('created_at')->getTypeName(), $table->getColumn('created_at')->getNotnull()]);
        foreach (['actor', 'subject_type', 'subject_id', 'origin', 'read_at', 'digested_at'] as $nullable) {
            self::assertFalse($table->getColumn($nullable)->getNotnull(), $nullable);
        }

        self::assertSame(8, $table->getColumn('state')->getLength());
        self::assertSame(['id'], array_map(static fn (object $name): string => $name->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertSame(['recipient'], array_map(static fn (object $name): string => $name->toString(), $schema->getTable(NotificationTables::PREFERENCES)->getPrimaryKeyConstraint()?->getColumnNames() ?? []));

        foreach (['centre', 'group', 'digest', 'purge'] as $index) {
            self::assertTrue($table->hasIndex('phil_notifications_'.$index), $index);
        }

        self::assertCount(2, NotificationTables::configure($schema)->getTables());
    }

    public function testThePreferencesTableIsLeftOutWhenNotUsed(): void
    {
        $connection = $this->connection(false);
        $event = new GenerateSchemaEventArgs($this->entityManager($connection), Schema::editor()->create());
        new NotificationSchemaListener($connection, false)->postGenerateSchema($event);

        self::assertTrue($event->getSchema()->hasTable(NotificationTables::NOTIFICATIONS));
        self::assertFalse($event->getSchema()->hasTable(NotificationTables::PREFERENCES));
        self::assertTrue(NotificationTables::configure($event->getSchema())->hasTable(NotificationTables::PREFERENCES));
    }

    private function connection(bool $withTables = true): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        if ($withTables) {
            foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(NotificationTables::configure(Schema::editor()->create())->getTables()) as $sql) {
                $connection->executeStatement($sql);
            }
        }

        return $connection;
    }

    private function entityManager(Connection $connection): EntityManagerInterface
    {
        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getConnection')->willReturn($connection);

        return $entityManager;
    }
}
