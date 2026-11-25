<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit;

use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Application\Register\ProcessingRegister;
use Phil\PrivacyBundle\Application\SubjectErasure;
use Phil\PrivacyBundle\Application\SubjectExport;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Infrastructure\Console\EraseSubjectCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ExportSubjectCommand;
use Phil\PrivacyBundle\Infrastructure\Console\ProcessingRegisterCommand;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\DatabaseServicesPass;
use Phil\PrivacyBundle\Infrastructure\DependencyInjection\PrivacyServicesPass;
use Phil\PrivacyBundle\Infrastructure\Monolog\RedactingProcessor;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessengerBundle;

/**
 * The wiring the bundle declares, read on an uncompiled `ContainerBuilder`: no kernel, no Monolog handler.
 */
#[CoversClass(PhilPrivacyBundle::class)]
final class PhilPrivacyBundleTest extends TestCase
{
    public function testTheProcessorIsTaggedForMonolog(): void
    {
        $definition = $this->load([])->getDefinition(RedactingProcessor::class);

        self::assertTrue($definition->hasTag('monolog.processor'));
        self::assertSame([RedactingProcessor::class, 'fromConfiguration'], $definition->getFactory());
    }

    public function testTheDenylistAndThePrefixesComeFromConfiguration(): void
    {
        $container = $this->load(['log_redaction' => ['denylist' => ['password', 'Authorization'], 'token_path_prefixes' => ['/p/']]]);

        self::assertSame([['password', 'Authorization'], ['/p/'], [], ['email', 'person_name', 'nickname', 'phone', 'ip', 'contact']], $container->getDefinition(RedactingProcessor::class)->getArguments());
    }

    public function testTheDefaultsDenyThePersonalDataCategoriesOnlyAndDeclareNoPrefix(): void
    {
        $container = $this->load([]);

        self::assertSame([[], [], [], RedactingProcessor::PERSONAL_DATA_CATEGORIES], $container->getDefinition(RedactingProcessor::class)->getArguments());
        self::assertSame(['email', 'person_name', 'nickname', 'phone', 'ip', 'contact'], RedactingProcessor::PERSONAL_DATA_CATEGORIES);
    }

    public function testThePersonalDataCategoriesAreConfiguredAndChecked(): void
    {
        $container = $this->load(['log_redaction' => ['personal_data_categories' => []]]);
        self::assertSame([[], [], [], []], $container->getDefinition(RedactingProcessor::class)->getArguments());

        $container = $this->load(['log_redaction' => ['personal_data_categories' => ['birth_date']]]);
        self::assertSame([[], [], [], ['birth_date']], $container->getDefinition(RedactingProcessor::class)->getArguments());

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.log_redaction: "Birth Date" is not a data category');

        $this->load(['log_redaction' => ['personal_data_categories' => ['Birth Date']]]);
    }

    public function testTheShortOptionTableComesFromConfiguration(): void
    {
        $container = $this->load(['log_redaction' => ['denylist' => ['password'], 'short_options' => ['p' => 'password', 'P' => 'port']]]);

        self::assertSame([['password'], [], ['p' => 'password', 'P' => 'port'], RedactingProcessor::PERSONAL_DATA_CATEGORIES], $container->getDefinition(RedactingProcessor::class)->getArguments());
    }

    public function testAMalformedShortOptionFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.log_redaction: "pw" is not a short option');

