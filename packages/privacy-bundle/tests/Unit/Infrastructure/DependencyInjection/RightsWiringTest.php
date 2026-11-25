<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\DependencyInjection;

use Override;
use Phil\PrivacyBundle\Application\AccountDeletion\AccountDeletions;
use Phil\PrivacyBundle\Application\AccountDeletion\DeletionWorkflow;
use Phil\PrivacyBundle\Application\ConfiguredRetentionSchedule;
use Phil\PrivacyBundle\Application\Consent\ConsentRecorder;
use Phil\PrivacyBundle\Application\Contract\AccountDeletionStore;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Application\Contract\CurrentDataSubject;
use Phil\PrivacyBundle\Application\Contract\ExportArchive;
use Phil\PrivacyBundle\Application\Contract\IncidentRegister;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Application\Handler\AdvanceAccountDeletionHandler;
use Phil\PrivacyBundle\Application\Handler\ProcessDueAccountDeletionsHandler;
use Phil\PrivacyBundle\Application\Handler\RunRetentionHandler;
use Phil\PrivacyBundle\Application\Incident\IncidentJournalRecorder;
use Phil\PrivacyBundle\Application\Incident\IncidentNotices;
use Phil\PrivacyBundle\Application\Message\ProcessDueAccountDeletions;
use Phil\PrivacyBundle\Application\Message\RunRetention;
use Phil\PrivacyBundle\Application\PersonalDataDownload;
use Phil\PrivacyBundle\Application\PrivacyRights;
use Phil\PrivacyBundle\Application\Register\ProcessingRegister;
use Phil\PrivacyBundle\Application\RetentionJob;
use Phil\PrivacyBundle\Application\RetentionRun;
use Phil\PrivacyBundle\Domain\AccountDeletion\AccountDeletion;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Infrastructure\Console\DisabledRightsWarning;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentJournalCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentNoticeCommand;
use Phil\PrivacyBundle\Infrastructure\Console\IncidentsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessDeletionsCommand;
use Phil\PrivacyBundle\Infrastructure\Console\RunRetentionCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAccountDeletions;
use Phil\PrivacyBundle\Infrastructure\Database\DbalConsentProofs;
use Phil\PrivacyBundle\Infrastructure\Database\DbalIncidentRegister;
use Phil\PrivacyBundle\Infrastructure\Database\PrivacyTables;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\AnonymizationServices;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\RightsConfiguration;
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
use Phil\PrivacyBundle\Infrastructure\Routing\MountingRouteLoader;
use Phil\PrivacyBundle\Infrastructure\Scheduler\PrivacySchedule;
use Phil\PrivacyBundle\Infrastructure\Security\SecurityCurrentDataSubject;
use Phil\PrivacyBundle\Infrastructure\Twig\ConsentExtension;
use Phil\PrivacyBundle\Infrastructure\Twig\TwigIncidentNotice;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilPrivacyBundle::class)]
#[CoversClass(RightsServices::class)]
#[CoversClass(RightsConfiguration::class)]
#[CoversClass(PrivacyServicesPass::class)]
#[CoversClass(AnonymizationServices::class)]
final class RightsWiringTest extends TestCase
{
    private string $warnings;

