<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use LogicException;
use Phil\CommercePosBundle\Application\CashDifferences;
use Phil\CommercePosBundle\Application\CashierSwitch;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\Contract\PaymentTerminal;
use Phil\CommercePosBundle\Application\Contract\TillDrawForm;
use Phil\CommercePosBundle\Application\Layaways;
use Phil\CommercePosBundle\Application\OfflineSales;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Application\RegisterGateway;
use Phil\CommercePosBundle\Application\RegisterPayments;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Application\TerminalAdapters;
use Phil\CommercePosBundle\Application\TerminalGateway;
use Phil\CommercePosBundle\Application\TillDraws;
use Phil\CommercePosBundle\Domain\Repository\CashSessionRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterRepository;
use Phil\CommercePosBundle\Domain\Repository\RegisterSaleRepository;
use Phil\CommercePosBundle\Infrastructure\Doctrine\DoctrinePosRepository;
use Phil\CommercePosBundle\Infrastructure\Http\OfflineSaleController;
use Phil\CommercePosBundle\Infrastructure\Terminal\FakePaymentTerminal;
use Phil\CommercePosBundle\PhilCommercePosBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

#[CoversClass(PhilCommercePosBundle::class)]
final class PhilCommercePosBundleTest extends TestCase
{
    public function testItWiresTheRegister(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_commerce_pos', $this->extension()->getAlias());

        foreach ([Registers::class, CashSessions::class, RegisterCheckout::class, RegisterPayments::class, Layaways::class, OfflineSales::class, OfflineSaleController::class] as $service) {
            self::assertTrue($container->getDefinition($service)->isPublic(), $service);
            self::assertTrue($container->getDefinition($service)->isAutowired(), $service);
        }

        self::assertSame([[]], $container->getDefinition(RegisterGateway::class)->getTag('phil_commerce.payment_gateway'));
        self::assertSame([[]], $container->getDefinition(OfflineSaleController::class)->getTag('controller.service_arguments'));
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame([['debit', 'credit']], $container->getDefinition(RegisterGateway::class)->getArguments());
        self::assertSame('Tip', $container->getDefinition(RegisterCheckout::class)->getArgument('$tipLabel'));
        self::assertSame(200_000, $container->getDefinition(Layaways::class)->getArgument('$deposit'));
    }