        $this->load(['log_redaction' => ['short_options' => ['pw' => 'password']]]);
    }

    /**
     * A letter is read as written: the configuration does not turn `-p` into `_p` before the domain refuses it.
     */
    public function testAShortOptionKeyIsNotNormalised(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.log_redaction: "-p" is not a short option');

        $this->load(['log_redaction' => ['short_options' => ['-p' => 'password']]]);
    }

    public function testANonStringShortOptionNameIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.log_redaction.short_options must map letters to long option names.');

        $this->load(['log_redaction' => ['short_options' => ['p' => 5]]]);
    }

    /**
     * A bad entry fails the container build, not the first log line.
     */
    public function testAMalformedPrefixFailsTheBuild(): void
    {
        try {
            $this->load(['log_redaction' => ['token_path_prefixes' => ['p']]]);
            self::fail('A malformed prefix must fail the build.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringStartsWith('phil_privacy.log_redaction: "p" is not a token path prefix', $invalidConfigurationException->getMessage());
            self::assertSame(0, $invalidConfigurationException->getCode());
            self::assertInstanceOf(InvalidPrivacyValue::class, $invalidConfigurationException->getPrevious());
        }
    }

    public function testAnEmptyDenylistKeyFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load(['log_redaction' => ['denylist' => ['--']]]);
    }

    public function testAMisplacedPatternStarFailsTheBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.log_redaction: "se*ed" is not a denylist key');

        $this->load(['log_redaction' => ['denylist' => ['*seed*', 'se*ed']]]);
    }

    public function testANonStringDenylistKeyIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.log_redaction.denylist must be a list of strings.');

        $this->load(['log_redaction' => ['denylist' => [5]]]);
    }

    public function testExportErasureAndTheRegisterAreWired(): void
    {
        $container = $this->load(['retention_schedule_source' => 'app.schedule', 'register' => ['classes' => ['App\\Entity\\Member']]]);

        self::assertEquals([new TaggedIteratorArgument('phil_privacy.exporter')], $container->getDefinition(SubjectExport::class)->getArguments());
        self::assertEquals([new TaggedIteratorArgument('phil_privacy.eraser')], $container->getDefinition(SubjectErasure::class)->getArguments());
        self::assertTrue($container->getDefinition(SubjectExport::class)->isPublic());
        self::assertTrue($container->getDefinition(SubjectErasure::class)->isPublic());
        self::assertTrue($container->getAutoconfiguredInstanceof()[PersonalDataExporter::class]->hasTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertTrue($container->getAutoconfiguredInstanceof()[PersonalDataEraser::class]->hasTag(PhilPrivacyBundle::ERASER_TAG));
        self::assertSame(['phil_privacy.exporter', 'phil_privacy.eraser'], [PhilPrivacyBundle::EXPORTER_TAG, PhilPrivacyBundle::ERASER_TAG]);
        self::assertEquals([new Reference('app.schedule')], $container->getDefinition(ProcessingRegister::class)->getArguments());
        self::assertTrue($container->getDefinition(ProcessingRegister::class)->isPublic());

        self::assertEquals([new Reference(SubjectExport::class)], $container->getDefinition(ExportSubjectCommand::class)->getArguments());
        self::assertEquals([new Reference(SubjectErasure::class)], $container->getDefinition(EraseSubjectCommand::class)->getArguments());
        self::assertEquals([new Reference(ProcessingRegister::class), ['App\\Entity\\Member'], new Reference('doctrine', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(ProcessingRegisterCommand::class)->getArguments());

        foreach ([ExportSubjectCommand::class, EraseSubjectCommand::class, ProcessingRegisterCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }

        $defaults = $this->load([]);
        self::assertSame([null], $defaults->getDefinition(ProcessingRegister::class)->getArguments());
        self::assertSame([], $defaults->getDefinition(ProcessingRegisterCommand::class)->getArgument(1));
    }

    public function testRegisterClassesAreStrings(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_privacy.register.classes must be a list of strings.');

        $this->load(['register' => ['classes' => [1]]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilPrivacyBundle()->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('phil_privacy', $extension->getAlias());

        $extension->load([$config], $container);

        return $container;
    }

    public function testTheHandlersAreRemovedBeforeMessengerWiresThem(): void
    {
        $container = new ContainerBuilder();
        new MessengerBundle()->build($container);
        new PhilPrivacyBundle()->build($container);

        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
        $messenger = array_search(MessengerPass::class, $passes, true);
        self::assertIsInt($messenger);

        $removal = array_search(DatabaseServicesPass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertLessThan($messenger, $removal);

        $removal = array_search(PrivacyServicesPass::class, $passes, true);
        self::assertIsInt($removal);
        self::assertLessThan($messenger, $removal);
    }
}
