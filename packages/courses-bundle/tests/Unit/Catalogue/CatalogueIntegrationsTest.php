<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Catalogue;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CoursesBundle\Domain\Catalogue\CatalogueAudience;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\CoursePrice;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Commerce\CoursePurchases;
use Phil\CoursesBundle\Infrastructure\Commerce\CourseSellables;
use Phil\CoursesBundle\Infrastructure\Organizations\OrganizationsLearnerUnits;
use Phil\CoursesBundle\Infrastructure\Twig\CoursesExtension;
use Phil\CoursesBundle\Infrastructure\Twig\KitComponents;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\OrganizationsBundle\Application\OrganizationAccess;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\RoleCatalog;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Application\UnitOfWork;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;
use Phil\OrganizationsBundle\Infrastructure\Memory\InMemoryOrganizationStore;
use Phil\Slug\TransliteratingSlugger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The catalogues with the packages they integrate: the courses sold through phil/commerce-bundle and their buyers
 * enrolled once paid, the units of phil/organizations-bundle whose catalogues reach a member, and the prices written
 * on the pages.
 */
#[CoversClass(CourseSellables::class)]
#[CoversClass(CoursePurchases::class)]
#[CoversClass(OrganizationsLearnerUnits::class)]
#[CoversClass(CoursesExtension::class)]
final class CatalogueIntegrationsTest extends TestCase
{
    public function testACourseSoldIsASellableAtItsPriceInTheCatalogue(): void
    {
        $store = new Store();
        $catalogue = $store->catalogues->create('All');
        $sold = $store->courses->create('Welding');
        $free = $store->courses->create('Drawing');
        $store->catalogues->offer($catalogue->id, $sold->id, price: CoursePrice::of(4_250, 'CAD'));
        $store->catalogues->offer($catalogue->id, $free->id);

        $sellables = new CourseSellables($store->catalogues, $store->courseStore, 'education');

        self::assertTrue($sellables->supports('course'));
        self::assertFalse($sellables->supports('product'));
        $sellable = $sellables->resolve(SellableReference::fromValue('course:'.$catalogue->id->value.'/'.$sold->id->value));
        self::assertSame(['Welding', 4_250, 'CAD', 'education'], [$sellable?->description, $sellable?->unitPrice->minorUnits, $sellable?->unitPrice->currency->code, $sellable?->taxCategory]);

        foreach ([$catalogue->id->value.'/'.$free->id->value, $catalogue->id->value, $catalogue->id->value.'/'.$sold->id->value.'/x', 'unknown/'.$sold->id->value, $catalogue->id->value.'/-'] as $id) {
            self::assertNull($sellables->resolve(SellableReference::of('course', $id)), $id);
        }

        self::assertSame([$catalogue->id->value, $sold->id->value], array_map(static fn (object $id): string => $id->value ?? '', CourseSellables::named($catalogue->id->value.'/'.$sold->id->value) ?? []));
        $store->connection->delete('phil_course', ['id' => $sold->id->value]);
        self::assertNull($sellables->resolve(SellableReference::of('course', $catalogue->id->value.'/'.$sold->id->value)));
    }

    public function testTheBuyerIsEnrolledOnceTheOrderIsPaid(): void
    {
        $store = new Store();
        $catalogue = $store->catalogues->create('All');
        [$welding, $painting, $free] = [$store->courses->create('Welding'), $store->courses->create('Painting'), $store->courses->create('Free')];
        $store->catalogues->offer($catalogue->id, $welding->id, price: CoursePrice::of(4_250, 'CAD'));
        $store->catalogues->offer($catalogue->id, $painting->id, price: CoursePrice::of(1_000, 'CAD'));
        $store->catalogues->offer($catalogue->id, $free->id);

        $orders = new class implements OrderRepository {
            /** @var array<string, Order> */
            public array $orders = [];

            #[Override]
            public function find(string $number): ?Order
            {
                return $this->orders[$number] ?? null;
            }

            #[Override]
            public function add(Order $order): void
            {
                $this->orders[$order->number] = $order;
            }
        };
        $cad = Currency::of('CAD');
        $order = Order::open('A-1', $cad);

        foreach (['course:'.$catalogue->id->value.'/'.$welding->id->value, 'course:'.$catalogue->id->value.'/'.$painting->id->value, 'course:'.$catalogue->id->value.'/'.$free->id->value, 'course:broken', 'product:'.$catalogue->id->value.'/'.$welding->id->value] as $value) {
            $order->add(Sellable::of(SellableReference::fromValue($value), 'Line', Money::of(100, $cad), 'education'), 1, Money::of(100, $cad));
        }

        $orders->add($order);
        $purchases = new CoursePurchases($orders, $store->catalogues);
        $event = new OrderPurchased('A-1', null, null, Money::of(500, $cad), new DateTimeImmutable());
        self::assertSame([], $purchases($event));

        $order->assignCustomer('ada');
        $enrolled = $purchases($event);
        self::assertSame(['Welding', 'Painting'], array_map(static fn (Enrollment $enrollment): string => $store->courses->get($enrollment->course)->title, $enrolled));
        self::assertSame(['ada', 'ada'], array_map(static fn (Enrollment $enrollment): string => $enrollment->learner->value, $enrolled));
        self::assertSame([$enrolled[0]->id->value], array_map(static fn (Enrollment $enrollment): string => $enrollment->id->value, \array_slice($purchases($event), 0, 1)));
        self::assertNull($store->enrollments->of($free->id, LearnerId::of('ada')));

        self::assertSame([], $purchases(new OrderPurchased('A-2', null, 'ada', Money::of(500, $cad), new DateTimeImmutable())));
        $order->assignCustomer('ada/2');
        self::assertSame([], $purchases($event));
    }

