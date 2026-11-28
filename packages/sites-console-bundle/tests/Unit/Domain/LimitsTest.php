<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Domain;

use Closure;
use Doctrine\ORM\Mapping\Column;
use Phil\SitesConsoleBundle\Domain\Alert\AlertRules;
use Phil\SitesConsoleBundle\Domain\Exception\InvalidValue;
use Phil\SitesConsoleBundle\Domain\Exception\MalformedDocument;
use Phil\SitesConsoleBundle\Domain\Model\ConfigurationPush;
use Phil\SitesConsoleBundle\Domain\Model\JournalEntry;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Model\Snapshot;
use Phil\SitesConsoleBundle\Domain\Model\UpdatePlan;
use Phil\SitesConsoleBundle\Domain\Status\JournalAction;
use Phil\SitesConsoleBundle\Domain\ValueObject\AgentUrl;
use Phil\SitesConsoleBundle\Domain\ValueObject\CheckState;
use Phil\SitesConsoleBundle\Domain\ValueObject\ConfigurationRequest;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;
use Phil\SitesConsoleBundle\Domain\ValueObject\Outcome;
use Phil\SitesConsoleBundle\Domain\ValueObject\Settings;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;
use Phil\SitesConsoleBundle\Domain\ValueObject\UpdateRequest;
use Phil\SitesConsoleBundle\Domain\Version\Packages;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Fixtures;
use Phil\SitesConsoleBundle\Tests\Unit\Support\Keys;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The edges of every limit and every ordering: one character under, at and over, so that a limit moved by one, a
 * length counted in bytes, or an order lost shows.
 */
#[CoversClass(Packages::class)]
#[CoversClass(AlertRules::class)]
#[CoversClass(Snapshot::class)]
#[CoversClass(JournalEntry::class)]
#[CoversClass(Site::class)]
#[CoversClass(UpdatePlan::class)]
#[CoversClass(ConfigurationPush::class)]
#[CoversClass(HealthSummary::class)]
#[CoversClass(CheckState::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(StatusDocument::class)]
#[CoversClass(ConfigurationRequest::class)]
#[CoversClass(UpdateRequest::class)]
#[CoversClass(Settings::class)]
final class LimitsTest extends TestCase
{
    public function testTheTextOfAHealthStatusStopsAtTenCharacters(): void
    {
        $enum = '"status" is up, degraded, down or unknown.';
        $text = '"status" is a text of 1 to 10 characters without control characters.';

        $this->assertRefusal($enum, static fn (): HealthSummary => HealthSummary::fromArray(['status' => str_repeat('a', 10)]));
        $this->assertRefusal($text, static fn (): HealthSummary => HealthSummary::fromArray(['status' => str_repeat('a', 11)]));
        $this->assertRefusal($enum, static fn (): CheckState => CheckState::fromArray(['name' => 'db', 'status' => str_repeat('a', 10)]));
        $this->assertRefusal($text, static fn (): CheckState => CheckState::fromArray(['name' => 'db', 'status' => str_repeat('a', 11)]));
    }

    public function testTheOutcomeOfAnAnswerStopsAtTenCharacters(): void
    {
        $this->assertRefusal('"outcome" is succeeded or failed, and "replayed" a boolean.', static fn (): Outcome => Outcome::fromJson('{"outcome":"'.str_repeat('a', 10).'"}'));
        $this->assertRefusal('"outcome" is a text of 1 to 10 characters without control characters.', static fn (): Outcome => Outcome::fromJson('{"outcome":"'.str_repeat('a', 11).'"}'));
    }

    public function testTheDateOfAStatusStopsAtThirtyTwoCharacters(): void
    {
        $base = ['site' => 'shop', 'php' => '8.5.1', 'symfony' => '8.2.0', 'packages' => []];

        $this->assertRefusal('"generated_at" is a date in the ATOM format.', static fn (): StatusDocument => StatusDocument::fromArray($base + ['generated_at' => str_repeat('a', 32)]));
        $this->assertRefusal('"generated_at" is a text of 1 to 32 characters without control characters.', static fn (): StatusDocument => StatusDocument::fromArray($base + ['generated_at' => str_repeat('a', 33)]));
    }

