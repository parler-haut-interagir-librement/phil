<?php

declare(strict_types=1);

namespace Phil\DocumentImportBundle\Tests\Functional\App;

use Override;
use Phil\DocumentImportBundle\Domain\Event\DocumentImported;
use Phil\DocumentImportBundle\Domain\Event\DocumentImportFailed;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * An application as small as the bundle needs: FrameworkBundle with Messenger and an in-memory transport `imports`,
 * TwigBundle, phil/upload-scan-bundle with its fake scanner, the bundle with its imports on a worker, the files waiting
 * in a directory of the test, and a listener that keeps what the imports dispatch.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return $this->root().'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return $this->root().'/log';
    }

    public function pendingDirectory(): string
    {
        return $this->root().'/pending';
    }

    /**
     * A directory of its own for each version of the configuration code, which the container does not watch.
     */
    private function root(): string
    {
        $source = \dirname(__DIR__, 3).'/src';

        return sys_get_temp_dir().'/phil-document-import-bundle-test/'.hash('xxh128', __DIR__.hash_file('xxh128', $source.'/PhilDocumentImportBundle.php').hash_file('xxh128', $source.'/Infrastructure/DependencyInjection/DocumentImportServices.php'));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'messenger' => ['transports' => ['imports' => 'in-memory://']],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        // An application requires FrameworkBundle, and with it the event dispatcher, in production; installed on its
        // own, the bundle has them in development only, which the container takes as absent.
        $services->set('event_dispatcher', EventDispatcher::class)->public()->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);
        $services->alias(EventDispatcherInterface::class, 'event_dispatcher');
        $services->alias(PsrEventDispatcherInterface::class, 'event_dispatcher');
        $services->set(RecordedImports::class)->public()
            ->tag('kernel.event_listener', ['event' => DocumentImported::class])
            ->tag('kernel.event_listener', ['event' => DocumentImportFailed::class]);

        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('phil_upload_scan', ['scanner' => 'fake', 'quarantine' => ['enabled' => false]]);
        $container->extension('phil_document_import', [
            'formats' => ['import' => ['docx', 'odt', 'markdown', 'html']],
            'styles' => ['Author Note' => 'omit'],
            'report' => ['title' => 'Left out'],
            'async' => ['transport' => 'imports', 'directory' => $this->pendingDirectory()],
        ]);
    }
}