    public function testTheConfiguration(): void
    {
        $container = $this->load(['recorded_methods' => ['debit', 'cheque_2'], 'tip_label' => 'Pourboire', 'layaway_deposit' => 1_000_000]);

        self::assertSame([['debit', 'cheque_2']], $container->getDefinition(RegisterGateway::class)->getArguments());
        self::assertSame('Pourboire', $container->getDefinition(RegisterCheckout::class)->getArgument('$tipLabel'));
        self::assertSame(1_000_000, $container->getDefinition(Layaways::class)->getArgument('$deposit'));
        self::assertSame([[]], $this->load(['recorded_methods' => []])->getDefinition(RegisterGateway::class)->getArguments());
        self::assertSame(0, $this->load(['layaway_deposit' => 0])->getDefinition(Layaways::class)->getArgument('$deposit'));
        self::assertSame([[str_repeat('a', 32)]], $this->load(['recorded_methods' => [str_repeat('a', 32)]])->getDefinition(RegisterGateway::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badConfigurations(): iterable
    {
        yield 'cash recorded' => [['recorded_methods' => ['cash']]];
        yield 'an uppercase method' => [['recorded_methods' => ['Debit']]];
        yield 'a digit first' => [['recorded_methods' => ['1debit']]];
        yield 'a long method' => [['recorded_methods' => [str_repeat('a', 33)]]];
        yield 'a method and a newline' => [['recorded_methods' => ["debit\n"]]];
        yield 'a numeric method' => [['recorded_methods' => [7]]];
        yield 'an empty tip label' => [['tip_label' => '']];
        yield 'a negative deposit' => [['layaway_deposit' => -1]];
        yield 'more than everything' => [['layaway_deposit' => 1_000_001]];
        yield 'a negative cash difference threshold' => [['cash_difference_threshold' => -1]];
        yield 'terminals taking cash' => [['terminals' => ['method' => 'cash']]];
        yield 'an uppercase terminal method' => [['terminals' => ['method' => 'Card']]];
        yield 'a terminal method with a digit first' => [['terminals' => ['method' => '1card']]];
        yield 'a terminal method after a space' => [['terminals' => ['method' => ' card']]];
        yield 'a terminal method before a dash' => [['terminals' => ['method' => 'card-']]];
        yield 'a long terminal method' => [['terminals' => ['method' => str_repeat('a', 33)]]];
        yield 'a terminal method and a newline' => [['terminals' => ['method' => "card\n"]]];
        yield 'a numeric terminal method' => [['terminals' => ['method' => 7]]];
        yield 'the cashier switch without its limiter' => [['cashier_pin' => ['enabled' => true]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('badConfigurations')]
    public function testABadConfigurationIsRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load($config);
    }

    public function testTheTerminalsTheCashDifferencesAndTheTillDraws(): void
    {
        $container = $this->load([]);

        self::assertSame([PaymentTerminal::class, TillDrawForm::class], array_keys(array_intersect_key($container->getAutoconfiguredInstanceof(), [PaymentTerminal::class => true, TillDrawForm::class => true])));
        self::assertSame([PhilCommercePosBundle::PAYMENT_TERMINAL_TAG => [[]]], $container->getAutoconfiguredInstanceof()[PaymentTerminal::class]->getTags());
        self::assertSame([PhilCommercePosBundle::TILL_DRAW_FORM_TAG => [[]]], $container->getAutoconfiguredInstanceof()[TillDrawForm::class]->getTags());

        $adapters = $container->getDefinition(TerminalAdapters::class)->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $adapters);
        self::assertSame(PhilCommercePosBundle::PAYMENT_TERMINAL_TAG, $adapters->getTag());

        $gateway = $container->getDefinition(TerminalGateway::class);
        self::assertTrue($gateway->isAutowired());
        self::assertSame('card_present', $gateway->getArgument('$method'));
        self::assertSame([[]], $gateway->getTag('phil_commerce.payment_gateway'));

        $draws = $container->getDefinition(TillDraws::class);
        self::assertTrue($draws->isPublic());
        self::assertTrue($draws->isAutowired());
        $forms = $draws->getArgument('$forms');
        self::assertInstanceOf(TaggedIteratorArgument::class, $forms);
        self::assertSame(PhilCommercePosBundle::TILL_DRAW_FORM_TAG, $forms->getTag());

        $differences = $container->getDefinition(CashDifferences::class);
        self::assertTrue($differences->isPublic());
        self::assertTrue($differences->isAutowired());
        self::assertNull($differences->getArgument('$threshold'));
        self::assertSame(0, $this->load(['cash_difference_threshold' => 0])->getDefinition(CashDifferences::class)->getArgument('$threshold'));
        self::assertSame(500, $this->load(['cash_difference_threshold' => 500])->getDefinition(CashDifferences::class)->getArgument('$threshold'));

        self::assertFalse($container->hasDefinition(FakePaymentTerminal::class), 'the fake terminal is for tests and development only');
        self::assertSame('card', $this->load(['terminals' => ['method' => 'card']])->getDefinition(TerminalGateway::class)->getArgument('$method'));
        self::assertSame(str_repeat('a', 32), $this->load(['terminals' => ['method' => str_repeat('a', 32)]])->getDefinition(TerminalGateway::class)->getArgument('$method'));
    }

    public function testTheFakeTerminalIsRegisteredOnRequest(): void
    {
        $fake = $this->load(['terminals' => ['fake' => true]])->getDefinition(FakePaymentTerminal::class);

        self::assertTrue($fake->isPublic());
        self::assertSame(['%kernel.secret%'], $fake->getArguments());
        self::assertSame([PhilCommercePosBundle::PAYMENT_TERMINAL_TAG => [[]], 'kernel.reset' => [['method' => 'reset']]], $fake->getTags());
    }

    public function testTheCashierSwitchStaysOffUntilEnabledWithALimiter(): void
    {
        foreach ([[], ['cashier_pin' => ['enabled' => false, 'limiter' => 'limiter.cashier_pin']]] as $config) {
            $switch = $this->load($config)->getDefinition(CashierSwitch::class);
            self::assertTrue($switch->isPublic());
            self::assertTrue($switch->isAutowired());
            self::assertSame(['$hasher' => null, '$limiter' => null], $switch->getArguments());
        }

        $container = $this->load(['cashier_pin' => ['enabled' => true, 'limiter' => 'limiter.cashier_pin']]);
        $switch = $container->getDefinition(CashierSwitch::class);
        $hasher = $switch->getArgument('$hasher');
        $limiter = $switch->getArgument('$limiter');
        self::assertInstanceOf(Reference::class, $hasher);
        self::assertInstanceOf(Reference::class, $limiter);
        self::assertSame('phil_commerce_pos.cashier_pin_hasher', (string) $hasher);
        self::assertSame('limiter.cashier_pin', (string) $limiter);
        self::assertSame(NativePasswordHasher::class, $container->getDefinition('phil_commerce_pos.cashier_pin_hasher')->getClass());
    }

    public function testWithDoctrineEverythingIsStored(): void
    {
        $container = $this->load([]);

        foreach ([RegisterRepository::class, CashSessionRepository::class, RegisterSaleRepository::class] as $port) {
            self::assertSame(DoctrinePosRepository::class, (string) $container->getAlias($port));
            self::assertTrue($container->getAlias($port)->isPublic());
        }

        $argument = $container->getDefinition(DoctrinePosRepository::class)->getArgument(0);
        self::assertInstanceOf(Reference::class, $argument);
        self::assertSame('doctrine.orm.entity_manager', (string) $argument);
    }

    public function testWithDoctrineTheMappingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->setParameter('kernel.bundles', ['DoctrineBundle' => DoctrineBundle::class]);
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilCommercePosBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname((string) new ReflectionClass(PhilCommercePosBundle::class)->getFileName()).'/Domain/Model',
            'prefix' => 'Phil\CommercePosBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsRegistered(): void
    {
        $container = $this->load([], []);

        foreach ([RegisterGateway::class, Registers::class, CashSessions::class, OfflineSaleController::class, DoctrinePosRepository::class] as $service) {
            self::assertFalse($container->hasDefinition($service), $service);
        }

        self::assertFalse($container->hasAlias(RegisterRepository::class));
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = $this->withCore();
        $container->registerExtension($this->extensionNamed('doctrine'));

        $this->prepending()->prepend($container);

        self::assertSame([], $container->getExtensionConfig('doctrine'));
    }

    public function testItRefusesToPrependWithoutTheCore(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('PhilCommercePosBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');

        $this->prepending()->prepend(new ContainerBuilder());
    }

    /**
     * @param array<string, mixed>  $config
     * @param array<string, string> $bundles
     */
    private function load(array $config, array $bundles = ['DoctrineBundle' => DoctrineBundle::class]): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function withCore(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->extensionNamed('phil_commerce'));

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilCommercePosBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }

    private function prepending(): PrependExtensionInterface
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        return $extension;
    }

    private function extensionNamed(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
