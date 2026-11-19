<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle;

use LogicException;
use Override;
use Phil\CommercePosBundle\Application\CashDifferences;
use Phil\CommercePosBundle\Application\CashierSwitch;
use Phil\CommercePosBundle\Application\CashSessions;
use Phil\CommercePosBundle\Application\Contract\PaymentTerminal;
use Phil\CommercePosBundle\Application\Contract\TillDrawForm;
use Phil\CommercePosBundle\Application\CustomerDisplay;
use Phil\CommercePosBundle\Application\Layaways;
use Phil\CommercePosBundle\Application\OfflineSales;
use Phil\CommercePosBundle\Application\RegisterCheckout;
use Phil\CommercePosBundle\Application\RegisterGateway;
use Phil\CommercePosBundle\Application\RegisterPayments;
use Phil\CommercePosBundle\Application\Registers;
use Phil\CommercePosBundle\Application\SalePresets;
use Phil\CommercePosBundle\Application\TerminalAdapters;
use Phil\CommercePosBundle\Application\TerminalGateway;
use Phil\CommercePosBundle\Application\TerminalPayments;
use Phil\CommercePosBundle\Application\TillDraws;
use Phil\CommercePosBundle\Infrastructure\Doctrine\DoctrinePosRepository;
use Phil\CommercePosBundle\Infrastructure\Http\OfflineSaleController;
use Phil\CommercePosBundle\Infrastructure\Http\TerminalNotificationController;
use Phil\CommercePosBundle\Infrastructure\Terminal\FakePaymentTerminal;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * The register, a module of the core of commerce: tills and their sessions — float, cash in and out, the count and
 * the end-of-day report —, cash rounded to the coins in use, recorded methods, connected terminals driven by the
 * server, held sales, tips, layaways, sale presets, the customer display, draws entered at the counter, the cashier
 * switch by PIN, and the sales the main register rang up offline, imported once it is back online.
 *
 * The module plugs into `phil/commerce-bundle`, and refuses to load without it. Every service reads its storage and
 * the core's, both Doctrine's: they are registered with DoctrineBundle only.
 */
final class PhilCommercePosBundle extends AbstractBundle
{
    /** The tag of the payment terminal adapters, given by autoconfiguration. */
    public const string PAYMENT_TERMINAL_TAG = 'phil_commerce_pos.payment_terminal';

    /** The tag of the forms that enter draws at the counter, given by autoconfiguration. */
    public const string TILL_DRAW_FORM_TAG = 'phil_commerce_pos.till_draw_form';

