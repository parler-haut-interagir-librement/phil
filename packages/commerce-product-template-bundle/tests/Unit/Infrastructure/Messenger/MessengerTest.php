<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Infrastructure\Messenger;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\CatalogGeneration;
use Phil\CommerceProductTemplateBundle\Application\Message\ProducePrintFiles;
use Phil\CommerceProductTemplateBundle\Application\Port\ProductionStore;
use Phil\CommerceProductTemplateBundle\Application\PrintProduction;
use Phil\CommerceProductTemplateBundle\Application\TemplateOptions;
use Phil\CommerceProductTemplateBundle\Infrastructure\Fonts\ConfiguredFonts;
use Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProduceOnPurchase;
use Phil\CommerceProductTemplateBundle\Infrastructure\Messenger\ProducePrintFilesHandler;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryDesigns;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryImages;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryOrders;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryProductionStore;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryTemplates;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\LootShirts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\Money\Currency;
use Phil\PrintCompositor\PrintCompositor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ProducePrintFilesHandler::class)]
#[CoversClass(ProduceOnPurchase::class)]
#[CoversClass(ProducePrintFiles::class)]
final class MessengerTest extends TestCase
{
    public function testAPurchaseDispatchesTheOrderNumberOnly(): void
    {
        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $messages = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->messages[] = $message;

                return new Envelope($message, $stamps);
            }
        };

        new ProduceOnPurchase($bus)(new OrderPurchased('R-1', 'web', 'customer-7', Shirts::cad(2_500), new DateTimeImmutable()));

        self::assertEquals([new ProducePrintFiles('R-1')], $bus->messages);
    }

    public function testTheHandlerProducesTheOrderAndAgainWithoutWriting(): void
    {
        $store = new InMemoryProductionStore();
        $orders = $this->orders(true);
        $handler = new ProducePrintFilesHandler($this->production($orders, $store));

        $handler(new ProducePrintFiles('R-1'));
        $writes = $store->writes;
        $handler(new ProducePrintFiles('R-1'));

        self::assertContains('production-orders/R-1.json', $writes);
        self::assertSame($writes, $store->writes);
    }

    public function testDrawsStillToMakeAreRetried(): void
    {
        try {
            new ProducePrintFilesHandler($this->production($this->orders(false), new InMemoryProductionStore()))(new ProducePrintFiles('R-1'));
            self::fail('The message is retried.');
        } catch (RecoverableMessageHandlingException $recoverableMessageHandlingException) {
            self::assertStringContainsString('still has options to draw', $recoverableMessageHandlingException->getMessage());
            self::assertSame(0, $recoverableMessageHandlingException->getCode());
        }
    }

    public function testAStoreThatCannotWriteIsRetried(): void
    {
        $store = new class implements ProductionStore {
            public function read(string $path): ?string
            {
                return null;
            }

            public function has(string $path): bool
            {
                return false;
            }

            public function write(string $path, string $bytes): void
            {
                throw new RuntimeException('The disk is full.');
            }
        };

        try {
            new ProducePrintFilesHandler($this->production($this->orders(true), $store))(new ProducePrintFiles('R-1'));
            self::fail('The message is retried.');
        } catch (RecoverableMessageHandlingException $recoverableMessageHandlingException) {
            self::assertStringContainsString('The disk is full.', $recoverableMessageHandlingException->getMessage());
            self::assertSame(0, $recoverableMessageHandlingException->getCode());
        }
    }

    public function testAnythingElseRefusedIsNeverRetried(): void
    {
        try {
            new ProducePrintFilesHandler($this->production($this->orders(true), new InMemoryProductionStore()))(new ProducePrintFiles('R-2'));
            self::fail('The message is never retried.');
        } catch (UnrecoverableMessageHandlingException $unrecoverableMessageHandlingException) {
            self::assertStringContainsString('No order has the number R-2.', $unrecoverableMessageHandlingException->getMessage());
            self::assertSame(0, $unrecoverableMessageHandlingException->getCode());
        }
    }

    private function orders(bool $drawn): InMemoryOrders
    {
        $orders = new InMemoryOrders();
        $order = Order::open('R-1', Currency::of('CAD'));
        $reference = SellableReference::of('product', 'LOOT01FLMBLK');
        $order->add(Sellable::of($reference, 'Tee', Shirts::cad(2_500), 'goods', $this->options()->optionsOf($reference, Currency::of('CAD'))), 1, Shirts::cad(2_500));
        $order->place(new DateTimeImmutable());

        if ($drawn) {
            $order->recordDraws($order->lines()[0]->reference, 'loot', ['1 2']);
        }

        $orders->add($order);

        return $orders;
    }

    private ?InMemoryTemplates $templates = null;

    private ?InMemoryDesigns $designs = null;

    private ?InMemoryGroups $groups = null;

    private function options(): TemplateOptions
    {
        if (!$this->groups instanceof InMemoryGroups) {
            $this->templates = new InMemoryTemplates(LootShirts::template());
            $this->designs = new InMemoryDesigns(LootShirts::design());
            $this->groups = new InMemoryGroups();
            new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en')->generate('LOOT01', 'tee');
        }

        \assert($this->templates instanceof InMemoryTemplates && $this->designs instanceof InMemoryDesigns);

        return new TemplateOptions($this->groups, $this->templates, $this->designs, 'product');
    }

    private function production(InMemoryOrders $orders, ProductionStore $store): PrintProduction
    {
        $options = $this->options();
        \assert($this->templates instanceof InMemoryTemplates && $this->designs instanceof InMemoryDesigns && $this->groups instanceof InMemoryGroups);
        $images = new InMemoryImages();
        $images->images = LootShirts::images();

        return new PrintProduction($orders, $this->templates, $this->designs, $this->groups, new VariantPlanner(), $options, $images, $store, new ConfiguredFonts([LootShirts::FONT => LootShirts::font()]), new PrintCompositor(), 'product', 'en', 'print-files', 'production-orders');
    }
}
