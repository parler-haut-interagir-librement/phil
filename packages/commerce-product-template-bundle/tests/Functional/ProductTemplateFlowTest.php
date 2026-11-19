<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use LogicException;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Exception\InvalidConfiguration;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\LineConfiguration;
use Phil\CommerceBundle\Domain\ValueObject\OptionChoice;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionValue;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplateBundle\Application\CatalogGeneration;
use Phil\CommerceProductTemplateBundle\Application\Mockups;
use Phil\CommerceProductTemplateBundle\Application\PrintProduction;
use Phil\CommerceProductTemplateBundle\Application\ProductGroups;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\Domain\ValueObject\TemplateOption;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Infrastructure\Doctrine\DoctrineProductTemplateRepository;
use Phil\CommerceProductTemplateBundle\PhilCommerceProductTemplateBundle;
use Phil\CommerceProductTemplateBundle\Tests\Functional\App\TestKernel;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Images;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\LootShirts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A template and a design stored, their products generated in the catalogue, stored and generated again without a
 * change, their mockups written to the store of images, their page read, and a line of an order configured with an
 * option the template declares, charged by the core; and a paid order of a loot box shirt produced by the message its
 * purchase dispatches, its print files and production order in the private store.
 */
#[CoversClass(PhilCommerceProductTemplateBundle::class)]
#[CoversClass(DoctrineProductTemplateRepository::class)]
#[CoversClass(DoctrineProductDesignRepository::class)]
#[CoversClass(DoctrineProductGroupRepository::class)]
final class ProductTemplateFlowTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * Symfony's `ServicesBundle` wires the event dispatcher only when `symfony/event-dispatcher` is available beside
     * `symfony/dependency-injection`; installed on its own for development, no kernel boots, so the suite runs from an
     * application that requires an HTTP kernel, such as a monorepo root.
     */
    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testATemplateAndADesignBecomeAGroupOfProductsSoldWithTheirOptions(): void
    {
        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $template = Shirts::productTemplate();
        $template->declareOptions(TemplateOption::of(OptionDefinition::ink('ink', 'Ink', [OptionChoice::of('glow', 'Glow in the dark', supplement: Shirts::cad(300))]), ['colour' => ['BK']]));
        $this->service(ProductTemplateRepository::class, ProductTemplateRepository::class)->add($template);
        $this->service(ProductDesignRepository::class, ProductDesignRepository::class)->add(Shirts::productDesign());
        $this->service(ProductDesignRepository::class, ProductDesignRepository::class)->add(Shirts::productDesign(Design::of('GATS001', Label::of(['en' => 'Dice']))));
        $entityManager->flush();
        $entityManager->clear();

        $generation = $this->service(CatalogGeneration::class, CatalogGeneration::class);
        self::assertCount(12, $generation->generate('NAQ001', 'unisex')->created);
        self::assertCount(6, $generation->generate('GATS001', 'unisex')->created);
        $entityManager->flush();
        $entityManager->clear();

        self::assertTrue($generation->generate('NAQ001', 'unisex')->changedNothing());
        $entityManager->flush();
        $entityManager->clear();

        self::assertSame(3_800, $this->service(ProductRepository::class, ProductRepository::class)->find('NAQ001FC2XBK')?->price->minorUnits);
        self::assertSame(['version' => 'C', 'size' => '2X', 'colour' => 'BK'], $this->service(ProductGroupRepository::class, ProductGroupRepository::class)->findMember('NAQ001FC2XBK')?->values());
        $members = array_map(static fn (GroupMember $member): string => $member->sku, $this->service(ProductGroupRepository::class, ProductGroupRepository::class)->membersOf('NAQ001:unisex'));
        $sorted = $members;
        sort($sorted);
        self::assertCount(12, $members);
        self::assertSame($sorted, $members);
        self::assertSame('NAQ001EC2XBK', $members[0]);

        $images = TestKernel::imagesDirectory();
        @mkdir($images.'/blanks', 0o777, true);
        @mkdir($images.'/art', 0o777, true);
        file_put_contents($images.'/blanks/front-bk.png', Images::png(8, 8, 0, 0, 0));
        file_put_contents($images.'/blanks/front-pk.png', Images::png(8, 8, 240, 160, 190));
        file_put_contents($images.'/blanks/back-bk.png', Images::png(8, 8, 20, 20, 20));
        file_put_contents($images.'/art/front.png', Images::png(4, 4, 255, 0, 0));
        file_put_contents($images.'/art/back.png', Images::png(4, 4, 0, 0, 255));
        $this->service(Mockups::class, Mockups::class)->render('NAQ001', 'unisex');
        $entityManager->flush();
        $entityManager->clear();

        $page = $this->service(ProductGroups::class, ProductGroups::class)->page('NAQ001', 'unisex', 'fr');
        self::assertNotNull($page);
        self::assertSame('Le gnome – T-shirt unisexe', $page->name);
        $sku = $page->skuFor(['version' => 'O', 'size' => 'M', 'colour' => 'BK'], 'E');
        self::assertSame('NAQ001EOMBK', $sku);
        $mockup = $page->members[0]['mockups']['front'] ?? '';
        self::assertFileExists($images.'/'.$mockup);

        $modifier = $this->service(OrderModifier::class, OrderModifier::class);
        $order = Order::open('P', Currency::of('CAD'));
        $modifier->add($order, SellableReference::of('product', $sku), 1, PricingContext::of(1, new DateTimeImmutable()), LineConfiguration::of(['ink' => OptionValue::chosen('glow')]));

        self::assertSame(2_500 + 300, $order->total()->minorUnits);

        $this->expectException(InvalidConfiguration::class);

        $modifier->add($order, SellableReference::of('product', 'NAQ001EOMPK'), 1, PricingContext::of(1, new DateTimeImmutable()), LineConfiguration::of(['ink' => OptionValue::chosen('glow')]));
    }

    public function testAPaidOrderIsProducedByItsMessage(): void
    {
        self::bootKernel();
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $this->service(ProductTemplateRepository::class, ProductTemplateRepository::class)->add(LootShirts::template());
        $this->service(ProductDesignRepository::class, ProductDesignRepository::class)->add(LootShirts::design());
        $entityManager->flush();
        $this->service(CatalogGeneration::class, CatalogGeneration::class)->generate('LOOT01', 'tee');
        $entityManager->flush();

        foreach (LootShirts::images() as $path => $bytes) {
            @mkdir(\dirname(TestKernel::imagesDirectory().'/'.$path), 0o777, true);
            file_put_contents(TestKernel::imagesDirectory().'/'.$path, $bytes);
        }

        $order = Order::open('F-1', Currency::of('CAD'));
        $this->service(OrderModifier::class, OrderModifier::class)->add($order, SellableReference::of('product', 'LOOT01FLMBLK'), 1, PricingContext::of(1, new DateTimeImmutable()), LineConfiguration::of(['epitaph' => OptionValue::chosen('Rest in pieces')]));
        $order->place(new DateTimeImmutable());
        $order->recordDraws($order->lines()[0]->reference, 'loot', ['4 2']);
        $this->service(OrderRepository::class, OrderRepository::class)->add($order);
        $entityManager->flush();

        $this->service('event_dispatcher', EventDispatcherInterface::class)->dispatch(new OrderPurchased('F-1', null, null, $order->total(), new DateTimeImmutable()));

        $json = TestKernel::productionDirectory().'/production-orders/F-1.json';
        self::assertFileExists($json);
        /** @var array{lines: list<array{sku: string, dice: array<string, string>, files: list<array{path: string}>}>} $production */
        $production = json_decode((string) file_get_contents($json), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('LOOT01FLMBLK', $production['lines'][0]['sku']);
        self::assertSame(['loot' => '42'], $production['lines'][0]['dice']);
        self::assertFileExists(TestKernel::productionDirectory().'/'.$production['lines'][0]['files'][0]['path']);
        self::assertSame(0o600, fileperms($json) & 0o777);
        self::assertSame('F-1', $this->service(PrintProduction::class, PrintProduction::class)->produce('F-1')->orderNumber);
    }

    public function testTheModuleWithoutTheCatalogueFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, withCatalogue: false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('register PhilCommerceBundle, from phil/commerce-bundle, and PhilCommerceCatalogBundle');

        $kernel->boot();
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
}
