<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\FormsAutosave\Application\Contract\DraftOwner;
use Phil\FormsAutosave\Application\Contract\DraftSealer;
use Phil\FormsAutosave\Application\Contract\DraftStore;
use Phil\FormsAutosave\Application\DraftKeys;
use Phil\FormsAutosave\Application\Drafts;
use Phil\FormsAutosave\Application\PlainSealer;
use Phil\FormsAutosave\Infrastructure\Console\PurgeCommand;
use Phil\FormsAutosave\Infrastructure\DependencyInjection\AutosaveServices;
use Phil\FormsAutosave\Infrastructure\DependencyInjection\ServerStorePass;
use Phil\FormsAutosave\Infrastructure\Doctrine\DoctrineDraftStore;
use Phil\FormsAutosave\Infrastructure\Form\AutosaveLabels;
use Phil\FormsAutosave\Infrastructure\Form\AutosaveTypeExtension;
use Phil\FormsAutosave\Infrastructure\Http\DoneCookie;
use Phil\FormsAutosave\Infrastructure\Http\DraftController;
use Phil\FormsAutosave\Infrastructure\Http\DraftRequest;
use Phil\FormsAutosave\Infrastructure\KeyManagement\KeyManagementSealer;
use Phil\FormsAutosave\Infrastructure\Memory\InMemoryDraftStore;
use Phil\FormsAutosave\Infrastructure\Security\TokenDraftOwner;
use Phil\FormsAutosave\PhilFormsAutosaveBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Local\SodiumKms;

