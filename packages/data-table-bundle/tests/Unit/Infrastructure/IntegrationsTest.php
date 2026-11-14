<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\DataTableBundle\Application\Exception\InvalidDefinition;
use Phil\DataTableBundle\Application\State\TablePreference;
use Phil\DataTableBundle\Application\Tables;
use Phil\DataTableBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\DataTableBundle\Infrastructure\DependencyInjection\DataTablesPass;
use Phil\DataTableBundle\Infrastructure\Security\AuthorizationCheckerAccess;
use Phil\DataTableBundle\Infrastructure\Settings\SettingsTablePreferences;
use Phil\DataTableBundle\Tests\Fixtures\TestLogger;
use Phil\DataTableBundle\Tests\Functional\App\BenchTable;
use Phil\DataTableBundle\Tests\Functional\App\BooksTable;
use Phil\SettingsBundle\Application\Contract\CurrentUser;
use Phil\SettingsBundle\Application\Contract\VisitorPreferences;
use Phil\SettingsBundle\Application\SettingCatalog;
use Phil\SettingsBundle\Application\Settings;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Setting\SectionDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingDefinition;
use Phil\SettingsBundle\Domain\Setting\SettingType;
use Phil\SettingsBundle\Domain\ValueObject\Subject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(AuthorizationCheckerAccess::class)]
#[CoversClass(SettingsTablePreferences::class)]
#[CoversClass(CsrfProtectionPass::class)]
#[CoversClass(DataTablesPass::class)]
final class IntegrationsTest extends TestCase
{
    /** @var array<string, string> */
    private array $values = [];