    public function testTheIdentifiersOfARequestAreBounded(): void
    {
        $settings = ['a' => 1];

        self::assertSame(str_repeat('a', 64), ConfigurationRequest::fromJson((string) json_encode(['id' => str_repeat('a', 64), 'settings' => $settings]))->id);
        $this->assertRefusal('"id" is a text of 1 to 64 characters without control characters.', static fn (): ConfigurationRequest => ConfigurationRequest::fromJson((string) json_encode(['id' => str_repeat('a', 65), 'settings' => $settings])));

        $package = 'a/'.str_repeat('b', 212);
        self::assertSame(214, \strlen($package));
        self::assertSame($package, UpdateRequest::fromJson((string) json_encode(['id' => str_repeat('a', 128), 'package' => $package, 'version' => '1.0']))->package);
        $this->assertRefusal('"package" is a text of 1 to 214 characters without control characters.', static fn (): UpdateRequest => UpdateRequest::fromJson((string) json_encode(['id' => 'x', 'package' => $package.'b', 'version' => '1.0'])));
        $this->assertRefusal('"id" is a text of 1 to 128 characters without control characters.', static fn (): UpdateRequest => UpdateRequest::fromJson((string) json_encode(['id' => str_repeat('a', 129), 'package' => 'a/b', 'version' => '1.0'])));
    }

    public function testTheKeyShownInTheReasonIsCutByCharacters(): void
    {
        $key = str_repeat('É', 101);

        $this->expectExceptionObject(InvalidValue::because(\sprintf('The key "%s" is 1 to 100 lowercase letters, digits, dots, dashes and underscores, not digits alone.', str_repeat('É', 100))));
        Settings::of([$key => 1]);
    }

    public function testAnOutcomeThatFailedWasNotReplayed(): void
    {
        self::assertFalse(Outcome::failed('x')->replayed);
        self::assertFalse(Outcome::failed('x')->succeeded);
    }

    public function testAnUnknownHealthIsPublic(): void
    {
        self::assertTrue(new ReflectionMethod(HealthSummary::class, 'unknown')->isPublic());
        self::assertSame('unknown', HealthSummary::unknown()->state->value);
    }

    public function testVersionsAreReadWholeAndWithoutATrailingBreak(): void
    {
        self::assertTrue(Packages::isOrdered('1.0v1'));
        self::assertTrue(Packages::isOrdered('v1.0'));
        self::assertFalse(Packages::isOrdered("1.0\n"));
        self::assertFalse(Packages::isOrdered('1.0 beta'));
        self::assertNull(Packages::compare("1.0\n", '1.0'));
        self::assertSame(0, Packages::compare('v1.0', '1.0'));
    }

    public function testAVersionNewerThanTheTargetOrWithoutOrderRaisesNothing(): void
    {
        $rules = new AlertRules(['phil/*'], ['phil/url' => '1.0']);

        self::assertSame([], $rules->evaluate('shop', $this->snapshot(['phil/url' => '2.0']), []));
        self::assertSame([], $rules->evaluate('shop', $this->snapshot(['phil/url' => 'dev-main']), []));
        self::assertSame([], $rules->evaluate('shop', $this->snapshot(['phil/url' => '1.0']), []));
    }

    public function testTheNewestVersionsSkipTheOtherPackagesAndAreSortedByName(): void
    {
        $rules = new AlertRules(['phil/*']);

        self::assertSame(['phil/url' => '1.0'], $rules->newest([$this->snapshot(['other/lib' => '9.0', 'phil/url' => '1.0'])]));
        self::assertSame(['phil/a' => '1.0', 'phil/b' => '2.0'], $rules->newest([$this->snapshot(['phil/b' => '2.0']), $this->snapshot(['phil/a' => '1.0'])]));
    }

    public function testTheReasonsToRefuseAreSharedByTheSnapshotsPackages(): void
    {
        $snapshot = Snapshot::reached('shop', Fixtures::status('shop', ['phil/url' => '0.1.4', 'a/b' => '1.0', 'phil/enums' => '2.0']), Fixtures::now());

        self::assertSame(['a/b' => '1.0', 'phil/enums' => '2.0', 'phil/url' => '0.1.4'], $snapshot->packages());
    }

    public function testAnErrorAndAJournalLineAreCutFromTheirStart(): void
    {
        $error = Snapshot::unreachable('shop', 'a'.str_repeat('é', 600), Fixtures::now())->error();
        self::assertSame('a'.str_repeat('é', 499), $error);

        $entry = JournalEntry::record(JournalAction::SiteRegistered, 'a'.str_repeat('é', 200), 'shop', null, true, 'b'.str_repeat('é', 600), Fixtures::now());
        self::assertSame('a'.str_repeat('é', 179), $entry->actor());
        self::assertSame('b'.str_repeat('é', 499), $entry->message());
    }

    public function testTheGroupsOfASiteAreAListWithoutRepeats(): void
    {
        $site = Site::register('shop', 'Shop', AgentUrl::fromString('https://shop.example.com/a'), Keys::site()->verifyingKey(), ['eu', 'eu', 'web'], Fixtures::now());

        self::assertSame(['eu', 'web'], $site->groups());
    }