    private const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    #[Override]
    protected string $extensionAlias = 'phil_commerce_pos';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('recorded_methods')
                    ->info('The methods the register records without processing them: a card on a terminal that is not connected, a cheque.')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $method): bool => !\is_string($method) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method) || 'cash' === $method)
                            ->thenInvalid('A recorded method is a payment method of up to 32 lowercase letters, digits or "_", and not "cash": %s.')
                        ->end()
                    ->end()
                    ->defaultValue(['debit', 'credit'])
                ->end()
                ->scalarNode('tip_label')
                    ->info('How a tip reads on the receipt.')
                    ->defaultValue('Tip')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('layaway_deposit')
                    ->info('The share of an order paid before it is put aside, in parts per million: 200000 for 20 %.')
                    ->defaultValue(200_000)
                    ->min(0)
                    ->max(1_000_000)
                ->end()
                ->integerNode('cash_difference_threshold')
                    ->info('The cash over or short, in minor units, beyond which the cash-difference report flags a session; none when null.')
                    ->defaultNull()
                    ->min(0)
                ->end()
                ->arrayNode('terminals')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('method')
                            ->info('The payment method of the core under which connected terminals take payments.')
                            ->defaultValue('card_present')
                            ->validate()
                                ->ifTrue(static fn (mixed $method): bool => !\is_string($method) || 1 !== preg_match('/^[a-z][a-z0-9_]{0,31}$/D', $method) || 'cash' === $method)
                                ->thenInvalid('The terminals\' method is a payment method of up to 32 lowercase letters, digits or "_", and not "cash": %s.')
                            ->end()
                        ->end()
                        ->booleanNode('fake')
                            ->info('Registers the fake terminal adapter, "fake", which signs its notifications with the kernel secret: for tests and development only.')
                            ->defaultFalse()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('cashier_pin')
                    ->info('The cashier switch by PIN on a shared register, off by default.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('limiter')
                            ->info('The service id of the rate limiter factory that bounds PIN attempts: "limiter.cashier_pin" for framework.rate_limiter.cashier_pin.')
                            ->defaultNull()
                        ->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $pin): bool => true === $pin['enabled'] && !\is_string($pin['limiter']))
                        ->thenInvalid('The cashier switch by PIN names the rate limiter that bounds its attempts: set cashier_pin.limiter.')
                    ->end()
                ->end()
            ->end();
    }

    /**
     * The check runs while extensions prepend, the one moment the container knows every registered extension;
     * each extension then loads in a container of its own.
     */
    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('phil_commerce')) {
            throw new LogicException('PhilCommercePosBundle is a module of the core of commerce: register PhilCommerceBundle, from phil/commerce-bundle, too.');
        }

        // The Stimulus controllers under the name Symfony UX gives the controllers of a package, for AssetMapper to serve.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [self::CONTROLLERS_DIR => '@phil/commerce-pos-bundle']]);
        }

        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $container->extension('doctrine', ['orm' => ['mappings' => ['PhilCommercePosBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => __DIR__.'/Domain/Model',
            'prefix' => 'Phil\CommercePosBundle\Domain\Model',
        ]]]]);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Every service reads the module's repositories and the core's orders and payments, Doctrine's all. An
        // application storing them otherwise declares the services itself.
        if (!$this->hasBundle($builder, 'DoctrineBundle')) {
            return;
        }

        $builder->registerForAutoconfiguration(PaymentTerminal::class)->addTag(self::PAYMENT_TERMINAL_TAG);
        $builder->registerForAutoconfiguration(TillDrawForm::class)->addTag(self::TILL_DRAW_FORM_TAG);

        /** @var array{method: string, fake: bool} $terminals */
        $terminals = $config['terminals'];
        /** @var array{enabled: bool, limiter: string|null} $pin */
        $pin = $config['cashier_pin'];
        $services = $container->services();
        $services->set(RegisterGateway::class)->args([$config['recorded_methods'] ?? []])->tag('phil_commerce.payment_gateway');
        $services->set(RegisterCheckout::class)->autowire()->public()->arg('$tipLabel', $config['tip_label'] ?? null);
        $services->set(Layaways::class)->autowire()->public()->arg('$deposit', $config['layaway_deposit'] ?? null);
        $services->set(CashDifferences::class)->autowire()->public()->arg('$threshold', $config['cash_difference_threshold'] ?? null);
        $services->set(TerminalAdapters::class)->args([tagged_iterator(self::PAYMENT_TERMINAL_TAG)]);
        $services->set(TerminalGateway::class)->autowire()->arg('$method', $terminals['method'])->tag('phil_commerce.payment_gateway');
        $services->set(TillDraws::class)->autowire()->public()->arg('$forms', tagged_iterator(self::TILL_DRAW_FORM_TAG));

        foreach ([Registers::class, CashSessions::class, RegisterPayments::class, OfflineSales::class, TerminalPayments::class, SalePresets::class, CustomerDisplay::class] as $service) {
            $services->set($service)->autowire()->public();
        }

        if ($terminals['fake']) {
            $services->set(FakePaymentTerminal::class)->args(['%kernel.secret%'])->public()
                ->tag(self::PAYMENT_TERMINAL_TAG)
                ->tag('kernel.reset', ['method' => 'reset']);
        }

        $this->loadCashierSwitch($pin['enabled'], $pin['limiter'], $container);

        foreach ([OfflineSaleController::class, TerminalNotificationController::class] as $controller) {
            $services->set($controller)->autowire()->public()->tag('controller.service_arguments');
        }

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrinePosRepository::class] as $adapter) {
            $services->set($adapter)->args([service('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }
    }

    /**
     * The switch is wired either way, and stays off — refusing every PIN — until the application enables it with a
     * rate limiter; it then hashes PINs with PHP's native algorithm, as Symfony's `auto` hasher does.
     *
     * @param string|null $limiter the service id of the rate limiter factory, which the configuration requires once enabled
     */
    private function loadCashierSwitch(bool $enabled, ?string $limiter, ContainerConfigurator $container): void
    {
        $switch = $container->services()->set(CashierSwitch::class)->autowire()->public();

        if (!$enabled || null === $limiter) {
            $switch->arg('$hasher', null)->arg('$limiter', null);

            return;
        }

        if (!class_exists(NativePasswordHasher::class) || !interface_exists(RateLimiterFactoryInterface::class)) {
            throw new LogicException('The cashier switch by PIN hashes with symfony/password-hasher and bounds its attempts with symfony/rate-limiter: require both.');
        }

        $container->services()->set('phil_commerce_pos.cashier_pin_hasher', NativePasswordHasher::class);
        $switch->arg('$hasher', service('phil_commerce_pos.cashier_pin_hasher'))->arg('$limiter', service($limiter));
    }

    private function hasBundle(ContainerBuilder $builder, string $bundle): bool
    {
        // Through the bag rather than `getParameter()`, which static analysis types from the container of
        // whatever application analyses the bundle, not from the kernel that loads it.
        $bundles = $builder->getParameterBag()->all()['kernel.bundles'] ?? null;

        return \is_array($bundles) && isset($bundles[$bundle]);
    }
}