    public function testTheVotersAnswer(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            /** @var list<mixed> */
            public array $asked = [];

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->asked[] = [$attribute, $subject];

                return 'YES' === $attribute;
            }
        };
        $access = new AuthorizationCheckerAccess($checker);
        $row = ['id' => 1];

        self::assertTrue($access->isGranted('YES', $row));
        self::assertFalse($access->isGranted('NO'));
        self::assertSame([['YES', $row], ['NO', null]], $checker->asked);
    }

    public function testNothingStoredIsNoPreference(): void
    {
        $preference = $this->preferences('u1')->load('books');

        self::assertSame([[], 0], [$preference->columns, $preference->pageSize]);
    }

    public function testAPreferenceIsStoredTableByTable(): void
    {
        $preferences = $this->preferences('u1');
        $preferences->save('books', new TablePreference(['a', 'b'], 25));
        $preferences->save('films', new TablePreference(['c'], 0));

        $loaded = $preferences->load('books');
        self::assertSame([['a', 'b'], 25], [$loaded->columns, $loaded->pageSize]);
        self::assertSame(['c'], $preferences->load('films')->columns);
        self::assertSame('{"books":{"columns":["a","b"],"size":25},"films":{"columns":["c"],"size":0}}', json_decode($this->values[SettingsTablePreferences::KEY], true));
    }

    public function testAVisitorStoresNothing(): void
    {
        $this->preferences(null)->save('books', new TablePreference(['a'], 25));

        self::assertSame([], $this->values);
    }

    public function testTooMuchIsNotStoredAndTheRefusalIsLogged(): void
    {
        $logger = new TestLogger();
        $this->preferences('u1', $logger)->save('books', new TablePreference([str_repeat('a', SettingsTablePreferences::MAX_LENGTH)], 25));
        self::assertSame([], $this->values);
        self::assertSame([['warning', 'The preferences of the table "{table}" are not kept: {length} characters, more than {limit}.', ['table' => 'books', 'length' => 2036, 'limit' => 2000]]], $logger->records);

        $this->preferences('u1')->save('books', new TablePreference([str_repeat('a', SettingsTablePreferences::MAX_LENGTH)], 25));
        self::assertSame([], $this->values, 'without a logger, the refusal is silent');

        $exactly = SettingsTablePreferences::MAX_LENGTH - \strlen('{"books":{"columns":[""],"size":25}}');
        $this->preferences('u1', $logger)->save('books', new TablePreference([str_repeat('a', $exactly)], 25));
        self::assertArrayHasKey(SettingsTablePreferences::KEY, $this->values);
        self::assertCount(1, $logger->records, 'what fits is not logged');
    }

    public function testWhatIsStoredIsReadWithCare(): void
    {
        $cases = [
            'not json' => '"{oops"',
            'not an object' => '"3"',
            'not a table' => json_encode(json_encode(['books' => 3])),
            'bad parts' => json_encode(json_encode(['books' => ['columns' => 'a', 'size' => 'b']])),
        ];

        foreach ($cases as $case => $stored) {
            $this->values = [SettingsTablePreferences::KEY => (string) $stored];
            $preference = $this->preferences('u1')->load('books');
            self::assertSame([[], 0], [$preference->columns, $preference->pageSize], $case);
        }

        $this->values = [SettingsTablePreferences::KEY => (string) json_encode(json_encode(['books' => ['columns' => [3, 'a'], 'size' => 5]]))];
        self::assertSame(['a'], $this->preferences('u1')->load('books')->columns);
    }

    public function testACorruptedDepthIsNoPreference(): void
    {
        $this->values = [SettingsTablePreferences::KEY => (string) json_encode(str_repeat('[', 12).str_repeat(']', 12))];

        self::assertSame([], $this->preferences('u1')->load('books')->columns);
    }

    public function testAMissingTokenManagerSaysWhatToChange(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"framework.csrf_protection" is false');

        new CsrfProtectionPass()->process(new ContainerBuilder());
    }

    public function testATokenManagerIsEnough(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(CsrfProtectionPass::TOKEN_MANAGER, new Definition());
        new CsrfProtectionPass()->process($container);

        self::assertTrue($container->has(CsrfProtectionPass::TOKEN_MANAGER));
    }

    public function testTheTablesAreFoundByTheirNames(): void
    {
        $container = new ContainerBuilder();
        $container->register(Tables::class)->setArguments([null, null]);
        $container->register('books', BooksTable::class)->addTag(DataTablesPass::TAG);
        $container->register(BenchTable::class)->addTag(DataTablesPass::TAG);
        new DataTablesPass()->process($container);

        $locator = $container->getDefinition(Tables::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $locator);
        $services = $container->getDefinition((string) $locator)->getArgument(0);
        self::assertIsArray($services);
        self::assertArrayHasKey('books', $services);
        self::assertArrayHasKey('bench', $services);
    }

    public function testTwoTablesOfOneNameAreAMistake(): void
    {
        $container = new ContainerBuilder();
        $container->register(Tables::class)->setArguments([null, null]);
        $container->register('one', BooksTable::class)->addTag(DataTablesPass::TAG);
        $container->register('two', BooksTable::class)->addTag(DataTablesPass::TAG);

        $this->expectException(InvalidDefinition::class);
        $this->expectExceptionMessageIsOrContains('The table "books" is invalid: two tables have this name.');

        new DataTablesPass()->process($container);
    }

    private function preferences(?string $user, ?LoggerInterface $logger = null): SettingsTablePreferences
    {
        $repository = new class($this->values) implements SettingRepository {
            /** @param array<string, string> $values */
            public function __construct(private array &$values)
            {
            }

            public function values(Subject $subject): array
            {
                return $this->values;
            }

            public function save(Subject $subject, array $values, array $removed, DateTimeImmutable $at): void
            {
                $this->values = [...$this->values, ...$values];
            }

            public function erase(Subject $subject): array
            {
                return [];
            }
        };
        $current = new readonly class($user) implements CurrentUser {
            public function __construct(private ?string $user)
            {
            }

            public function id(): ?string
            {
                return $this->user;
            }
        };
        $catalog = new SettingCatalog([new SectionDefinition('general')], [new SettingDefinition(SettingsTablePreferences::KEY, SettingType::Text, '{}', maxLength: SettingsTablePreferences::MAX_LENGTH)]);
        $visitor = new class implements VisitorPreferences {
            public function values(): array
            {
                return [];
            }
        };

        return new SettingsTablePreferences(new Settings($catalog, $repository, $current, $visitor, new MockClock()), $current, $logger);
    }
}
