<?php

declare(strict_types=1);

namespace Phil\LibraryBundle;

use Override;
use Phil\LibraryBundle\Application\Catalogue;
use Phil\LibraryBundle\Application\Contract\LoanTransaction;
use Phil\LibraryBundle\Application\Contract\MemberResolver;
use Phil\LibraryBundle\Application\Contract\ReminderSender;
use Phil\LibraryBundle\Application\Contract\StockLink;
use Phil\LibraryBundle\Application\Contract\TitleSearch;
use Phil\LibraryBundle\Application\LoanPolicy;
use Phil\LibraryBundle\Application\Loans;
use Phil\LibraryBundle\Application\Reminders;
use Phil\LibraryBundle\Infrastructure\Console\ForgetHistoryCommand;
use Phil\LibraryBundle\Infrastructure\Console\SendRemindersCommand;
use Phil\LibraryBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\LibraryBundle\Infrastructure\DependencyInjection\LibraryPass;
use Phil\LibraryBundle\Infrastructure\Doctrine\DbalTitleSearch;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineCopyRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineLoanRepository;
use Phil\LibraryBundle\Infrastructure\Doctrine\DoctrineTitleRepository;
use Phil\LibraryBundle\Infrastructure\Form\CopyType;
use Phil\LibraryBundle\Infrastructure\Form\LendType;
use Phil\LibraryBundle\Infrastructure\Form\ReturnType;
use Phil\LibraryBundle\Infrastructure\Form\SearchType;
use Phil\LibraryBundle\Infrastructure\Form\TitleType;
use Phil\LibraryBundle\Infrastructure\Http\AdminController;
use Phil\LibraryBundle\Infrastructure\Http\CatalogueController;
use Phil\LibraryBundle\Infrastructure\Http\DeskController;
use Phil\LibraryBundle\Infrastructure\Http\MemberLoansController;
use Phil\LibraryBundle\Infrastructure\Http\Pages;
use Phil\LibraryBundle\Infrastructure\Messenger\MessengerReminderSender;
use Phil\LibraryBundle\Infrastructure\Privacy\LoanHistoryPrivacy;
use Phil\LibraryBundle\Infrastructure\Stockroom\NoStockLink;
use Phil\LibraryBundle\Infrastructure\Twig\KitComponents;
use Phil\LibraryBundle\Infrastructure\Twig\LibraryExtension;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A game or book library on Doctrine: the catalogue of titles and their records, searched by a list filtered without
 * JavaScript; the copies, lent at the desk or reserved ahead on `phil/reservation`, renewed, returned with their
 * condition, lost; reminders before the due date and notices of a loan overdue through Messenger; the member's loans,
 * the administration pages, the history of loans in the privacy requests of `phil/privacy-bundle`, and the link between
 * a copy and an item of `phil/stockroom`.
 *
 * ```yaml
 * phil_library:
 *     loans: { days: 21, max_renewals: 2, max_open: 10 }
 *     timezone: America/Toronto
 * ```
 */
final class PhilLibraryBundle extends AbstractBundle
{
    /** The tags of `phil/privacy-bundle`'s exporters and erasers. */
    public const string EXPORTER_TAG = 'phil_privacy.exporter';

    public const string ERASER_TAG = 'phil_privacy.eraser';

    /** The recipes of the PHIL UX kit the templates draw, as `ux:install` names them. */
    public const array RECIPES = ['alert', 'badge', 'button', 'card', 'table'];

