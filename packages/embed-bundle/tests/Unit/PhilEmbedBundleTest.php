<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit;

use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\EmbedBundle\Application\Contract\FramingPolicy;
use Phil\EmbedBundle\Application\Embed;
use Phil\EmbedBundle\Application\EmbedCodes;
use Phil\EmbedBundle\Application\Embeds;
use Phil\EmbedBundle\Application\EmbedTokenCodec;
use Phil\EmbedBundle\Application\EmbedTokens;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use Phil\EmbedBundle\Domain\ValueObject\SigningKey;
use Phil\EmbedBundle\Infrastructure\BrowserSecurity\BrowserSecurityFraming;
use Phil\EmbedBundle\Infrastructure\Console\IssueTokenCommand;
use Phil\EmbedBundle\Infrastructure\DependencyInjection\EmbedServices;
use Phil\EmbedBundle\Infrastructure\DependencyInjection\FramingPass;
use Phil\EmbedBundle\Infrastructure\Http\EmbedRequestListener;
use Phil\EmbedBundle\Infrastructure\Http\EmbedScriptController;
use Phil\EmbedBundle\Infrastructure\Http\HeaderFraming;
use Phil\EmbedBundle\Infrastructure\Twig\EmbedExtension;
use Phil\EmbedBundle\PhilEmbedBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilEmbedBundle::class)]
#[CoversClass(EmbedServices::class)]
#[CoversClass(FramingPass::class)]
final class PhilEmbedBundleTest extends TestCase
{
    private const string SECRET = 'a secret of at least thirty-two bytes';

