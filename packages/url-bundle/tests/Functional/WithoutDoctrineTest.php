<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Functional;

use Composer\Autoload\ClassLoader;
use Doctrine\DBAL\Types\Type;
use FilesystemIterator;
use Override;
use Phil\UrlBundle\Infrastructure\Doctrine\UrlColumnType;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfTypeValidator;
use Phil\UrlBundle\PhilUrlBundle;
use Phil\UrlBundle\Tests\Functional\WithoutDoctrine\KernelWithoutDoctrine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * An application that never installed `doctrine/dbal`: each test runs in its own process, where the class loader no
 * longer finds anything under `Doctrine\`, as if the package were absent.
 */
#[CoversClass(PhilUrlBundle::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WithoutDoctrineTest extends TestCase
{
    private ?KernelWithoutDoctrine $kernel = null;

    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        $this->hideDoctrine();
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!$this->kernel instanceof KernelWithoutDoctrine || !is_dir($this->kernel->varDir())) {
            return;
        }

        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->kernel->varDir(), FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } elseif ($entry instanceof SplFileInfo) {
                unlink($entry->getPathname());
            }
        }

        rmdir($this->kernel->varDir());
    }

    public function testTheKernelBootsWithoutDoctrine(): void
    {
        $kernel = new KernelWithoutDoctrine();
        $this->kernel = $kernel;
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            self::assertFalse(class_exists(Type::class));
            self::assertFalse($container->getParameter('phil_url.allow_localhost'));

            $formFactory = $container->get('test.form.factory');
            self::assertInstanceOf(FormFactoryInterface::class, $formFactory);
            $form = $formFactory->create(TypedUrlType::class);
            $form->submit('https://ph-il.ca/');

            self::assertTrue($form->isValid());
            self::assertSame('https://ph-il.ca/', $form->getViewData());
            self::assertTrue(class_exists(UrlOfTypeValidator::class));
        } finally {
            $kernel->shutdown();
        }
    }

    public function testTheKernelBootsWithLocalhostAllowedAndTheColumnTypeTurnedOff(): void
    {
        $kernel = new KernelWithoutDoctrine(['allow_localhost' => true, 'column_type' => false]);
        $this->kernel = $kernel;
        $kernel->boot();
        $kernel->shutdown();

        self::assertFalse(class_exists(Type::class));
        self::assertFalse(class_exists(UrlColumnType::class, false));
    }

    public function testAColumnTypeRequiredWithoutDbalIsRefusedAtBuild(): void
    {
        $kernel = new KernelWithoutDoctrine(['column_type' => true]);
        $this->kernel = $kernel;
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The column type phil_url needs the package "doctrine/dbal": run "composer require doctrine/dbal" and register DoctrineBundle, or set "phil_url.column_type" to null or false.');

        $kernel->boot();
    }

    /**
     * Puts each Composer class loader behind a filter that answers nothing for `Doctrine\`.
     */
    private function hideDoctrine(): void
    {
        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $loader->unregister();
            spl_autoload_register(static function (string $class) use ($loader): void {
                if (!str_starts_with($class, 'Doctrine\\')) {
                    $loader->loadClass($class);
                }
            }, true, true);
        }
    }
}
