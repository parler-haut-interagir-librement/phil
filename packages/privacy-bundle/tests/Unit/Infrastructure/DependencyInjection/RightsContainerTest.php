<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\ConfiguredRetentionSchedule;
use Phil\PrivacyBundle\Application\Consent\ConsentRecorder;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Contract\ExportArchive;
use Phil\PrivacyBundle\Application\Handler\AdvanceAccountDeletionHandler;
use Phil\PrivacyBundle\Application\Handler\ProcessDueAccountDeletionsHandler;
use Phil\PrivacyBundle\Application\Handler\RunRetentionHandler;
use Phil\PrivacyBundle\Application\Message\AdvanceAccountDeletion;
use Phil\PrivacyBundle\Application\PersonalDataDownload;
use Phil\PrivacyBundle\Application\PrivacyRights;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\AccountDeletion\DeletionPlace;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Consent\ConsentToken;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessDeletionsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\RunRetentionCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsServices;
use Phil\PrivacyBundle\Infrastructure\Doctrine\PrivacySchemaListener;
use Phil\PrivacyBundle\Infrastructure\Filesystem\EncryptedExportArchive;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentController;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentCookie;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentVaryListener;
use Phil\PrivacyBundle\Infrastructure\Http\PreferencesController;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Phil\PrivacyBundle\Infrastructure\Log\LoggedConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Scheduler\PrivacySchedule;
use Phil\PrivacyBundle\Infrastructure\Security\SecurityCurrentDataSubject;
use Phil\PrivacyBundle\Infrastructure\Twig\ConsentExtension;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Workflow\StateMachine;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The services of the rights, compiled into a container and built one by one: an argument left out of a definition
 * fails here, and not in the first request of an application.
 */
#[CoversClass(RightsServices::class)]
final class RightsContainerTest extends TestCase
{
    public function testEveryServiceIsBuiltWithTheArgumentsItNeeds(): void
    {
        $container = $this->compiled();

        foreach ([
            DbalAccountDeletions::class => DbalAccountDeletions::class,
            DbalConsentProofs::class => DbalConsentProofs::class,
            PrivacySchemaListener::class => PrivacySchemaListener::class,
            PrivacyRights::class => PrivacyRights::class,
            AccountDeletionStore::class => DbalAccountDeletions::class,
            ExportArchive::class => EncryptedExportArchive::class,
            DeletionWorkflow::SERVICE => StateMachine::class,
            AccountDeletions::class => AccountDeletions::class,
            PersonalDataDownload::class => PersonalDataDownload::class,
            AdvanceAccountDeletionHandler::class => AdvanceAccountDeletionHandler::class,
            ProcessDueAccountDeletionsHandler::class => ProcessDueAccountDeletionsHandler::class,
            ProcessDeletionsCommand::class => ProcessDeletionsCommand::class,
            ConsentPolicy::class => ConsentPolicy::class,
            LoggedConsentProofs::class => LoggedConsentProofs::class,
            ConsentProofs::class => DbalConsentProofs::class,
            ConsentRecorder::class => ConsentRecorder::class,
            ConsentToken::class => ConsentToken::class,
            ConsentCookie::class => ConsentCookie::class,
            ConsentContext::class => ConsentContext::class,
            ConsentVaryListener::class => ConsentVaryListener::class,
            PrivacyPages::class => PrivacyPages::class,
            ConsentController::class => ConsentController::class,
            ConsentExtension::class => ConsentExtension::class,
            SecurityCurrentDataSubject::class => SecurityCurrentDataSubject::class,
            CurrentDataSubject::class => SecurityCurrentDataSubject::class,
            PreferencesController::class => PreferencesController::class,
            ConfiguredRetentionSchedule::class => ConfiguredRetentionSchedule::class,
            RetentionRun::class => RetentionRun::class,
            RetentionJob::class => RetentionJob::class,
            RunRetentionHandler::class => RunRetentionHandler::class,
            RunRetentionCommand::class => RunRetentionCommand::class,
            PrivacySchedule::class => PrivacySchedule::class,
        ] as $id => $class) {
            self::assertInstanceOf($class, $container->get($id), $id);
        }
    }

    public function testTheHandlersNameTheMessagesTheyHandle(): void
    {
        $container = $this->loaded();

        self::assertSame([['handles' => AdvanceAccountDeletion::class]], $container->getDefinition(AdvanceAccountDeletionHandler::class)->getTag('messenger.message_handler'));
        self::assertSame([['event' => 'postGenerateSchema']], $container->getDefinition(PrivacySchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testTheConsentCookieIsSignedWithTheApplicationSecret(): void
    {
        self::assertSame(['%kernel.secret%'], $this->loaded()->getDefinition(ConsentToken::class)->getArguments());
    }

    public function testTheWorkflowDispatchesItsEventsThroughTheApplication(): void
    {
        $container = $this->compiled();
        $events = [];
        $dispatcher = $container->get('event_dispatcher');
        self::assertInstanceOf(EventDispatcher::class, $dispatcher);
        $dispatcher->addListener('workflow.phil_privacy_account_deletion.guard', static function () use (&$events): void {
            $events[] = 'guard';
        });
        $workflow = $container->get(DeletionWorkflow::SERVICE);
        self::assertInstanceOf(StateMachine::class, $workflow);

        $workflow->can(new AccountDeletion(str_repeat('a', 32), DataSubject::fromString('m1'), DeletionPlace::Requested, new DateTimeImmutable(), new DateTimeImmutable()), DeletionWorkflow::CANCEL);

        self::assertSame(['guard'], $events);
    }

    private function compiled(): ContainerBuilder
    {
        $container = $this->loaded();
        $container->setParameter('kernel.secret', 'secret');
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.environment', 'test');

        $tokens = self::createStub(CsrfTokenManagerInterface::class);
        $externals = [
            'database_connection' => DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            'lock.factory' => new LockFactory(new InMemoryStore()),
            'clock' => new MockClock(),
            'logger' => new NullLogger(),
            'event_dispatcher' => new EventDispatcher(),
            'messenger.default_bus' => new MessageBus(),
            'request_stack' => new RequestStack(),
            'twig' => new Environment(new ArrayLoader()),
            'security.csrf.token_manager' => $tokens,
            'router' => self::createStub(UrlGeneratorInterface::class),
            'security.token_storage' => new TokenStorage(),
        ];

        foreach (array_keys($externals) as $id) {
            $container->register($id)->setSynthetic(true)->setPublic(true);
        }

        foreach ($container->getDefinitions() as $definition) {
            $definition->setPublic(true);
        }

        foreach ($container->getAliases() as $alias) {
            $alias->setPublic(true);
        }

        $container->compile(true);

        foreach ($externals as $id => $service) {
            $container->set($id, $service);
        }

        return $container;
    }

    private function loaded(): ContainerBuilder
    {
        $bundle = new PhilPrivacyBundle('php://memory');
        $container = new ContainerBuilder();
        // An application with accounts, as most are: the account deletions are on by default.
        $container->setParameter('kernel.bundles', [RightsServices::SECURITY_BUNDLE => SecurityBundle::class]);

        $instanceof = [];
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $config = new Processor()->processConfiguration($configuration, [[
            'consent' => ['categories' => ['analytics' => []]],
            'retention' => ['rules' => ['R-1' => ['store' => PrivacyTables::PROOFS, 'period' => 'P1Y']]],
        ]]);
        $bundle->loadExtension($config, new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        return $container;
    }
}
