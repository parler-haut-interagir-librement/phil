<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Application\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Flag\CatalogEntry;
use Phil\FeatureFlagsBundle\Application\Flag\FlagCatalog;
use Phil\FeatureFlagsBundle\Application\Flag\UnreadableEntry;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\CountingProvider;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\PartlyUnreadableProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagCatalog::class)]
#[CoversClass(CatalogEntry::class)]
#[CoversClass(UnreadableEntry::class)]
final class FlagCatalogTest extends TestCase
{
    public function testEveryEnumerableFlagIsListedByNameWithItsSourceAndStoredState(): void
    {
        $catalog = new FlagCatalog(
            [
                'yaml' => ConfiguredFlagProvider::fromConfiguration(['zeta' => ['active' => true], 'alpha' => ['active' => false]]),
                'db' => ConfiguredFlagProvider::fromConfiguration(['beta' => ['active' => false]]),
            ],
            new InMemoryFlagStore(['alpha' => true]),
        );

        $entries = $catalog->entries();

        self::assertSame(['alpha', 'beta', 'zeta'], array_map(static fn (CatalogEntry $entry): string => $entry->declared->name->value, $entries));
        self::assertSame(['yaml', 'db', 'yaml'], array_map(static fn (CatalogEntry $entry): string => $entry->source, $entries));
        self::assertTrue($entries[0]->stored);
        self::assertFalse($entries[0]->declared->active);
        self::assertTrue($entries[0]->effective()->active);
        self::assertNull($entries[1]->stored);
        self::assertFalse($entries[1]->effective()->active);
    }

    /**
     * The first provider that knows a flag declares it; a later one is shadowed, as in the evaluator.
     */
    public function testTheFirstProviderDeclaresAFlag(): void
    {
        $catalog = new FlagCatalog([
            'yaml' => ConfiguredFlagProvider::fromConfiguration(['beta' => ['active' => true]]),
            'db' => ConfiguredFlagProvider::fromConfiguration(['beta' => ['active' => false]]),
        ]);

        self::assertCount(1, $catalog->entries());
        self::assertSame('yaml', $catalog->entries()[0]->source);
        self::assertSame('yaml', $catalog->find(FlagName::of('beta'))?->source);
    }

    /**
     * A provider that cannot list its flags contributes none to the listing, and still answers for one.
     */
    public function testAProviderThatCannotListStillAnswersForOneFlag(): void
    {
        $remote = new CountingProvider();
        $remote->flags = ['remote-flag' => true];

        $catalog = new FlagCatalog(['remote' => $remote]);

        self::assertSame([], $catalog->entries());
        self::assertSame('remote', $catalog->find(FlagName::of('remote-flag'))?->source);
        self::assertNull($catalog->find(FlagName::of('absent')));
    }

    /**
     * A provider that cannot list its flags is skipped, and the ones after it still contribute theirs.
     */
    public function testTheProvidersAfterOneThatCannotListStillContribute(): void
    {
        $catalog = new FlagCatalog([
            'remote' => new CountingProvider(),
            'yaml' => ConfiguredFlagProvider::fromConfiguration(['beta' => ['active' => true]]),
        ]);

        self::assertSame(['beta'], array_map(static fn (CatalogEntry $entry): string => $entry->declared->name->value, $catalog->entries()));
    }

    /**
     * A flag whose definition its provider cannot read is listed apart, with its reason and source; looked up, it is
     * unreadable rather than absent, and `find()` says why instead of answering `null`.
     */
    public function testAnUnreadableFlagIsListedApartWithItsReason(): void
    {
        $catalog = new FlagCatalog([
            'yaml' => ConfiguredFlagProvider::fromConfiguration(['alpha' => ['active' => true]]),
            'db' => new PartlyUnreadableProvider(['beta' => true], ['zeta' => 'Not JSON.', 'gamma' => 'No rules.']),
        ]);

        self::assertSame(['alpha', 'beta'], array_map(static fn (CatalogEntry $entry): string => $entry->declared->name->value, $catalog->entries()));

        $unreadable = $catalog->unreadable();
        self::assertSame(['gamma', 'zeta'], array_map(static fn (UnreadableEntry $entry): string => $entry->name, $unreadable));
        self::assertSame('No rules.', $unreadable[0]->reason);
        self::assertSame('db', $unreadable[0]->source);

        $lookup = $catalog->lookup(FlagName::of('zeta'));
        self::assertInstanceOf(UnreadableEntry::class, $lookup);
        self::assertSame('Not JSON.', $lookup->reason);
        self::assertSame('db', $lookup->source);
        self::assertInstanceOf(CatalogEntry::class, $catalog->lookup(FlagName::of('beta')));
        self::assertNull($catalog->lookup(FlagName::of('absent')));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('Not JSON.');
        $catalog->find(FlagName::of('zeta'));
    }

    /**
     * Precedence holds for unreadable flags as for the others: a flag an earlier provider declares hides a later
     * unreadable one, and an unreadable flag hides a later provider's definition, as the evaluator stops at it.
     */
    public function testAnUnreadableFlagTakesItsPlaceInThePrecedence(): void
    {
        $catalog = new FlagCatalog([
            'yaml' => ConfiguredFlagProvider::fromConfiguration(['alpha' => ['active' => true]]),
            'db' => new PartlyUnreadableProvider([], ['alpha' => 'Shadowed.', 'beta' => 'Not JSON.']),
            'other' => new PartlyUnreadableProvider(['beta' => true, 'gamma' => false], ['beta' => 'Hidden too.']),
        ]);

        self::assertSame(['alpha', 'gamma'], array_map(static fn (CatalogEntry $entry): string => $entry->declared->name->value, $catalog->entries()));
        self::assertSame(['beta'], array_map(static fn (UnreadableEntry $entry): string => $entry->name, $catalog->unreadable()));
        self::assertSame('db', $catalog->unreadable()[0]->source);
        self::assertSame('yaml', $catalog->find(FlagName::of('alpha'))?->source);
    }

    public function testAProviderThatCannotDiagnoseReportsNothingUnreadable(): void
    {
        $catalog = new FlagCatalog(['remote' => new CountingProvider(), 'yaml' => ConfiguredFlagProvider::fromConfiguration(['beta' => ['active' => true]])]);

        self::assertSame([], $catalog->unreadable());
    }

    public function testWithoutAStoreNothingIsStored(): void
    {
        $catalog = new FlagCatalog(['yaml' => ConfiguredFlagProvider::fromConfiguration(['beta' => ['active' => true]])]);

        self::assertNull($catalog->find(FlagName::of('beta'))?->stored);
    }
}
