<?php

declare(strict_types=1);

namespace Phil\UxBundle\Tests\Unit;

use Phil\UxBundle\PhilUxBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Bundle\TwigBundle\DependencyInjection\TwigExtension;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

#[CoversClass(PhilUxBundle::class)]
final class PhilUxBundleTest extends TestCase
{
    public function testTheBundleRegistersNoService(): void
    {
        $extension = $this->extension();
        $container = new ContainerBuilder();
        $extension->load([['form_theme' => false]], $container);

        self::assertSame('phil_ux', $extension->getAlias());
        self::assertSame(['service_container'], array_keys($container->getDefinitions()));
    }

    public function testTheKeyTakesABoolean(): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->extension()->load([['form_theme' => 'no']], new ContainerBuilder());
    }

    /**
     * The theme comes before the application's themes; the stylesheet of phil/ux-css is mapped as phil-ux/.
     */
    public function testTheThemeAndTheStylesheetArePrepended(): void
    {
        $container = $this->container();
        $container->prependExtensionConfig('twig', ['form_themes' => ['application.html.twig']]);

        $this->prepend($container);

        self::assertSame([['form_themes' => [PhilUxBundle::FORM_THEME]], ['form_themes' => ['application.html.twig']]], $container->getExtensionConfig('twig'));
        self::assertSame([['asset_mapper' => ['paths' => [PhilUxBundle::stylesheetDirectory() => 'phil-ux']]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(PhilUxBundle::stylesheetDirectory().'/phil-ux.css');
        self::assertStringEndsWith('/ux-css/dist', PhilUxBundle::stylesheetDirectory());
    }

    public function testTheLastValueOfTheKeyWins(): void
    {
        $off = $this->container();
        $off->loadFromExtension('phil_ux', ['form_theme' => true]);
        $off->loadFromExtension('phil_ux', ['form_theme' => false]);
        $this->prepend($off);

        $on = $this->container();
        $on->loadFromExtension('phil_ux', ['form_theme' => false]);
        $on->loadFromExtension('phil_ux', []);
        $on->loadFromExtension('phil_ux', ['form_theme' => true]);
        $this->prepend($on);

        $unset = $this->container();
        $unset->loadFromExtension('phil_ux', ['form_theme' => false]);
        $unset->loadFromExtension('phil_ux', []);
        $this->prepend($unset);

        self::assertSame([], $off->getExtensionConfig('twig'));
        self::assertSame([['form_themes' => [PhilUxBundle::FORM_THEME]]], $on->getExtensionConfig('twig'));
        self::assertSame([], $unset->getExtensionConfig('twig'));
    }

    public function testNothingIsPrependedWithoutTwigNorFrameworkBundle(): void
    {
        $bare = new ContainerBuilder();
        $bare->registerExtension($this->extension());

        $this->prepend($bare);

        self::assertSame([[], []], [$bare->getExtensionConfig('twig'), $bare->getExtensionConfig('framework')]);
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilUxBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new TwigExtension());
        $container->registerExtension(new FrameworkExtension());
        $container->registerExtension($this->extension());

        return $container;
    }

    private function prepend(ContainerBuilder $container): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $extension->prepend($container);
    }
}
