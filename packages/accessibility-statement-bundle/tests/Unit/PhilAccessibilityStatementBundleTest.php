<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Tests\Unit;

use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityStatement\Audit\ReportReader;
use Phil\AccessibilityStatement\Contract\AuditSource;
use Phil\AccessibilityStatement\EvaluationMethod;
use Phil\AccessibilityStatement\StatementGenerator;
use Phil\AccessibilityStatementBundle\Application\StatementPublisher;
use Phil\AccessibilityStatementBundle\Application\StatementRegenerator;
use Phil\AccessibilityStatementBundle\Domain\Contract\StatementRepository;
use Phil\AccessibilityStatementBundle\Infrastructure\Audit\ReportDirectory;
use Phil\AccessibilityStatementBundle\Infrastructure\Config\ConfiguredStatements;
use Phil\AccessibilityStatementBundle\Infrastructure\Console\RegenerateCommand;
use Phil\AccessibilityStatementBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\AccessibilityStatementBundle\Infrastructure\DependencyInjection\StatementServices;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\DbalStatements;
use Phil\AccessibilityStatementBundle\Infrastructure\Doctrine\StatementSchemaListener;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementAdminController;
use Phil\AccessibilityStatementBundle\Infrastructure\Http\StatementController;
use Phil\AccessibilityStatementBundle\Infrastructure\LegalNotices\StatementFooterLink;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\KitComponents;
use Phil\AccessibilityStatementBundle\Infrastructure\Twig\StatementExtension;
use Phil\AccessibilityStatementBundle\PhilAccessibilityStatementBundle;
use Phil\AccessibilityStatementBundle\Tests\Unit\Fixture\Statements;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilAccessibilityStatementBundle::class)]
#[CoversClass(StatementServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
final class PhilAccessibilityStatementBundleTest extends TestCase
{
    public function testTheDefaultsServeTheStatementOfTheConfiguration(): void
    {
        $container = $this->load(['statement' => Statements::minimal()]);

        self::assertSame('phil_accessibility_statement', $this->extension()->getAlias());
        self::assertSame(['service_container', ConfiguredStatements::class, StatementController::class, KitComponents::class, StatementExtension::class, StatementFooterLink::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference(StatementRepository::class), new Reference('router')], $container->getDefinition(StatementFooterLink::class)->getArguments());
        self::assertSame(['phil_legal_notices.footer_link' => [[]]], $container->getDefinition(StatementFooterLink::class)->getTags());
        self::assertSame([Statements::minimal()], $container->getDefinition(ConfiguredStatements::class)->getArguments());
        self::assertSame(ConfiguredStatements::class, (string) $container->getAlias(StatementRepository::class));
        self::assertTrue($container->getAlias(StatementRepository::class)->isPublic());

        $controller = $container->getDefinition(StatementController::class);
        self::assertEquals([new Reference(StatementRepository::class), new Reference('twig'), PhilAccessibilityStatementBundle::LAYOUT, 'wcag', null, 3600, null], $controller->getArguments());
        self::assertTrue($controller->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $controller->getTags());

        $extension = $container->getDefinition(StatementExtension::class);
        self::assertEquals([new Reference('router'), new Reference('request_stack'), 'en', new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference(KitComponents::class)], $extension->getArguments());
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertFalse($container->hasDefinition(StatementAdminController::class));
        self::assertSame(['twig.attribute_extension' => [[]], 'twig.runtime' => [[]]], $extension->getTags());
        self::assertFalse($container->hasAlias(AuditSource::class));
    }

    public function testWithoutAStatementNoneIsPublishedYet(): void
    {
        $container = $this->load([]);

        self::assertSame([null], $container->getDefinition(ConfiguredStatements::class)->getArguments());
        self::assertSame(ConfiguredStatements::class, (string) $container->getAlias(StatementRepository::class));
    }

