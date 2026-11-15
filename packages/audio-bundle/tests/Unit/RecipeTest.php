<?php

declare(strict_types=1);

namespace Phil\AudioBundle\Tests\Unit;

use Phil\Audio\AudioFormat;
use Phil\Audio\ListeningMode;
use Phil\Audio\NullAudio;
use Phil\Audio\Rendition;
use Phil\AudioBundle\Application\Renditions;
use Phil\AudioBundle\PhilAudioBundle;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The Flex recipe: it registers the bundle and copies a configuration the bundle accepts as is.
 */
#[CoversNothing]
final class RecipeTest extends TestCase
{
    private const string RECIPE = __DIR__.'/../../recipe/0.1';

    public function testTheRecipeRegistersTheBundleAndCopiesItsConfiguration(): void
    {
        $manifest = json_decode((string) file_get_contents(self::RECIPE.'/manifest.json'), true, flags: \JSON_THROW_ON_ERROR);
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_audio.yaml');

        self::assertSame(['bundles' => [PhilAudioBundle::class => ['all']], 'copy-from-recipe' => ['config/' => '%CONFIG_DIR%/']], $manifest);
        self::assertSame(['phil_audio' => ['tools' => 'null', 'renditions' => ['mp3' => ['format' => 'mp3', 'bitrate' => 128]], 'listening' => ['mode' => 'full']]], $config);
    }

    public function testTheBundleAcceptsTheCopiedConfiguration(): void
    {
        /** @var array{phil_audio: array<string, mixed>} $config */
        $config = Yaml::parseFile(self::RECIPE.'/config/packages/phil_audio.yaml');
        $extension = new PhilAudioBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $extension->load([$config['phil_audio']], $container);

        self::assertSame(NullAudio::class, $container->getDefinition('phil_audio.tools')->getClass());
        self::assertEquals([['mp3' => new Definition(Rendition::class, [AudioFormat::Mp3, 128, null, null])]], $container->getDefinition(Renditions::class)->getArguments());
        self::assertSame([ListeningMode::Full, null], $container->getDefinition('phil_audio.listening_policy')->getArguments());
    }
}
