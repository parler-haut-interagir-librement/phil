<?php

declare(strict_types=1);

namespace Phil\BulkActions\Tests\Unit;

use Phil\BulkActions\BulkSelector;
use Phil\BulkActions\Exception\AccessDenied;
use Phil\BulkActions\Exception\InvalidInput;
use Phil\BulkActions\Exception\UnknownAction;
use Phil\BulkActions\Internal\Guard;
use Phil\BulkActions\Selection\FilterCriteria;
use Phil\BulkActions\Selection\PendingSelection;
use Phil\BulkActions\Selection\Selection;
use Phil\BulkActions\Selection\SelectionToken;
use Phil\BulkActions\Target\Limits;
use Phil\BulkActions\Tests\Support\Fixture;
use Phil\BulkActions\Tests\Support\Product;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BulkSelector::class)]
#[CoversClass(Guard::class)]
#[CoversClass(PendingSelection::class)]
#[CoversClass(SelectionToken::class)]
#[CoversClass(AccessDenied::class)]
#[CoversClass(InvalidInput::class)]
#[CoversClass(UnknownAction::class)]
final class BulkSelectorTest extends TestCase
{
    private Fixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new Fixture();
        $this->fixture->products->seed(60);
    }

    public function testASelectionIsFiledUnderTheFingerprintOfItsToken(): void
    {
        $selection = Selection::identifiers(['p0001', 'p0002'], ['p0001' => '1']);
        $token = $this->fixture->selector->select('ana', 'products', 'edit', $selection, Fixture::now());
        $pending = $this->fixture->selections->peek($token->fingerprint());

        self::assertNotNull($pending);
        self::assertSame('ana', $pending->actor);
        self::assertSame('products', $pending->target);
        self::assertSame('edit', $pending->action);
        self::assertEquals($selection, $pending->selection);
        self::assertEquals(Fixture::now()->modify('+900 seconds'), $pending->expiresAt);
        self::assertSame(1, $this->fixture->selections->count());
        self::assertSame(['run:ana:products:edit'], $this->fixture->authorizer->asked);
    }

    public function testTheLifetimeComesFromTheLimits(): void
    {
        $fixture = new Fixture(new Limits(lifetimeSeconds: 120));
        $fixture->products->seed(1);

        $token = $fixture->selector->select('ana', 'products', 'edit', Selection::identifiers(['p0001']), Fixture::now());

        self::assertEquals(Fixture::now()->modify('+2 minutes'), $fixture->selections->peek($token->fingerprint())?->expiresAt);
    }

    public function testAPersonWhoMayNotRunTheActionSelectsNothing(): void
    {
        try {
            $this->fixture->selector->select('bob', 'products', 'edit', Selection::identifiers(['p0001']), Fixture::now());
            self::fail('A selection was filed for a person without the right.');
        } catch (AccessDenied $accessDenied) {
            self::assertEquals(AccessDenied::toRun('products', 'edit'), $accessDenied);
        }

        self::assertSame(0, $this->fixture->selections->count());
    }

    public function testTheTargetAndTheActionComeFromTheConfiguration(): void
    {
        try {
            $this->fixture->selector->select('ana', Product::class, 'edit', Selection::identifiers(['p0001']), Fixture::now());
            self::fail('A class name was taken for a target.');
        } catch (UnknownAction $unknownAction) {
            self::assertEquals(UnknownAction::target(Product::class), $unknownAction);
        }

        $this->expectExceptionObject(UnknownAction::action('products', 'delete'));
        $this->fixture->selector->select('ana', 'products', 'delete', Selection::identifiers(['p0001']), Fixture::now());
    }

    public function testAPageSendsAtMostTheIdentifiersOfTheLimits(): void
    {
        $fifty = array_map(static fn (int $i): string => \sprintf('p%04d', $i), range(1, 50));
        $this->fixture->selector->select('ana', 'products', 'edit', Selection::identifiers($fifty), Fixture::now());

        $this->expectExceptionObject(InvalidInput::because('selection', 'must name 50 items at most; select by filter instead'));
        $this->fixture->selector->select('ana', 'products', 'edit', Selection::identifiers([...$fifty, 'p0051']), Fixture::now());
    }

    public function testAFilterMatchesAtMostTheItemsOfTheLimits(): void
    {
        $fixture = new Fixture(new Limits(maxItems: 60));
        $fixture->products->seed(61);

        $filter = FilterCriteria::of('status', ['status' => 'draft']);

        $fixture->selector->select('ana', 'products', 'edit', Selection::filter($filter, ['p0061']), Fixture::now());

        $this->expectExceptionObject(InvalidInput::because('selection', 'must match 60 items at most; narrow the filter'));
        $fixture->selector->select('ana', 'products', 'edit', Selection::filter($filter), Fixture::now());
    }

    public function testAFilterUnticksAtMostTheIdentifiersOfTheLimits(): void
    {
        $filter = FilterCriteria::of('status', ['status' => 'draft']);
        $fifty = array_map(static fn (int $i): string => \sprintf('p%04d', $i), range(1, 50));
        $this->fixture->selector->select('ana', 'products', 'edit', Selection::filter($filter, $fifty), Fixture::now());

        $this->expectExceptionObject(InvalidInput::because('selection', 'must untick 50 items at most'));
        $this->fixture->selector->select('ana', 'products', 'edit', Selection::filter($filter, [...$fifty, 'p0051']), Fixture::now());
    }

    public function testAFilterTheRepositoryDoesNotKnowIsRefused(): void
    {
        $this->expectExceptionObject(InvalidInput::because('filter "price"', 'must be one the target "products" supports'));

        $this->fixture->selector->select('ana', 'products', 'edit', Selection::filter(FilterCriteria::of('price')), Fixture::now());
    }
}