    public function testEveryOptionIsPassedOn(): void
    {
        $container = $this->load([
            'source' => 'doctrine',
            'connection' => 'legal',
            'clock' => 'app.clock',
            'default_locale' => 'fr',
            'wording' => 'aca',
            'layout' => 'base.html.twig',
            'cache_max_age' => 0,
            'feedback_route' => 'phil_contact_form',
            'feedback_form' => 'accessibility-0',
            'kit_subdirectory' => 'ui/kit',
            'admin' => ['enabled' => true, 'role' => 'ROLE_EDITOR'],
            'audit' => ['enabled' => true, 'directory' => '/srv/audit', 'minimum_severity' => 'warning', 'method' => 'third-party', 'report_url' => '/audit'],
        ]);

        self::assertSame([
            'service_container',
            DbalStatements::class,
            StatementSchemaListener::class,
            StatementController::class,
            KitComponents::class,
            StatementExtension::class,
            StatementFooterLink::class,
            ReportDirectory::class,
            StatementRegenerator::class,
            RegenerateCommand::class,
            StatementPublisher::class,
            StatementAdminController::class,
        ], array_keys($container->getDefinitions()));

        $connection = new Reference('doctrine.dbal.legal_connection');
        self::assertEquals([$connection, null, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(DbalStatements::class)->getArguments());
        self::assertSame(DbalStatements::class, (string) $container->getAlias(StatementRepository::class));
        self::assertTrue($container->getAlias(StatementRepository::class)->isPublic());
        self::assertEquals([$connection], $container->getDefinition(StatementSchemaListener::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'legal']]], $container->getDefinition(StatementSchemaListener::class)->getTags());
        self::assertEquals([new Reference(StatementRepository::class), new Reference('twig'), 'base.html.twig', 'aca', 'phil_contact_form', 0, 'accessibility-0'], $container->getDefinition(StatementController::class)->getArguments());
        self::assertSame(['ui/kit'], $container->getDefinition(KitComponents::class)->getArguments());
        $publisher = $container->getDefinition(StatementPublisher::class);
        self::assertEquals([new Reference(StatementRepository::class), new Reference('app.clock'), new Reference(StatementRegenerator::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)], $publisher->getArguments());
        self::assertTrue($publisher->isPublic());
        $admin = $container->getDefinition(StatementAdminController::class);
        self::assertEquals([
            new Reference(StatementRepository::class),
            new Reference(StatementPublisher::class),
            new Reference('twig'),
            new Reference('router'),
            'base.html.twig',
            'ROLE_EDITOR',
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('security.csrf.token_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ], $admin->getArguments());
        self::assertTrue($admin->isPublic());
        self::assertSame(['controller.service_arguments' => [[]]], $admin->getTags());
        self::assertSame('ROLE_ADMIN', $this->load(['statement' => Statements::minimal(), 'admin' => true])->getDefinition(StatementAdminController::class)->getArgument(5));
        self::assertSame('fr', $container->getDefinition(StatementExtension::class)->getArgument(2));

        self::assertEquals(['/srv/audit', new Definition(ReportReader::class), EvaluationMethod::ThirdParty, '/audit'], $container->getDefinition(ReportDirectory::class)->getArguments());
        self::assertSame(ReportDirectory::class, (string) $container->getAlias(AuditSource::class));
        $regenerator = $container->getDefinition(StatementRegenerator::class);
        self::assertEquals([new Reference(StatementRepository::class), new Reference(AuditSource::class), new Definition(StatementGenerator::class, [Severity::Warning]), new Reference('app.clock')], $regenerator->getArguments());
        self::assertTrue($regenerator->isPublic());
        $command = $container->getDefinition(RegenerateCommand::class);
        self::assertEquals([new Reference(StatementRegenerator::class), new Reference(StatementRepository::class)], $command->getArguments());
        self::assertSame(['console.command' => [[]]], $command->getTags());
    }

    public function testTheAuditDefaultsAndAnAuditSourceOfItsOwn(): void
    {
        $container = $this->load(['statement' => Statements::minimal(), 'audit' => true]);
        self::assertEquals(['%kernel.project_dir%/var/accessibility', new Definition(ReportReader::class), EvaluationMethod::Automated, null], $container->getDefinition(ReportDirectory::class)->getArguments());
        self::assertEquals(new Definition(StatementGenerator::class, [Severity::Error]), $container->getDefinition(StatementRegenerator::class)->getArgument(2));
        self::assertEquals(new Reference('clock'), $container->getDefinition(StatementRegenerator::class)->getArgument(3));

        $container = $this->load(['statement' => Statements::minimal(), 'audit' => ['source' => 'app.audits']]);
        self::assertFalse($container->hasDefinition(ReportDirectory::class));
        self::assertSame('app.audits', (string) $container->getAlias(AuditSource::class));
    }

    public function testTheDefaultValuesMayBeWritten(): void
    {
        $container = $this->load(['source' => 'config', 'wording' => 'wcag', 'statement' => Statements::minimal(), 'audit' => ['minimum_severity' => 'error', 'method' => 'automated']]);

        self::assertTrue($container->hasDefinition(ConfiguredStatements::class));
        self::assertSame(EvaluationMethod::Automated, $container->getDefinition(ReportDirectory::class)->getArgument(2));
        self::assertEquals(new Definition(StatementGenerator::class, [Severity::Error]), $container->getDefinition(StatementRegenerator::class)->getArgument(2));
        self::assertSame('en-301-549', $this->load(['wording' => 'en-301-549', 'statement' => Statements::minimal()])->getDefinition(StatementController::class)->getArgument(3));
        self::assertEquals(new Definition(StatementGenerator::class, [Severity::Notice]), $this->load(['statement' => Statements::minimal(), 'audit' => ['minimum_severity' => 'notice', 'method' => 'self-assessment']])->getDefinition(StatementRegenerator::class)->getArgument(2));
    }

    public function testTheDoctrineSourceTakesTheStatementOfTheConfigurationAsItsFirstVersion(): void
    {
        $container = $this->load(['source' => 'doctrine', 'statement' => Statements::minimal()]);

        self::assertEquals([new Reference('doctrine.dbal.default_connection'), Statements::minimal(), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(DbalStatements::class)->getArguments());
        self::assertSame(['doctrine.event_listener' => [['event' => 'postGenerateSchema', 'connection' => 'default']]], $container->getDefinition(StatementSchemaListener::class)->getTags());
    }

    public function testADateYamlReadAsATimestampIsReadAsTheMomentItIs(): void
    {
        $statement = [
            ...Statements::minimal(),
            'published_at' => 1_793_491_200,
            'status' => 'partial',
            'non_conformities' => [['criterion' => '1.4.3', 'fix_by' => 1_800_000_000], ['criterion' => '1.1.1', 'fix_by' => '2027-01-01'], ['criterion' => '2.1.1']],
            'evaluation' => ['evaluated_at' => 1_793_404_800],
        ];

        $read = $this->load(['statement' => $statement])->getDefinition(ConfiguredStatements::class)->getArgument(0);

        self::assertIsArray($read);
        self::assertSame('2026-11-01T00:00:00+00:00', $read['published_at']);
        self::assertSame([['criterion' => '1.4.3', 'fix_by' => '2027-01-15T08:00:00+00:00'], ['criterion' => '1.1.1', 'fix_by' => '2027-01-01'], ['criterion' => '2.1.1']], $read['non_conformities']);
        self::assertSame(['evaluated_at' => '2026-10-31T00:00:00+00:00'], $read['evaluation']);
        self::assertSame(['version' => 1], array_intersect_key(StatementServices::statement(['version' => 1, 'published_at' => '2026-11-01', 'feedback' => ['email' => 'a@example.com'], 'evaluation' => ['evaluated_at' => '2026-10-01']]), ['version' => true]));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigurations')]
    public function testAnInvalidConfigurationFailsTheBuild(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'a statement as text' => [['statement' => 'full'], 'The statement is a map.'];
        yield 'a statement that cannot be published' => [['statement' => [...Statements::minimal(), 'status' => 'partial']], 'phil_accessibility_statement.statement: A statement that lists no criterion not met claims full conformance.'];
        yield 'an unknown source' => [['source' => 'file', 'statement' => Statements::minimal()], 'The value "file" is not allowed for path "phil_accessibility_statement.source"'];
        yield 'an unknown wording' => [['wording' => 'ada', 'statement' => Statements::minimal()], 'The value "ada" is not allowed for path "phil_accessibility_statement.wording"'];
        yield 'a negative age' => [['cache_max_age' => -1, 'statement' => Statements::minimal()], 'Should be greater than or equal to 0'];
        yield 'an unknown severity' => [['statement' => Statements::minimal(), 'audit' => ['minimum_severity' => 'fatal']], 'The value "fatal" is not allowed'];
        yield 'an unknown method' => [['statement' => Statements::minimal(), 'audit' => ['method' => 'guess']], 'The value "guess" is not allowed'];
        yield 'a form key with capitals' => [['statement' => Statements::minimal(), 'feedback_form' => 'Feedback'], 'The key of a form is 1 to 64 lower-case letters, digits or "-".'];
        yield 'a form key too long' => [['statement' => Statements::minimal(), 'feedback_form' => 'a'.str_repeat('b', 64)], 'The key of a form is 1 to 64 lower-case letters, digits or "-".'];
        yield 'a form key as a number' => [['statement' => Statements::minimal(), 'feedback_form' => 12], 'The key of a form is 1 to 64 lower-case letters, digits or "-".'];
        yield 'a form key then a line' => [['statement' => Statements::minimal(), 'feedback_form' => "feedback\n"], 'The key of a form is 1 to 64 lower-case letters, digits or "-".'];
        yield 'an empty role' => [['statement' => Statements::minimal(), 'admin' => ['role' => '']], 'cannot contain an empty value'];
    }

    public function testTheRecipesAreNamedAfterTheSubdirectoryOfTheToolkitUnlessTheConfigurationSaysIt(): void
    {
        $toolkit = $this->compiled(['statement' => Statements::minimal()], 'ui');
        self::assertSame(['ui'], $toolkit->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([''], $this->compiled(['statement' => Statements::minimal()], null)->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([''], $this->compiled(['statement' => Statements::minimal()], false)->getDefinition(KitComponents::class)->getArguments());
        self::assertSame(['own'], $this->compiled(['statement' => Statements::minimal(), 'kit_subdirectory' => 'own'], 'ui')->getDefinition(KitComponents::class)->getArguments());
        self::assertSame([''], $this->compiled(['statement' => Statements::minimal(), 'kit_subdirectory' => ''], 'ui')->getDefinition(KitComponents::class)->getArguments());

        $empty = new ContainerBuilder();
        new KitSubdirectoryPass()->process($empty);
        self::assertFalse($empty->hasDefinition(KitComponents::class));
        self::assertSame(['ui:Card:Title', 'a:b:Badge', 'Badge'], [new KitComponents('/ui/')->name('Card:Title'), new KitComponents('a/b')->name('Badge'), new KitComponents()->name('Badge')]);
    }

    /**
     * @param array<string, mixed> $config
     * @param string|false|null    $toolkit the subdirectory of the Toolkit's configuration; false when the Toolkit is not there
     */
    private function compiled(array $config, string|false|null $toolkit): ContainerBuilder
    {
        $container = $this->load($config);

        if (false !== $toolkit) {
            $container->setParameter('.ux_toolkit.components.subdirectory', $toolkit);
        }

        new PhilAccessibilityStatementBundle()->build($container);

        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if ($pass instanceof KitSubdirectoryPass) {
                $pass->process($container);
            }
        }

        return $container;
    }

    public function testAStatementThatCannotBePublishedKeepsItsReason(): void
    {
        try {
            StatementServices::statement(['version' => 0]);
            self::fail('The statement was accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertNotNull($invalidConfigurationException->getPrevious());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilAccessibilityStatementBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
