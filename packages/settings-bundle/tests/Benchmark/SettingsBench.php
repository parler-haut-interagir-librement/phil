<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Schema;
use Phil\SettingsBundle\Application\DocumentAttributes;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Application\Visitor\PreferenceCookieFormat;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use Phil\SettingsBundle\Infrastructure\Doctrine\DbalSettingRepository;
use Phil\SettingsBundle\Infrastructure\Doctrine\SettingTables;
use Phil\SettingsBundle\Tests\Unit\Fixture\Catalogs;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedUser;
use Phil\SettingsBundle\Tests\Unit\Fixture\FixedVisitor;
use Symfony\Component\Clock\MockClock;

/**
 * The hot paths, run on every page: the attributes of the root element for a user whose values are read from a table
 * of a thousand users — once per request, then from the service —, the same for a visitor through their cookie, and
 * the cookie read on its own.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"settings"})
 */
final class SettingsBench
{
    private SettingCatalog $catalog;

    private Settings $settings;

    private DocumentAttributes $attributes;

    private DocumentAttributes $visitorAttributes;

    private PreferenceCookieFormat $format;

    public function __construct()
    {
        $this->setUp();
    }

    public function setUp(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        foreach ($connection->getDatabasePlatform()->getCreateTablesSQL(SettingTables::configure(Schema::editor()->create())->getTables()) as $sql) {
            $connection->executeStatement($sql);
        }

        $repository = new DbalSettingRepository($connection);
        $at = new DateTimeImmutable('2026-11-08 00:00 UTC');
        $connection->beginTransaction();

        for ($user = 0; $user < 1000; ++$user) {
            $repository->save(Subject::user('u'.$user), ['display.motion' => '"reduce"', 'display.text_size' => '"large"', 'author.columns' => '3'], [], $at);
        }

        $connection->commit();

        $this->catalog = Catalogs::sample();
        $this->settings = new Settings($this->catalog, $repository, new FixedUser('u500'), new FixedVisitor(), new MockClock());
        $this->attributes = new DocumentAttributes($this->catalog, $this->settings);
        $visitor = new Settings($this->catalog, $repository, new FixedUser(), new FixedVisitor(['display.motion' => 'reduce', 'display.theme' => 'dark']), new MockClock());
        $this->visitorAttributes = new DocumentAttributes($this->catalog, $visitor);
        $this->format = new PreferenceCookieFormat($this->catalog);
    }

    /**
     * A new request: the values read from the table, then the attributes.
     *
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchUserAttributesOnANewRequest(): void
    {
        $this->settings->reset();
        $this->attributes->attributes();
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchUserAttributesWithinARequest(): void
    {
        $this->attributes->attributes();
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchVisitorAttributes(): void
    {
        $this->visitorAttributes->attributes();
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(5)
     */
    public function benchCookie(): void
    {
        $this->format->parse('display.contrast=more&display.motion=reduce&display.text_size=large&display.theme=dark');
    }
}