    public function testTheServicesOfAConfiguration(): void
    {
        $container = $this->load([[
            'keys' => [['id' => 'new', 'secret' => self::SECRET], ['id' => 'old', 'secret' => self::SECRET.'!']],
            'allow_localhost' => true,
            'script_max_age' => 60,
            'embeds' => ['calendar' => ['origins' => ['https://a.example'], 'themes' => ['light'], 'default_theme' => 'light', 'ttl' => 3600], 'form' => []],
        ]]);

        self::assertSame('phil_embed', $this->extension()->getAlias());
        $codec = $container->getDefinition(EmbedTokenCodec::class)->getArguments();
        self::assertIsArray($codec[0]);
        self::assertEquals([
            new Definition(SigningKey::class, ['new', self::SECRET])->setFactory([SigningKey::class, 'of']),
            new Definition(SigningKey::class, ['old', self::SECRET.'!'])->setFactory([SigningKey::class, 'of']),
        ], $codec[0]);
        self::assertEquals([new Reference('clock'), true], \array_slice($codec, 1));
        $embeds = $container->getDefinition(Embeds::class)->getArgument(0);
        self::assertEquals([
            new Definition(Embed::class, ['calendar', [new Definition(Origin::class, ['https://a.example', true])->setFactory([Origin::class, 'fromString'])], ['light'], 'light', 3600])->setFactory([Embed::class, 'of']),
            new Definition(Embed::class, ['form', [], [], null, null])->setFactory([Embed::class, 'of']),
        ], $embeds);
        self::assertSame([PhilEmbedBundle::CONTROLLERS_DIR, null, 60], $container->getDefinition(EmbedScriptController::class)->getArguments());
        self::assertSame('auto', $container->getParameter(FramingPass::PARAMETER));
        self::assertSame(HeaderFraming::class, (string) $container->getAlias(FramingPolicy::class));
        self::assertSame([['event' => 'kernel.controller', 'priority' => -16]], $container->getDefinition(EmbedRequestListener::class)->getTag('kernel.event_listener'));
        self::assertSame([['event' => 'kernel.response', 'method' => 'onResponse', 'priority' => -1024]], $container->getDefinition(HeaderFraming::class)->getTag('kernel.event_listener'));
        self::assertTrue($container->getDefinition(IssueTokenCommand::class)->hasTag('console.command'));

        $tokens = $container->getDefinition(EmbedTokens::class);
        self::assertEquals([new Reference(EmbedTokenCodec::class), new Reference(Embeds::class), new Reference('clock')], $tokens->getArguments());
        self::assertTrue($tokens->isPublic());
        $codes = $container->getDefinition(EmbedCodes::class);
        self::assertEquals([new Reference(EmbedTokens::class), new Reference('router'), true], $codes->getArguments());
        self::assertTrue($codes->isPublic());
        self::assertEquals(
            [new Reference(EmbedTokens::class), new Reference(FramingPolicy::class), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE), true],
            $container->getDefinition(EmbedRequestListener::class)->getArguments(),
        );
        self::assertTrue($container->getDefinition(EmbedScriptController::class)->isPublic());
        $extension = $container->getDefinition(EmbedExtension::class);
        self::assertEquals([new Reference('request_stack'), new Reference(EmbedCodes::class), true], $extension->getArguments());
        self::assertTrue($extension->hasTag('twig.attribute_extension'));
        self::assertTrue($extension->hasTag('twig.runtime'));
        self::assertEquals([new Reference(EmbedTokens::class), true], $container->getDefinition(IssueTokenCommand::class)->getArguments());
    }

    public function testTheLimitsOfTheConfiguration(): void
    {
        $key = ['keys' => [['id' => 'k', 'secret' => self::SECRET]]];

        foreach ([0, 31_536_000] as $age) {
            self::assertSame($age, $this->load([[...$key, 'script_max_age' => $age]])->getDefinition(EmbedScriptController::class)->getArgument(2));
        }

        $embeds = $this->load([[...$key, 'embeds' => ['cal' => ['ttl' => 1]]]])->getDefinition(Embeds::class)->getArgument(0);
        self::assertIsArray($embeds);
        self::assertInstanceOf(Definition::class, $embeds[0]);
        self::assertSame(1, $embeds[0]->getArgument(4));
        self::assertSame('auto', $this->load([[...$key, 'framing' => 'auto']])->getParameter(FramingPass::PARAMETER));

        foreach ([
            [['script_max_age' => -1], 'too small'],
            [['script_max_age' => 31_536_001], 'too big'],
            [['embeds' => ['cal' => ['ttl' => 0]]], 'too small'],
        ] as [$more, $message]) {
            try {
                $this->load([[...$key, ...$more]]);
                self::fail('Refused.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([['keys' => [['id' => 'k', 'secret' => self::SECRET]]]]);

        self::assertSame([], $container->getDefinition(Embeds::class)->getArgument(0));
        self::assertSame(86_400, $container->getDefinition(EmbedScriptController::class)->getArgument(2));
        self::assertFalse($container->getDefinition(EmbedTokenCodec::class)->getArgument(2));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'no keys' => [[], 'keys'];
        yield 'an empty list of keys' => [['keys' => []], 'keys'];
        yield 'a short secret' => [['keys' => [['id' => 'k', 'secret' => 'short']]], 'A key is refused'];
        yield 'a bad key identifier' => [['keys' => [['id' => 'a.b', 'secret' => self::SECRET]]], 'A key is refused'];
        yield 'a bad embed name' => [['keys' => [['id' => 'k', 'secret' => self::SECRET]], 'embeds' => ['Calendar' => []]], 'see Embed'];
        yield 'a default theme it lacks' => [['keys' => [['id' => 'k', 'secret' => self::SECRET]], 'embeds' => ['cal' => ['themes' => ['light'], 'default_theme' => 'dark']]], 'see Embed'];
        yield 'an origin with a path' => [['keys' => [['id' => 'k', 'secret' => self::SECRET]], 'embeds' => ['cal' => ['origins' => ['https://a.example/x']]]], 'An origin of the embeds is refused'];
        yield 'localhost not allowed' => [['keys' => [['id' => 'k', 'secret' => self::SECRET]], 'embeds' => ['cal' => ['origins' => ['http://localhost']]]], 'An origin of the embeds is refused'];
        yield 'an unknown framing' => [['keys' => [['id' => 'k', 'secret' => self::SECRET]], 'framing' => 'meta'], 'framing'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refused')]
    public function testARefusedConfiguration(array $config, string $message): void
    {
        try {
            $this->load([$config]);
            self::fail('The configuration is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            self::assertStringNotContainsString(self::SECRET, $invalidConfigurationException->getMessage(), 'a secret never goes in a message');
        }
    }

    public function testTheFramingIsChosenOnceEveryBundleIsLoaded(): void
    {
        $auto = $this->load([['keys' => [['id' => 'k', 'secret' => self::SECRET]]]]);
        $auto->register(BrowserSecurityContext::class, BrowserSecurityContext::class);
        new FramingPass()->process($auto);
        self::assertSame(BrowserSecurityFraming::class, (string) $auto->getAlias(FramingPolicy::class));
        self::assertEquals([new Reference(BrowserSecurityContext::class)], $auto->getDefinition(BrowserSecurityFraming::class)->getArguments());
        self::assertEquals(new Reference(BrowserSecurityContext::class), $auto->getDefinition(EmbedScriptController::class)->getArgument(1));

        $header = $this->load([['keys' => [['id' => 'k', 'secret' => self::SECRET]], 'framing' => 'header']]);
        $header->register(BrowserSecurityContext::class, BrowserSecurityContext::class);
        new FramingPass()->process($header);
        self::assertSame(HeaderFraming::class, (string) $header->getAlias(FramingPolicy::class));
        self::assertFalse($header->hasDefinition(BrowserSecurityFraming::class));
        self::assertEquals(new Reference(BrowserSecurityContext::class), $header->getDefinition(EmbedScriptController::class)->getArgument(1));

        $alone = $this->load([['keys' => [['id' => 'k', 'secret' => self::SECRET]]]]);
        new FramingPass()->process($alone);
        self::assertSame(HeaderFraming::class, (string) $alone->getAlias(FramingPolicy::class));
        self::assertNull($alone->getDefinition(EmbedScriptController::class)->getArgument(1));

        $forced = $this->load([['keys' => [['id' => 'k', 'secret' => self::SECRET]], 'framing' => 'browser_security']]);
        new FramingPass()->process($forced);
        self::assertSame(BrowserSecurityFraming::class, (string) $forced->getAlias(FramingPolicy::class));

        $nothing = new ContainerBuilder();
        new FramingPass()->process($nothing);
        self::assertFalse($nothing->hasAlias(FramingPolicy::class));

        $noController = new ContainerBuilder();
        $noController->setParameter(FramingPass::PARAMETER, 'auto');
        $noController->register(BrowserSecurityContext::class, BrowserSecurityContext::class);
        new FramingPass()->process($noController);
        self::assertFalse($noController->hasDefinition(EmbedScriptController::class));
    }

    public function testThePrependedConfigurationAndThePass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'asset_mapper';
            }
        });
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['paths' => [PhilEmbedBundle::CONTROLLERS_DIR => '@phil/embed-bundle']]], $container->getExtensionConfig('asset_mapper'));

        $without = new ContainerBuilder();
        $without->setParameter('kernel.environment', 'test');
        $without->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension->prepend($without);
        self::assertSame([], $without->getExtensions());

        $built = new ContainerBuilder();
        new PhilEmbedBundle()->build($built);
        self::assertCount(1, array_filter($built->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof FramingPass));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilEmbedBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