#[CoversClass(PhilFormsAutosaveBundle::class)]
#[CoversClass(AutosaveServices::class)]
#[CoversClass(ServerStorePass::class)]
final class PhilFormsAutosaveBundleTest extends TestCase
{
    public function testTheServicesOfTheBrowserAlone(): void
    {
        $container = $this->load([]);
        $optional = ContainerInterface::NULL_ON_INVALID_REFERENCE;

        self::assertSame('phil_forms_autosave', $this->extension()->getAlias());
        self::assertSame(['service_container', DraftKeys::class, AutosaveLabels::class, DoneCookie::class, AutosaveTypeExtension::class], array_keys($container->getDefinitions()));
        self::assertSame(['%kernel.secret%'], $container->getDefinition(DraftKeys::class)->getArguments());
        self::assertEquals([new Reference('translator', $optional)], $container->getDefinition(AutosaveLabels::class)->getArguments());
        self::assertEquals([new Reference('clock'), new Reference(Drafts::class, $optional)], $container->getDefinition(DoneCookie::class)->getArguments());
        self::assertSame([['event' => 'kernel.response']], $container->getDefinition(DoneCookie::class)->getTag('kernel.event_listener'));
        self::assertEquals([
            new Reference(DraftKeys::class),
            new Reference(AutosaveLabels::class),
            new Reference('request_stack'),
            604800,
            800,
            262144,
            false,
            new Reference(DraftOwner::class, $optional),
            new Reference(Drafts::class, $optional),
            new Reference('router', $optional),
            new Reference(DraftRequest::class, $optional),
        ], $container->getDefinition(AutosaveTypeExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(AutosaveTypeExtension::class)->hasTag('form.type_extension'));
        self::assertFalse($container->has(DraftOwner::class));
    }

    public function testTheServicesOfTheServerStore(): void
    {
        $container = $this->load(['secret' => 's', 'ttl' => 60, 'debounce' => 100, 'max_bytes' => 1024, 'server' => ['enabled' => true, 'max_bytes' => 2048, 'owner' => 'app.owner']]);

        self::assertSame(['service_container', DraftKeys::class, AutosaveLabels::class, DoneCookie::class, DoctrineDraftStore::class, Drafts::class, DraftRequest::class, DraftController::class, PurgeCommand::class, AutosaveTypeExtension::class], array_keys($container->getDefinitions()));
        self::assertSame('app.owner', (string) $container->getAlias(DraftOwner::class));
        self::assertEquals([new Reference('doctrine.dbal.default_connection')], $container->getDefinition(DoctrineDraftStore::class)->getArguments());
        self::assertSame([DoctrineDraftStore::class, true], [(string) $container->getAlias(DraftStore::class), $container->getAlias(DraftStore::class)->isPublic()]);
        self::assertFalse($container->has(DraftSealer::class), 'the encrypting sealer is completed by the pass');
        self::assertEquals([new Reference(DraftStore::class), new Reference(DraftSealer::class), new Reference(DraftKeys::class), new Reference('clock'), 60, 2048], $container->getDefinition(Drafts::class)->getArguments());
        self::assertTrue($container->getDefinition(Drafts::class)->isPublic());
        self::assertEquals([new Reference('security.csrf.token_manager')], $container->getDefinition(DraftRequest::class)->getArguments());
        self::assertEquals([new Reference(Drafts::class), new Reference(DraftRequest::class), new Reference(DraftOwner::class), 2048], $container->getDefinition(DraftController::class)->getArguments());
        self::assertTrue($container->getDefinition(DraftController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(DraftController::class)->isPublic());
        self::assertEquals([new Reference(Drafts::class)], $container->getDefinition(PurgeCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(PurgeCommand::class)->hasTag('console.command'));
        self::assertSame(['s'], $container->getDefinition(DraftKeys::class)->getArguments());
        self::assertSame([60, 100, 1024, true], \array_slice($container->getDefinition(AutosaveTypeExtension::class)->getArguments(), 3, 4));

        $memory = $this->load(['server' => ['enabled' => true, 'store' => 'memory', 'encrypt' => false]]);
        self::assertSame([InMemoryDraftStore::class, true], [(string) $memory->getAlias(DraftStore::class), $memory->getAlias(DraftStore::class)->isPublic()]);
        self::assertSame([['method' => 'reset']], $memory->getDefinition(InMemoryDraftStore::class)->getTag('kernel.reset'));
        self::assertSame(PlainSealer::class, (string) $memory->getAlias(DraftSealer::class));
        self::assertTrue($memory->hasDefinition(PlainSealer::class));
        self::assertSame(DoctrineDraftStore::class, (string) $this->load(['server' => ['enabled' => true, 'store' => 'doctrine']])->getAlias(DraftStore::class));
        self::assertEquals([new Reference('doctrine.dbal.drafts_connection')], $this->load(['server' => ['enabled' => true, 'connection' => 'drafts']])->getDefinition(DoctrineDraftStore::class)->getArguments());
        self::assertSame(604800, $memory->getDefinition(Drafts::class)->getArgument(4));
        self::assertSame(65536, $memory->getDefinition(Drafts::class)->getArgument(5));
    }

    public function testTheBoundsOfTheConfiguration(): void
    {
        self::assertSame([60, 100, 1024], \array_slice($this->load(['ttl' => 60, 'debounce' => 100, 'max_bytes' => 1024])->getDefinition(AutosaveTypeExtension::class)->getArguments(), 3, 3));
        self::assertSame([31536000, 10000, 4194304], \array_slice($this->load(['ttl' => 31536000, 'debounce' => 10000, 'max_bytes' => 4194304])->getDefinition(AutosaveTypeExtension::class)->getArguments(), 3, 3));
        self::assertSame([1024, 4194304], [
            $this->load(['server' => ['enabled' => true, 'max_bytes' => 1024]])->getDefinition(Drafts::class)->getArgument(5),
            $this->load(['server' => ['enabled' => true, 'max_bytes' => 4194304]])->getDefinition(Drafts::class)->getArgument(5),
        ]);

        foreach ([['ttl' => 59], ['ttl' => 31536001], ['debounce' => 99], ['debounce' => 10001], ['max_bytes' => 1023], ['max_bytes' => 4194305], ['secret' => ''], ['server' => ['store' => 'redis']], ['server' => ['connection' => '']], ['server' => ['max_bytes' => 1023]], ['server' => ['max_bytes' => 4194305]]] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringContainsString('phil_forms_autosave', $invalid->getMessage());
            }
        }
    }

    public function testTheMappingFollowsTheServerStoreAndTheAssetsArePrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $mapping = [['orm' => ['mappings' => ['PhilFormsAutosave' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\FormsAutosave\Domain\Model',
        ]]]]];

        foreach ([
            [[['server' => ['enabled' => true]]], $mapping],
            [[['server' => ['enabled' => 'true']]], $mapping],
            [[['server' => ['enabled' => true]], ['server' => ['enabled' => false]]], []],
            [[['server' => ['enabled' => false]], ['server' => ['enabled' => true]], ['server' => []], ['ttl' => 60]], $mapping],
            [[['server' => 'x']], []],
            [[[]], []],
        ] as [$configs, $expected]) {
            $container = new ContainerBuilder();
            $container->registerExtension(new DoctrineExtension());
            $container->registerExtension(new FrameworkExtension());
            $container->registerExtension($extension);

            foreach ($configs as $config) {
                $container->loadFromExtension('phil_forms_autosave', $config);
            }

            $extension->prepend($container);
            self::assertSame($expected, $container->getExtensionConfig('doctrine'), (string) json_encode($configs));
            self::assertSame([['csrf_protection' => ['stateless_token_ids' => ['phil_forms_autosave']]], ['asset_mapper' => ['paths' => [
                PhilFormsAutosaveBundle::CONTROLLERS_DIR => '@phil/forms-autosave',
                PhilFormsAutosaveBundle::STYLES_DIR => 'phil-forms-autosave',
            ]]]], $container->getExtensionConfig('framework'));
        }

        self::assertFileExists(PhilFormsAutosaveBundle::CONTROLLERS_DIR.'/autosave_controller.js');
        self::assertFileExists(PhilFormsAutosaveBundle::STYLES_DIR.'/forms-autosave.css');

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);
        $bare->loadFromExtension('phil_forms_autosave', ['server' => ['enabled' => true]]);

