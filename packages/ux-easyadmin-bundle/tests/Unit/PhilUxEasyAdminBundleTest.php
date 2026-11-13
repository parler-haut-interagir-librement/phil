<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Unit;

use Phil\UxEasyAdminBundle\Infrastructure\EasyAdmin\ThemeStylesheet;
use Phil\UxEasyAdminBundle\PhilUxEasyAdminBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\FrameworkExtension;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[CoversClass(PhilUxEasyAdminBundle::class)]
final class PhilUxEasyAdminBundleTest extends TestCase
{
    public function testTheListenerIsRegisteredAfterEasyAdminsRouter(): void
    {
        $extension = $this->extension();
        $container = new ContainerBuilder();
        $extension->load([], $container);

        self::assertSame('phil_ux_easy_admin', $extension->getAlias());
        $definition = $container->getDefinition('phil_ux_easy_admin.theme_stylesheet');
        self::assertSame(ThemeStylesheet::class, $definition->getClass());
        self::assertSame([PhilUxEasyAdminBundle::STYLESHEET], $definition->getArguments());
        self::assertSame([['event' => RequestEvent::class, 'priority' => -8]], $definition->getTag('kernel.event_listener'));
    }

    public function testTheThemeCanBeLeftToTheDashboards(): void
    {
        $container = new ContainerBuilder();
        $this->extension()->load([['theme' => false]], $container);

        self::assertSame(['service_container'], array_keys($container->getDefinitions()));
    }

    public function testTheKeyTakesABoolean(): void
    {
        $this->expectException(InvalidTypeException::class);

        $this->extension()->load([['theme' => 'no']], new ContainerBuilder());
    }

    public function testTheStylesheetIsMapped(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new FrameworkExtension());

        $extension->prepend($container);

        self::assertSame([['asset_mapper' => ['paths' => [PhilUxEasyAdminBundle::STYLES_DIR => 'phil-ux-easyadmin']]]], $container->getExtensionConfig('framework'));
        self::assertFileExists(PhilUxEasyAdminBundle::STYLES_DIR.'/phil-ux-easyadmin.css');

        $bare = new ContainerBuilder();
        $extension->prepend($bare);
        self::assertSame([], $bare->getExtensionConfig('framework'));
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilUxEasyAdminBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