    #[Override]
    protected function setUp(): void
    {
        $this->warnings = sys_get_temp_dir().'/phil-privacy-warnings-'.bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->warnings)) {
            unlink($this->warnings);
        }
    }

    public function testEveryRightIsOnByDefaultAndNothingIsWarned(): void
    {
        $container = $this->load([]);

        self::assertSame([true, true], $container->getDefinition(PrivacyRights::class)->getArguments());
        self::assertTrue($container->getDefinition(PrivacyRights::class)->isPublic());
        self::assertFalse($container->hasDefinition(DisabledRightsWarning::class));
        self::assertFileDoesNotExist($this->warnings);
    }

    public function testASentenceThatIsNotTheOneIsRefused(): void
    {
        foreach (['export' => PrivacyRights::DISABLE_EXPORT_SENTENCE, 'erasure' => PrivacyRights::DISABLE_ERASURE_SENTENCE] as $right => $sentence) {
            foreach ([true, 'yes', $sentence.'.'] as $wrong) {
                try {
                    $this->load(['rights' => [$right => ['disable' => $wrong]]]);
                    self::fail('A short switch must not turn a right off.');
                } catch (InvalidConfigurationException $invalidConfigurationException) {
                    self::assertSame(\sprintf('phil_privacy.rights.%s.disable must be exactly this sentence: "%s".', $right, $sentence), $invalidConfigurationException->getMessage());
                }
            }
        }
    }

    public function testTurningARightOffTakesTheSentenceAndWarnsAtTheBuildAndInTheConsole(): void
    {
        $container = $this->load(['rights' => ['export' => ['disable' => PrivacyRights::DISABLE_EXPORT_SENTENCE]]]);

        self::assertSame([false, true], $container->getDefinition(PrivacyRights::class)->getArguments());
        $listener = $container->getDefinition(DisabledRightsWarning::class);
        self::assertSame([['event' => 'console.command']], $listener->getTag('kernel.event_listener'));
        self::assertEquals([new Reference(PrivacyRights::class)], $listener->getArguments());
        $warning = (string) file_get_contents($this->warnings);
        self::assertStringContainsString('!! DANGER: phil_privacy.rights.export is DISABLED', $warning);
        self::assertStringContainsString(str_repeat('!', 100), $warning);
    }

    public function testBothRightsOffAreBothWarnedOf(): void
    {
        $container = $this->load(['rights' => ['export' => ['disable' => PrivacyRights::DISABLE_EXPORT_SENTENCE], 'erasure' => ['disable' => PrivacyRights::DISABLE_ERASURE_SENTENCE]]]);

        self::assertSame([false, false], $container->getDefinition(PrivacyRights::class)->getArguments());
        $warning = (string) file_get_contents($this->warnings);
        self::assertStringContainsString('rights.export is DISABLED', $warning);
        self::assertStringContainsString('!! DANGER: phil_privacy.rights.erasure is DISABLED', $warning);
        [$export, $erasure] = new PrivacyRights(false, false)->warnings();
        $border = str_repeat('!', 100);
        self::assertSame("\n".$border."\n!! DANGER: ".$export."\n!! DANGER: ".$erasure."\n".$border."\n\n", $warning);
    }

    public function testACategoryNamedByANumberIsRefusedAtTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.consent: "7" is not a consent category');

        $this->load(['consent' => ['categories' => ['7' => []]]]);
    }

    public function testNamesAndLocalesAreKeptAsWritten(): void
    {
        $container = $this->load(['consent' => ['categories' => ['third-party' => ['labels' => ['fr-CA' => 'Tiers'], 'descriptions' => ['fr-CA' => 'Des tiers']]]]]);

        $categories = $container->getDefinition(ConsentPolicy::class)->getArgument(1);
        self::assertIsArray($categories);
        self::assertInstanceOf(Definition::class, $categories[0]);
        self::assertSame(['third-party', false, ['fr-CA' => 'Tiers'], ['fr-CA' => 'Des tiers'], []], $categories[0]->getArguments());
    }

    public function testEveryValueOfARetentionLineCanBeWrittenOut(): void
    {
        $line = [
            'store' => 'trash',
            'categories' => ['email'],
            'period' => 'P30D',
            'mechanism' => 'job',
            'erasure' => 'deleted',
            'access' => 'returned',
            'purpose' => 'Undo a deletion',
            'recipients' => ['Support'],
            'processors' => [['name' => 'Host', 'location' => 'Canada']],
            'location' => 'Canada',
        ];

        $container = $this->load(['retention' => ['rules' => ['R-1' => $line]]]);

        self::assertSame(['R-1' => $line], $container->getDefinition(ConfiguredRetentionSchedule::class)->getArgument(0));
    }

    /**
     * @return iterable<string, array{array<string, string>|string|null}>
     */
    public static function withoutAccounts(): iterable
    {
        yield 'no kernel.bundles' => [null];
        yield 'other bundles' => [['FrameworkBundle' => FrameworkBundle::class]];
        yield 'not a list' => [RightsServices::SECURITY_BUNDLE];
    }

    /**
     * @param array<string, string>|string|null $bundles
     */
    #[DataProvider('withoutAccounts')]
    public function testWithoutAccountsThereIsNoAccountDeletion(array|string|null $bundles): void
    {
        $container = $this->load([], $bundles);

        foreach ([DbalAccountDeletions::class, AccountDeletions::class, DeletionWorkflow::SERVICE, AdvanceAccountDeletionHandler::class, ProcessDueAccountDeletionsHandler::class, ProcessDeletionsCommand::class, EncryptedExportArchive::class, PrivacySchedule::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id);
        }

        self::assertFalse($container->hasAlias(AccountDeletionStore::class));
        self::assertTrue($container->hasDefinition(PersonalDataDownload::class));
        self::assertFalse($container->getParameter(PrivacyServicesPass::DELETIONS_REQUIRED));
    }

    public function testTheAccountDeletionsCanBeTurnedOnWithoutAccounts(): void
    {
        $container = $this->load(['account_deletion' => ['enabled' => true]], null);

        self::assertTrue($container->hasDefinition(AccountDeletions::class));
        self::assertTrue($container->hasDefinition(DbalAccountDeletions::class));
        self::assertTrue($container->getParameter(PrivacyServicesPass::DELETIONS_REQUIRED));
        self::assertSame(['15 minutes', null], $container->getDefinition(PrivacySchedule::class)->getArguments());
    }

    public function testTheAccountDeletionsCanBeTurnedOffWithAccounts(): void
    {
        $container = $this->load(['account_deletion' => ['enabled' => false], 'retention' => ['rules' => ['R-1' => ['store' => 'trash', 'period' => 'P30D']]]]);

        self::assertFalse($container->hasDefinition(AccountDeletions::class));
        self::assertFalse($container->getParameter(PrivacyServicesPass::DELETIONS_REQUIRED));
        self::assertSame([null, '1 day'], $container->getDefinition(PrivacySchedule::class)->getArguments());
    }

    public function testTheRegisterOfIncidentsIsOnByDefaultAndCanBeTurnedOff(): void
    {
        $ids = [DbalIncidentRegister::class, IncidentJournalRecorder::class, IncidentNotices::class, TwigIncidentNotice::class, IncidentCommand::class, IncidentsCommand::class, IncidentJournalCommand::class, IncidentNoticeCommand::class];
        $on = $this->load([]);

        foreach ($ids as $id) {
            self::assertTrue($on->hasDefinition($id), $id);
        }

        self::assertSame(DbalIncidentRegister::class, (string) $on->getAlias(IncidentRegister::class));
        self::assertSame(PrivacyTables::ALL, $on->getParameter(PrivacyServicesPass::TABLES_PARAMETER));

        $off = $this->load(['incidents' => ['enabled' => false]]);

        foreach ($ids as $id) {
            self::assertFalse($off->hasDefinition($id), $id);
        }

        self::assertFalse($off->hasAlias(IncidentRegister::class));
        self::assertSame(['%'.PrivacyServicesPass::TABLES_PARAMETER.'%'], \array_slice($off->getDefinition(PrivacySchemaListener::class)->getArguments(), 1));
    }

    public function testTheAccountDeletionDefaults(): void
    {
        $container = $this->load([]);
        $service = $container->getDefinition(AccountDeletions::class);

        self::assertTrue($service->isPublic());
        self::assertSame('P30D', $service->getArgument(7));
        self::assertSame('P7D', $service->getArgument(8));
        self::assertEquals(new Reference('clock'), $service->getArgument(6));
        self::assertEquals(new Reference('lock.factory'), $service->getArgument(2));
        self::assertSame(DbalAccountDeletions::class, (string) $container->getAlias(AccountDeletionStore::class));
        self::assertSame(EncryptedExportArchive::class, (string) $container->getAlias(ExportArchive::class));
        self::assertSame(['%kernel.project_dir%/var/privacy-exports', '%kernel.secret%'], $container->getDefinition(EncryptedExportArchive::class)->getArguments());
        self::assertEquals(new Reference('database_connection'), $container->getDefinition(DbalAccountDeletions::class)->getArgument(0));
        self::assertTrue($container->getDefinition(DbalAccountDeletions::class)->hasTag(RightsServices::PURGER_TAG));
        self::assertTrue($container->getDefinition(DbalConsentProofs::class)->hasTag(RightsServices::PURGER_TAG));
        self::assertSame([['event' => 'postGenerateSchema']], $container->getDefinition(PrivacySchemaListener::class)->getTag('doctrine.event_listener'));
        $workflow = $container->getDefinition(DeletionWorkflow::SERVICE);
        self::assertTrue($workflow->isPublic());
        self::assertSame([['name' => DeletionWorkflow::NAME, 'supports' => [AccountDeletion::class]]], $workflow->getTag('workflow'));
        self::assertSame([['handles' => ProcessDueAccountDeletions::class]], $container->getDefinition(ProcessDueAccountDeletionsHandler::class)->getTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(ProcessDeletionsCommand::class)->hasTag('console.command'));
    }

    public function testAStoreAnArchiveAndAClockCanBeNamed(): void
    {
        $container = $this->load([
            'account_deletion' => ['store' => 'app.store', 'export_archive' => 'app.archive', 'delay' => 'P7D', 'export_retention' => 'P1D', 'export_directory' => '/x'],
            'pages' => ['clock' => 'app.clock'],
        ]);

        self::assertSame('app.store', (string) $container->getAlias(AccountDeletionStore::class));
        self::assertSame('app.archive', (string) $container->getAlias(ExportArchive::class));
        self::assertFalse($container->hasDefinition(EncryptedExportArchive::class));
        self::assertSame('P7D', $container->getDefinition(AccountDeletions::class)->getArgument(7));
        self::assertSame('P1D', $container->getDefinition(AccountDeletions::class)->getArgument(8));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(AccountDeletions::class)->getArgument(6));
    }

    public function testAnArchiveDirectoryIsGivenToTheArchive(): void
    {
        self::assertSame('/x', $this->load(['account_deletion' => ['export_directory' => '/x']])->getDefinition(EncryptedExportArchive::class)->getArgument(0));
    }

    public function testADurationThatIsNotOneStopsTheBuild(): void
    {
        foreach ([
            'phil_privacy.account_deletion.delay: "PT0S"' => ['account_deletion' => ['delay' => 'PT0S']],
            'phil_privacy.account_deletion.export_retention: "x"' => ['account_deletion' => ['export_retention' => 'x']],
            'phil_privacy.consent.lifetime: "x"' => ['consent' => ['lifetime' => 'x']],
        ] as $message => $config) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringStartsWith($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheConsentIsReadFromTheConfiguration(): void
    {
        $container = $this->load(['consent' => ['version' => '4', 'cookie_name' => 'c', 'lifetime' => 'P1M', 'categories' => [
            'analytics' => ['labels' => ['en' => 'A'], 'descriptions' => ['en' => 'B']],
            'session' => ['necessary' => true],
        ]], 'cookies' => ['inventory' => __DIR__.'/Fixture/cookies.yaml']]);

        $policy = $container->getDefinition(ConsentPolicy::class);
        self::assertTrue($policy->isPublic());
        self::assertSame('4', $policy->getArgument(0));
        $categories = $policy->getArgument(1);
        self::assertIsArray($categories);
        self::assertInstanceOf(Definition::class, $categories[0]);
        self::assertSame(['analytics', false, ['en' => 'A'], ['en' => 'B'], ['_a', '_a_ses.*']], $categories[0]->getArguments());
        self::assertInstanceOf(Definition::class, $categories[1]);
        self::assertSame(['session', true, [], [], []], $categories[1]->getArguments());
        self::assertSame(['c', 'P1M'], \array_slice($container->getDefinition(ConsentCookie::class)->getArguments(), 2));
        self::assertTrue($container->getDefinition(ConsentContext::class)->isPublic());
        self::assertEquals([new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(LoggedConsentProofs::class)->getArguments());
        self::assertSame([['event' => 'kernel.response', 'method' => 'onKernelResponse']], $container->getDefinition(ConsentVaryListener::class)->getTag('kernel.event_listener'));
        self::assertSame(DbalConsentProofs::class, (string) $container->getAlias(ConsentProofs::class));
    }

    public function testTheProofsCanBeNamed(): void
    {
        self::assertSame('app.proofs', (string) $this->load(['consent' => ['proofs' => 'app.proofs']])->getAlias(ConsentProofs::class));
    }

    public function testABadCategoryOrVersionStopsTheBuild(): void
    {
        foreach ([
            'is not a version' => ['consent' => ['version' => 'a b']],
            'is not a consent category' => ['consent' => ['categories' => ['A B' => []]]],
        ] as $message => $config) {
            try {
                $this->load($config);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringStartsWith('phil_privacy.consent: ', $invalidConfigurationException->getMessage());
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testThePagesDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('@PhilPrivacy/layout.html.twig', $container->getDefinition(PrivacyPages::class)->getArgument(3));
        $loader = $container->getDefinition(MountingRouteLoader::class);
        self::assertSame(['routing.loader', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE], $loader->getDecoratedService());
        self::assertSame('/privacy-choices', $loader->getArgument(1));
        self::assertTrue($loader->getArgument(2));
        self::assertSame(SecurityCurrentDataSubject::class, (string) $container->getAlias(CurrentDataSubject::class));
        foreach ([ConsentController::class, PreferencesController::class] as $controller) {
            self::assertTrue($container->getDefinition($controller)->isPublic());
            self::assertTrue($container->getDefinition($controller)->hasTag('controller.service_arguments'));
        }

        self::assertTrue($container->getDefinition(ConsentExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(ConsentExtension::class)->hasTag('twig.runtime'));
    }

    public function testThePagesCanBeMovedOrMountedByHand(): void
    {
        $container = $this->load(['pages' => ['prefix' => '/x', 'layout' => 'l.twig', 'current_subject' => 'app.subject']]);
        self::assertSame('/x', $container->getDefinition(MountingRouteLoader::class)->getArgument(1));
        self::assertSame('l.twig', $container->getDefinition(PrivacyPages::class)->getArgument(3));
        self::assertSame('app.subject', (string) $container->getAlias(CurrentDataSubject::class));

        self::assertFalse($this->load(['pages' => ['auto_mount' => false]])->hasDefinition(MountingRouteLoader::class));
    }

    public function testWithoutASourceTheRetentionIsNotWired(): void
    {
        $container = $this->load([]);

        self::assertFalse($container->hasDefinition(RetentionRun::class));
        self::assertFalse($container->hasDefinition(RetentionJob::class));
        self::assertNull($container->getDefinition(ProcessingRegister::class)->getArgument(0));
        self::assertSame(['15 minutes', null], $container->getDefinition(PrivacySchedule::class)->getArguments());
    }

    public function testANamedSourceIsWired(): void
    {
        $container = $this->load(['retention_schedule_source' => 'app.schedule', 'retention' => ['frequency' => '1 hour'], 'account_deletion' => ['frequency' => '5 minutes']]);

        self::assertEquals(new Reference('app.schedule'), $container->getDefinition(RetentionRun::class)->getArgument(0));
        self::assertEquals(new Reference('app.schedule'), $container->getDefinition(ProcessingRegister::class)->getArgument(0));
        self::assertEquals(new TaggedIteratorArgument(RightsServices::PURGER_TAG), $container->getDefinition(RetentionRun::class)->getArgument(1));
        self::assertTrue($container->getDefinition(RetentionRun::class)->isPublic());
        self::assertTrue($container->getDefinition(RetentionJob::class)->isPublic());
        self::assertSame([['handles' => RunRetention::class]], $container->getDefinition(RunRetentionHandler::class)->getTag('messenger.message_handler'));
        self::assertTrue($container->getDefinition(RunRetentionCommand::class)->hasTag('console.command'));
        self::assertSame(['5 minutes', '1 hour'], $container->getDefinition(PrivacySchedule::class)->getArguments());
        self::assertSame([['name' => PrivacySchedule::NAME]], $container->getDefinition(PrivacySchedule::class)->getTag('scheduler.schedule_provider'));
    }

    public function testRulesFromTheConfigurationAreTheSource(): void
    {
        $rules = ['R-1' => ['store' => 'trash', 'period' => 'P30D']];
        $container = $this->load(['retention' => ['rules' => $rules]]);

        self::assertEquals(new Reference(ConfiguredRetentionSchedule::class), $container->getDefinition(RetentionRun::class)->getArgument(0));
        self::assertSame(['R-1' => ['store' => 'trash', 'period' => 'P30D', 'categories' => [], 'mechanism' => 'job', 'erasure' => 'deleted', 'access' => 'returned', 'purpose' => null, 'recipients' => [], 'processors' => [], 'location' => null]], $container->getDefinition(ConfiguredRetentionSchedule::class)->getArgument(0));
        self::assertSame(['15 minutes', '1 day'], $container->getDefinition(PrivacySchedule::class)->getArguments());
    }

    public function testASourceAndRulesAreOneTooMany(): void
    {
        $this->expectExceptionMessageIsOrContains('a schedule has one authority');

        $this->load(['retention_schedule_source' => 'app.schedule', 'retention' => ['rules' => ['R-1' => ['store' => 'trash', 'period' => 'P30D']]]]);
    }

    public function testABadRuleStopsTheBuild(): void
    {
        $this->expectExceptionMessageIsOrContains('phil_privacy.retention.rules: "x" is not an ISO 8601 duration');

        $this->load(['retention' => ['rules' => ['R-1' => ['store' => 'trash', 'period' => 'x']]]]);
    }

    public function testPurgersAreFoundByAutoconfiguration(): void
    {
        $autoconfigured = $this->load([])->getAutoconfiguredInstanceof();

        self::assertArrayHasKey(RetentionPurger::class, $autoconfigured);
        self::assertTrue($autoconfigured[RetentionPurger::class]->hasTag(RightsServices::PURGER_TAG));
    }

    public function testTheStatelessFormTokenIsDeclared(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'framework';
            }
        });
        $instanceof = [];
        new PhilPrivacyBundle()->prependExtension(new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        self::assertSame([['csrf_protection' => ['stateless_token_ids' => [PrivacyPages::CSRF_TOKEN_ID]]]], $container->getExtensionConfig('framework'));
    }

    public function testNothingIsPrependedWithoutTheFramework(): void
    {
        $container = new ContainerBuilder();
        $instanceof = [];
        new PhilPrivacyBundle()->prependExtension(new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        self::assertSame([], $container->getExtensions());
    }

    public function testAnApplicationWithoutAnythingKeepsOnlyWhatRuns(): void
    {
        $container = $this->load(['retention' => ['rules' => ['R-1' => ['store' => 'trash', 'period' => 'P30D']]]]);
        new PrivacyServicesPass()->process($container);

        foreach ([DbalAccountDeletions::class, DbalConsentProofs::class, PrivacySchemaListener::class, AccountDeletions::class, ProcessDueAccountDeletionsHandler::class, ProcessDeletionsCommand::class, PrivacySchedule::class, PersonalDataDownload::class, RetentionJob::class, RunRetentionHandler::class, RunRetentionCommand::class, ConsentController::class, PreferencesController::class, PrivacyPages::class, MountingRouteLoader::class, ConsentExtension::class, ConsentContext::class, ConsentCookie::class, ConsentVaryListener::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id);
        }

        self::assertFalse($container->hasAlias(AccountDeletionStore::class));
        self::assertSame(LoggedConsentProofs::class, (string) $container->getAlias(ConsentProofs::class));
    }

    public function testANamedStoreAndNamedProofsSurviveWithoutADatabase(): void
    {
        $container = $this->load(['account_deletion' => ['store' => 'app.store'], 'consent' => ['proofs' => 'app.proofs']]);
        $container->register('app.store', stdClass::class);
        $this->services($container, ['clock', 'lock.factory']);
        new PrivacyServicesPass()->process($container);

        self::assertSame('app.store', (string) $container->getAlias(AccountDeletionStore::class));
        self::assertSame('app.proofs', (string) $container->getAlias(ConsentProofs::class));
        self::assertTrue($container->hasDefinition(AccountDeletions::class));
        self::assertFalse($container->hasDefinition(DbalAccountDeletions::class));
    }

    public function testWithADatabaseAndALockTheDeletionsAreKept(): void
    {
        $container = $this->load(['retention' => ['rules' => ['R-1' => ['store' => 'trash', 'period' => 'P30D']]]]);
        $this->services($container, ['database_connection', 'clock', 'lock.factory', 'messenger.default_bus']);
        new PrivacyServicesPass()->process($container);

        foreach ([DbalAccountDeletions::class, PrivacySchemaListener::class, AccountDeletions::class, ProcessDueAccountDeletionsHandler::class, ProcessDeletionsCommand::class, PrivacySchedule::class, PersonalDataDownload::class, RetentionJob::class, RunRetentionCommand::class] as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }

        // Without the pages, no consent is recorded: its store and its table go.
        self::assertFalse($container->hasDefinition(DbalConsentProofs::class));
        self::assertFalse($container->hasAlias(ConsentProofs::class));
        self::assertSame([PrivacyTables::DELETIONS, PrivacyTables::INCIDENTS, PrivacyTables::INCIDENT_REVISIONS, PrivacyTables::INCIDENT_JOURNAL], $container->getParameter(PrivacyServicesPass::TABLES_PARAMETER));
    }

    public function testWithoutABusTheSweepIsLeftToTheCommand(): void
    {
        $container = $this->load([]);
        $this->services($container, ['database_connection', 'clock', 'lock.factory']);
        new PrivacyServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(AccountDeletions::class));
        self::assertTrue($container->hasDefinition(ProcessDeletionsCommand::class));
        self::assertFalse($container->hasDefinition(ProcessDueAccountDeletionsHandler::class));
        self::assertFalse($container->hasDefinition(PrivacySchedule::class));
    }

    public function testWithoutALockNothingThatNeedsOneRuns(): void
    {
        $container = $this->load([]);
        $this->services($container, ['database_connection', 'clock']);
        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(AccountDeletions::class));
        self::assertFalse($container->hasDefinition(PersonalDataDownload::class));
    }

    public function testWithoutAClockNothingThatNeedsOneRuns(): void
    {
        $container = $this->load([]);
        $this->services($container, ['database_connection', 'lock.factory', 'twig', 'security.csrf.token_manager', 'router']);
        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(AccountDeletions::class));
        self::assertFalse($container->hasDefinition(ConsentController::class));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function missingForThePages(): iterable
    {
        yield 'twig' => [['clock', 'security.csrf.token_manager', 'router']];
        yield 'csrf' => [['clock', 'twig', 'router']];
        yield 'router' => [['clock', 'twig', 'security.csrf.token_manager']];
    }

    /**
     * @param list<string> $present
     */
    #[DataProvider('missingForThePages')]
    public function testThePagesNeedTwigTheTokensAndTheRouter(array $present): void
    {
        $container = $this->load([]);
        $this->services($container, $present);
        new PrivacyServicesPass()->process($container);

        foreach ([ConsentRecorder::class, ConsentCookie::class, ConsentContext::class, ConsentVaryListener::class, ConsentController::class, ConsentExtension::class, PreferencesController::class, PrivacyPages::class, MountingRouteLoader::class] as $id) {
            self::assertFalse($container->hasDefinition($id), $id);
        }
    }

    public function testWithoutAFirewallOnlyTheConsentPageIsMounted(): void
    {
        $container = $this->load([]);
        $this->services($container, ['clock', 'twig', 'security.csrf.token_manager', 'router']);
        new PrivacyServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(ConsentController::class));
        self::assertFalse($container->hasDefinition(PreferencesController::class));
        self::assertFalse($container->hasDefinition(SecurityCurrentDataSubject::class));
        self::assertFalse($container->hasAlias(CurrentDataSubject::class));
        self::assertFalse($container->getDefinition(MountingRouteLoader::class)->getArgument(2));
    }

    public function testATokenStorageAloneIsNotAFirewall(): void
    {
        foreach (['security.token_storage', 'security.authorization_checker'] as $only) {
            $container = $this->load(['pages' => ['auto_mount' => false]]);
            $this->services($container, ['clock', 'twig', 'security.csrf.token_manager', 'router', $only]);
            new PrivacyServicesPass()->process($container);

            self::assertFalse($container->hasDefinition(PreferencesController::class));
        }
    }

    public function testWithAFirewallThePreferencesStay(): void
    {
        $container = $this->load([]);
        $this->services($container, ['clock', 'twig', 'security.csrf.token_manager', 'router', 'security.token_storage', 'security.authorization_checker']);
        new PrivacyServicesPass()->process($container);

        self::assertTrue($container->hasDefinition(PreferencesController::class));
        self::assertTrue($container->getDefinition(MountingRouteLoader::class)->getArgument(2));
    }

    public function testAProcessOnAContainerThatDoesNotHaveTheBundleIsHarmless(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(PrivacyServicesPass::PROOFS_PARAMETER, null);
        new PrivacyServicesPass()->process($container);

        self::assertFalse($container->hasDefinition(AccountDeletions::class));
    }

    /**
     * @param list<string> $ids
     */
    private function services(ContainerBuilder $container, array $ids): void
    {
        foreach ($ids as $id) {
            $container->register($id, stdClass::class);
        }
    }

    /**
     * @param array<string, mixed>              $config
     * @param array<string, string>|string|null $bundles the parameter kernel.bundles, null for none
     */
    private function load(array $config, array|string|null $bundles = [RightsServices::SECURITY_BUNDLE => SecurityBundle::class]): ContainerBuilder
    {
        $bundle = new PhilPrivacyBundle($this->warnings);
        $container = new ContainerBuilder();

        // An application with accounts, as most are, unless the test says otherwise: the account deletions are on by default.
        if (null !== $bundles) {
            $container->setParameter('kernel.bundles', $bundles);
        }

        $instanceof = [];
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);
        $bundle->loadExtension(new Processor()->processConfiguration($configuration, [$config]), new ContainerConfigurator($container, new PhpFileLoader($container, new FileLocator()), $instanceof, __FILE__, __FILE__), $container);

        return $container;
    }
}