    public function testThePositionOfASiteInAPlanIgnoresItsRepeats(): void
    {
        $plan = UpdatePlan::plan('phil/url', '1.0', ['a', 'a', 'b'], 1, null, Fixtures::now());

        self::assertCount(2, $plan->steps());
        self::assertSame(2, $plan->batchAt(1));
        self::assertSame('b', $plan->siteAt(1));
    }

    public function testAPushToTheSameSiteTwiceGoesOnce(): void
    {
        $push = ConfigurationPush::create(Settings::of(['a' => 1]), ['a', 'a', 'b'], null, Fixtures::now());

        self::assertSame(['a', 'b'], $push->undeliveredSites());
    }

    /**
     * @param array{0: ?string, 1: ?int, 2: bool, 3: array<string, mixed>} $expected the type, the length, whether it may be null, the options
     */
    #[DataProvider('columns')]
    public function testTheColumnsOfTheTables(string $entity, string $property, array $expected): void
    {
        /** @var class-string $class */
        $class = 'Phil\SitesConsoleBundle\Domain\Model\\'.$entity;
        $attributes = new ReflectionClass($class)->getProperty($property)->getAttributes(Column::class);
        self::assertCount(1, $attributes);
        $column = $attributes[0]->newInstance();

        self::assertSame($expected, [$column->type, $column->length, $column->nullable, $column->options]);
    }

    /**
     * @return iterable<string, array{string, string, array{0: ?string, 1: ?int, 2: bool, 3: array<string, mixed>}}>
     */
    public static function columns(): iterable
    {
        $fixed = ['fixed' => true];

        foreach ([
            'ConfigurationPush.id' => [null, 26, false, $fixed],
            'ConfigurationPush.settings' => ['json', null, false, []],
            'ConfigurationPush.deliveries' => ['json', null, false, []],
            'ConfigurationPush.createdAt' => [null, null, false, []],
            'ConfigurationPush.createdBy' => [null, 180, true, []],
            'JournalEntry.id' => [null, 26, false, $fixed],
            'JournalEntry.occurredAt' => [null, null, false, []],
            'JournalEntry.actor' => [null, 180, true, []],
            'JournalEntry.action' => [null, 32, false, []],
            'JournalEntry.siteId' => [null, 64, true, []],
            'JournalEntry.reference' => [null, 128, true, []],
            'JournalEntry.succeeded' => [null, null, false, []],
            'JournalEntry.message' => [null, 500, false, []],
            'Site.id' => [null, 64, false, []],
            'Site.name' => [null, 100, false, []],
            'Site.agentUrl' => [null, 2048, false, []],
            'Site.publicKey' => [null, 64, false, []],
            'Site.groups' => ['json', null, false, []],
            'Site.createdAt' => [null, null, false, []],
            'Snapshot.id' => [null, 26, false, $fixed],
            'Snapshot.siteId' => [null, 64, false, []],
            'Snapshot.takenAt' => [null, null, false, []],
            'Snapshot.reachable' => [null, null, false, []],
            'Snapshot.health' => [null, 10, false, []],
            'Snapshot.checks' => ['json', null, false, []],
            'Snapshot.packages' => ['json', null, false, []],
            'Snapshot.php' => [null, 64, true, []],
            'Snapshot.symfony' => [null, 64, true, []],
            'Snapshot.error' => [null, 500, true, []],
            'UpdatePlan.id' => [null, 26, false, $fixed],
            'UpdatePlan.package' => [null, 214, false, []],
            'UpdatePlan.version' => [null, 64, false, []],
            'UpdatePlan.batchSize' => [null, null, false, []],
            'UpdatePlan.state' => [null, 10, false, []],
            'UpdatePlan.steps' => ['json', null, false, []],
            'UpdatePlan.createdAt' => [null, null, false, []],
            'UpdatePlan.createdBy' => [null, 180, true, []],
        ] as $name => $expected) {
            [$entity, $property] = explode('.', $name);

            yield $name => [$entity, $property, $expected];
        }
    }

    /**
     * @param array<string, string> $packages
     */
    private function snapshot(array $packages): Snapshot
    {
        return Snapshot::reached('shop', Fixtures::status('shop', $packages), Fixtures::now());
    }

    /**
     * @param Closure(): mixed $call
     */
    private function assertRefusal(string $message, Closure $call): void
    {
        try {
            $call();
            self::fail($message);
        } catch (MalformedDocument $malformedDocument) {
            self::assertSame($message, $malformedDocument->getMessage());
        }
    }
}
