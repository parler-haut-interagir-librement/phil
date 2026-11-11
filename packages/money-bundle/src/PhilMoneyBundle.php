<?php

declare(strict_types=1);

namespace Phil\MoneyBundle;

use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use Phil\MoneyBundle\Infrastructure\Doctrine\CurrencyType;
use Phil\MoneyBundle\Infrastructure\Form\MoneyType;
use Phil\MoneyBundle\Infrastructure\Twig\MoneyExtension;
use Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrencyValidator;
use Phil\MoneyBundle\Infrastructure\Validator\MoneyRangeValidator;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Attribute\AsTwigFilter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * `phil/money` in a Symfony application: the accepted currencies, the `MoneyType` form type, the
 * `AcceptedCurrency` and `MoneyRange` constraints and — when their packages are installed — the `format_money`
 * Twig filter, the `phil_currency` column type and the `Money` embeddable.
 *
 * ```yaml
 * phil_money:
 *     currencies: [CAD, USD]
 *     default_currency: CAD
 * ```
 */
final class PhilMoneyBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_money';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $unknown = static fn (string $code): bool => !AcceptedCurrencies::isKnown($code);

        $definition->rootNode()
            ->validate()
                ->ifTrue($this->defaultOutsideTheList(...))
                ->thenInvalid('The default currency is not one of the accepted currencies: %s.')
            ->end()
            ->children()
                ->arrayNode('currencies')
                    ->info('The ISO 4217 codes the application accepts, in the order a choice offers them; empty accepts every known currency.')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue($unknown)
                            ->thenInvalid('%s is not a known ISO 4217 currency code.')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_currency')
                    ->info('The currency an empty amount starts in; the first accepted currency by default.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue($unknown)
                        ->thenInvalid('%s is not a known ISO 4217 currency code.')
                    ->end()
                ->end()
                ->booleanNode('doctrine_mapping')
                    ->info('Maps Phil\Money\Money as an embeddable on the ORM.')
                    ->defaultTrue()
                ->end()
            ->end();
    }

    /**
     * @param array{currencies: list<string>, default_currency: ?string} $config
     */
    private function defaultOutsideTheList(array $config): bool
    {
        return null !== $config['default_currency'] && [] !== $config['currencies'] && !\in_array($config['default_currency'], $config['currencies'], true);
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('doctrine') || !class_exists(Type::class)) {
            return;
        }

        $container->extension('doctrine', ['dbal' => ['types' => [CurrencyType::NAME => CurrencyType::class]]]);

        // The bundle's own tree, read before its extension loads: the mapping must reach DoctrineBundle first.
        /** @var array{doctrine_mapping: bool} $config */
        $config = new Processor()->processConfiguration(new Configuration($this, $builder, $this->extensionAlias), $builder->getExtensionConfig($this->extensionAlias));
        $orm = array_any($builder->getExtensionConfig('doctrine'), static fn (mixed $doctrine): bool => \is_array($doctrine) && isset($doctrine['orm']));

        if ($config['doctrine_mapping'] && $orm && interface_exists(EntityManagerInterface::class)) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilMoney' => [
                'type' => 'xml',
                'is_bundle' => false,
                'dir' => $this->getPath().'/config/doctrine',
                'prefix' => 'Phil\Money',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{currencies: list<string>, default_currency: ?string, doctrine_mapping: bool} $config */
        $services = $container->services();

        $services->set(AcceptedCurrencies::class)
            ->factory([AcceptedCurrencies::class, 'of'])
            ->args([$config['currencies'], $config['default_currency']]);
        $services->set(MoneyType::class)
            ->args([service(AcceptedCurrencies::class)])
            ->tag('form.type');
        $services->set(AcceptedCurrencyValidator::class)
            ->args([service(AcceptedCurrencies::class)])
            ->tag('validator.constraint_validator');
        $services->set(MoneyRangeValidator::class)
            ->tag('validator.constraint_validator');

        if (class_exists(AsTwigFilter::class)) {
            $services->set(MoneyExtension::class)
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