        $extension->prepend($bare);
        self::assertSame([[], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('framework')]);
    }

    public function testThePassIsAddedAndPathIsThePackage(): void
    {
        $container = new ContainerBuilder();
        new PhilFormsAutosaveBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof ServerStorePass));
        self::assertSame(\dirname(__DIR__, 2), new PhilFormsAutosaveBundle()->getPath());
        self::assertSame([800, 262144], [PhilFormsAutosaveBundle::DEBOUNCE, PhilFormsAutosaveBundle::MAX_BYTES]);
    }

    public function testTheUserOfSecurityIsTheOwnerUnlessTheApplicationNamesOne(): void
    {
        $container = $this->load([]);
        $container->register('security.token_storage');
        new ServerStorePass()->process($container);
        self::assertSame(TokenDraftOwner::class, (string) $container->getAlias(DraftOwner::class));
        self::assertEquals([new Reference('security.token_storage')], $container->getDefinition(TokenDraftOwner::class)->getArguments());

        $named = $this->load(['server' => ['owner' => 'app.owner']]);
        $named->register('app.owner');
        $named->register('security.token_storage');
        new ServerStorePass()->process($named);
        self::assertSame('app.owner', (string) $named->getAlias(DraftOwner::class));
        self::assertFalse($named->hasDefinition(TokenDraftOwner::class));

        $none = $this->load([]);
        new ServerStorePass()->process($none);
        self::assertFalse($none->has(DraftOwner::class));
    }

    public function testTheServerStoreSealsThroughKeyManagement(): void
    {
        $container = $this->server(['enabled' => true, 'key' => 'drafts']);
        $container->register('key_management.local', SodiumKms::class);
        $container->setAlias(DataKeyGeneratorInterface::class, 'key_management.local');
        new ServerStorePass()->process($container);

        self::assertSame(['drafts', null], [$container->getParameter(AutosaveServices::KEY), $container->getParameter(AutosaveServices::KMS)]);
        self::assertSame(KeyManagementSealer::class, (string) $container->getAlias(DraftSealer::class));
        self::assertEquals([new Reference(DataKeyGeneratorInterface::class), 'drafts'], $container->getDefinition(KeyManagementSealer::class)->getArguments());

        $named = $this->server(['enabled' => true, 'key' => 'drafts', 'kms' => 'key_management.vault']);
        $named->register('key_management.vault', SodiumKms::class);
        new ServerStorePass()->process($named);
        self::assertEquals([new Reference('key_management.vault'), 'drafts'], $named->getDefinition(KeyManagementSealer::class)->getArguments());

        $plain = $this->server(['enabled' => true, 'encrypt' => false]);
        new ServerStorePass(false)->process($plain);
        self::assertSame(PlainSealer::class, (string) $plain->getAlias(DraftSealer::class));
        self::assertFalse($plain->hasDefinition(KeyManagementSealer::class));

        $own = $this->server(['enabled' => true]);
        $own->register('app.sealer', PlainSealer::class);
        $own->setAlias(DraftSealer::class, 'app.sealer');
        new ServerStorePass(false)->process($own);
        self::assertSame('app.sealer', (string) $own->getAlias(DraftSealer::class));
    }

    public function testTheServerStoreStopsTheContainerWhenSomethingIsMissing(): void
    {
        foreach ([
            ['csrf', 'framework.csrf_protection'],
            ['owner', 'phil_forms_autosave.server.owner'],
            ['component', 'composer require symfony/key-management'],
            ['key', 'set "phil_forms_autosave.server.key" to a master key of the KMS'],
            ['kms', 'through the KMS "Symfony\\Component\\KeyManagement\\DataKeyGeneratorInterface", which is not defined'],
            ['named kms', 'through the KMS "key_management.vault", which is not defined'],
        ] as [$missing, $hint]) {
            $container = $this->server(['enabled' => true, ...('key' === $missing ? [] : ['key' => 'drafts']), ...('named kms' === $missing ? ['kms' => 'key_management.vault'] : [])], 'owner' !== $missing, 'csrf' !== $missing);

            try {
                new ServerStorePass('component' !== $missing)->process($container);
                self::fail($missing);
            } catch (LogicException $logic) {
                self::assertStringContainsString($hint, $logic->getMessage());
            }
        }
    }

    /**
     * @param array<string, mixed> $server
     */
    private function server(array $server, bool $owner = true, bool $csrf = true): ContainerBuilder
    {
        $container = $this->load(['server' => [...$server, ...($owner ? ['owner' => 'app.owner'] : [])]]);

        if ($owner) {
            $container->register('app.owner');
        }

        if ($csrf) {
            $container->register('security.csrf.token_manager');
        }

        return $container;
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
        $extension = new PhilFormsAutosaveBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