    public function testALineThatCannotBeGrantedDoesNotStopTheOnesAfterIt(): void
    {
        $store = new Store();
        $catalogue = $store->catalogues->create('All');
        [$welding, $free] = [$store->courses->create('Welding'), $store->courses->create('Free')];
        $store->catalogues->offer($catalogue->id, $welding->id, price: CoursePrice::of(4_250, 'CAD'));
        $store->catalogues->offer($catalogue->id, $free->id);

        $orders = new class implements OrderRepository {
            public ?Order $order = null;

            #[Override]
            public function find(string $number): ?Order
            {
                return $this->order;
            }

            #[Override]
            public function add(Order $order): void
            {
                $this->order = $order;
            }
        };
        $cad = Currency::of('CAD');
        $order = Order::open('B-1', $cad);
        $order->assignCustomer('ada');

        foreach (['course:broken', 'course:'.$catalogue->id->value.'/'.$free->id->value, 'course:'.$catalogue->id->value.'/'.$welding->id->value] as $value) {
            $order->add(Sellable::of(SellableReference::fromValue($value), 'Line', Money::of(100, $cad), 'education'), 1, Money::of(100, $cad));
        }

        $orders->add($order);
        $enrolled = new CoursePurchases($orders, $store->catalogues)(new OrderPurchased('B-1', null, null, Money::of(300, $cad), new DateTimeImmutable()));

        self::assertSame(['Welding'], array_map(static fn (Enrollment $enrollment): string => $store->courses->get($enrollment->course)->title, $enrolled));
    }

    public function testTheUnitsOfAMemberAndThoseAboveReachTheirCatalogues(): void
    {
        $organizationStore = new InMemoryOrganizationStore();
        $clock = new Store()->clock;
        $work = new UnitOfWork($organizationStore);
        $access = new OrganizationAccess($organizationStore, RoleCatalog::organization());
        $organizations = new Organizations($organizationStore, $work, $access, new TransliteratingSlugger(), $clock);
        $units = new Units($organizationStore, $organizationStore, $work, $access, new UnitAccess($organizationStore, $organizationStore, $access, RoleCatalog::unit()), $clock);
        $owner = MemberId::fromString('ann');
        $acme = $organizations->create('Acme Guild', $owner);
        $other = $organizations->create('Other Guild', MemberId::fromString('ada'));
        $sales = $units->create($acme->id, $owner, 'Sales');
        $east = $units->create($acme->id, $owner, 'East', $sales->id);
        $units->create($acme->id, $owner, 'West', $sales->id);
        $north = $units->create($other->id, MemberId::fromString('ada'), 'North');
        $units->addMember($east->id, $owner, MemberId::fromString('ann'), ['member']);
        $units->addMember($north->id, MemberId::fromString('ada'), MemberId::fromString('ada'), ['member']);

        $learnerUnits = new OrganizationsLearnerUnits($organizations, new UnitTree($organizationStore));
        $expected = [$sales->id->value, $east->id->value];
        sort($expected);
        self::assertSame($expected, $learnerUnits->units(LearnerId::of('ann')));
        self::assertSame([$north->id->value], $learnerUnits->units(LearnerId::of('ada')));
        self::assertSame([], $learnerUnits->units(LearnerId::of('eve')));
        self::assertSame([], $learnerUnits->units(LearnerId::of('a:b')));

        $ownUnitsOnly = new OrganizationsLearnerUnits($organizations, new UnitTree($organizationStore), false);
        self::assertSame([$east->id->value], $ownUnitsOnly->units(LearnerId::of('ann')));
        self::assertSame([$north->id->value], $ownUnitsOnly->units(LearnerId::of('ada')));

        $store = new Store();
        $store->units->units = ['ann' => $learnerUnits->units(LearnerId::of('ann'))];

        $catalogue = $store->catalogues->create('Sales', '', CatalogueAudience::Unit, $sales->id->value);
        self::assertSame($catalogue->id->value, $store->catalogues->shown($catalogue->id, LearnerId::of('ann'))->id->value);
    }

    public function testAPriceIsWrittenAsTheLocaleWritesIt(): void
    {
        $extension = new CoursesExtension(new KitComponents('ui'));
        self::assertSame('ui:Button', $extension->kit('Button'));
        self::assertSame('$42.50', $extension->price(CoursePrice::of(4_250, 'USD')));
        self::assertStringStartsWith('42,50', $extension->price(CoursePrice::of(4_250, 'CAD'), 'fr'));
        self::assertSame('¥4,250', $extension->price(CoursePrice::of(4_250, 'JPY'), 'en'));
        self::assertSame('BHD 4.250', str_replace("\u{a0}", ' ', $extension->price(CoursePrice::of(4_250, 'BHD'), 'en')));
        self::assertStringContainsString('42.50', $extension->price(CoursePrice::of(4_250, 'XYZ'), 'en'));
        self::assertSame('course', CatalogueEntry::SELLABLE_TYPE);
    }
}