    #[Override]
    protected string $extensionAlias = 'phil_library';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new LibraryPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('timezone')->info("The library's clock: a loan is due at the end of its last day on it, and the pages show their moments on it.")->defaultValue('UTC')->cannotBeEmpty()->end()
                ->arrayNode('loans')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('days')->info('How many days a loan lasts.')->defaultValue(21)->min(1)->max(365)->end()
                        ->integerNode('max_renewals')->info('How many times a loan is renewed.')->defaultValue(2)->min(0)->max(99)->end()
                        ->integerNode('max_open')->info('How many loans, reserved or out, a member holds at once.')->defaultValue(10)->min(1)->max(999)->end()
                        ->booleanNode('block_when_overdue')->info('Whether a member with a copy overdue borrows no more until it comes back.')->defaultTrue()->end()
                        ->integerNode('reservation_days')->info('How far ahead, in days, a copy is reserved.')->defaultValue(90)->min(1)->max(730)->end()
                    ->end()
                ->end()
                ->arrayNode('reminders')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('before_due')->info('The minutes before the due date a reminder comes.')->integerPrototype()->min(1)->max(525_600)->end()->defaultValue([1440])->end()
                        ->integerNode('overdue_every_days')->info('The days between two notices of a loan overdue.')->defaultValue(7)->min(1)->max(365)->end()
                        ->integerNode('overdue_max')->info('The most notices a loan overdue gets; none with 0.')->defaultValue(3)->min(0)->max(99)->end()
                    ->end()
                ->end()
                ->integerNode('history_days')->info('The days a closed loan keeps its member before phil:library:forget-history forgets it; kept until an erasure when null.')->defaultNull()->min(0)->max(36_500)->end()
                ->integerNode('batch')->info('The most loans each run of phil:library:send-reminders reads.')->defaultValue(500)->min(1)->max(10_000)->end()
                ->integerNode('per_page')->info('The titles a page of the catalogue lists.')->defaultValue(25)->min(1)->max(100)->end()
                ->scalarNode('member_resolver')->info("The service of the application's MemberResolver, for the member's page of loans; no page without it.")->defaultNull()->end()
                ->scalarNode('stockroom')->info("The service of phil/stockroom's Stockroom the loans of linked copies move items in; the one of phil/stockroom-bundle when it is installed and none is named, else none moves.")->defaultNull()->end()
                ->arrayNode('roles')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('desk')->info('The role of the desk.')->defaultValue('ROLE_LIBRARIAN')->cannotBeEmpty()->end()
                        ->scalarNode('admin')->info('The role of the administration pages.')->defaultValue('ROLE_LIBRARIAN')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->arrayNode('pages')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('layout')->info('The layout the pages extend, which defines the blocks "title" and "content".')->defaultValue('@PhilLibrary/layout.html.twig')->cannotBeEmpty()->end()
                        ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null.')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilLibraryBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\LibraryBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{timezone: string, loans: array{days: int, max_renewals: int, max_open: int, block_when_overdue: bool, reservation_days: int}, reminders: array{before_due: list<int>, overdue_every_days: int, overdue_max: int}, history_days: ?int, batch: int, per_page: int, member_resolver: ?string, stockroom: ?string, roles: array{desk: string, admin: string}, pages: array{layout: string, kit_subdirectory: ?string}} $config */
        $services = $container->services();
        $builder->setParameter(LibraryPass::MEMBER_RESOLVER, $config['member_resolver']);
        $builder->setParameter(LibraryPass::STOCKROOM, $config['stockroom']);

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineTitleRepository::class, DoctrineCopyRepository::class, DoctrineLoanRepository::class] as $adapter) {
            $services->set($adapter)->args([new Reference('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }

        $services->set(DbalTitleSearch::class)->args([new Reference('doctrine.dbal.default_connection'), new Reference('clock')]);
        $services->alias(TitleSearch::class, DbalTitleSearch::class)->public();
        $services->set(NoStockLink::class);
        $services->alias(StockLink::class, NoStockLink::class);

        $loans = $config['loans'];
        $services->set(LoanPolicy::class)->args([$loans['days'], $loans['max_renewals'], $loans['max_open'], $loans['block_when_overdue'], $loans['reservation_days'], $config['history_days'], $config['timezone']]);
        $services->set(Catalogue::class)->args([new Reference(DoctrineTitleRepository::class), new Reference(DoctrineCopyRepository::class), new Reference('clock')])->public();
        $services->set(Loans::class)
            ->args([
                new Reference(DoctrineCopyRepository::class),
                new Reference(DoctrineLoanRepository::class),
                new Reference(StockLink::class),
                new Reference(LoanPolicy::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference(LoanTransaction::class, ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->public();

        if (interface_exists(MessageBusInterface::class)) {
            $services->set(MessengerReminderSender::class)->args([new Reference(MessageBusInterface::class)]);
            $services->alias(ReminderSender::class, MessengerReminderSender::class);
        }

        $reminders = $config['reminders'];
        $services->set(Reminders::class)
            ->args([new Reference(DoctrineLoanRepository::class), new Reference(ReminderSender::class), new Reference('clock'), $reminders['before_due'], $reminders['overdue_every_days'], $reminders['overdue_max']])
            ->public();

        if (class_exists(Command::class)) {
            $services->set(SendRemindersCommand::class)->args([new Reference(Reminders::class), $config['batch']])->tag('console.command');
            $services->set(ForgetHistoryCommand::class)->args([new Reference(Loans::class)])->tag('console.command');
        }

        if (interface_exists(PersonalDataEraser::class)) {
            $services->set(LoanHistoryPrivacy::class)->args([new Reference(DoctrineLoanRepository::class)])->tag(self::EXPORTER_TAG)->tag(self::ERASER_TAG);
        }

        foreach ([SearchType::class, LendType::class, ReturnType::class, TitleType::class, CopyType::class] as $type) {
            $services->set($type)->tag('form.type');
        }

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $services->set(KitComponents::class)->args([$config['pages']['kit_subdirectory']]);
        $services->set(LibraryExtension::class)->args([new Reference(KitComponents::class), $config['timezone']])->tag('twig.attribute_extension')->tag('twig.runtime');
        $services->set(Pages::class)->args([new Reference('twig'), $config['pages']['layout'], new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $services->set(CatalogueController::class)
            ->args([new Reference(Pages::class), new Reference('form.factory'), new Reference(TitleSearch::class), new Reference(Catalogue::class), new Reference(Loans::class), new Reference('clock'), $config['per_page']])
            ->tag('controller.service_arguments')
            ->public();
        $services->set(DeskController::class)
            ->args([new Reference(Pages::class), new Reference('form.factory'), new Reference(Loans::class), new Reference('translator'), $config['roles']['desk']])
            ->tag('controller.service_arguments')
            ->public();
        $services->set(AdminController::class)
            ->args([new Reference(Pages::class), new Reference('form.factory'), new Reference(Catalogue::class), new Reference(DoctrineLoanRepository::class), new Reference('router'), new Reference('clock'), $config['roles']['admin']])
            ->tag('controller.service_arguments')
            ->public();
        $services->set(MemberLoansController::class)
            ->args([new Reference(Pages::class), new Reference(MemberResolver::class, ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(Loans::class), new Reference('clock')])
            ->tag('controller.service_arguments')
            ->public();
    }
}
