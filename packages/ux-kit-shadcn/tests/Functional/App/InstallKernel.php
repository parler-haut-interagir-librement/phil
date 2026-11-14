<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Functional\App;

use FilesystemIterator;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpKernel\Kernel;
use ZipArchive;

/**
 * An application whose `ux:install` installs the components and the controllers of recipes in the subdirectory `ui`,
 * and downloads the kit from GitHub through an HTTP client that answers with the archive of this package: the kit as
 * `.gitattributes` exports it, its manifest, its icon and its recipes.
 */
final class InstallKernel extends Kernel
{
    use MicroKernelTrait;

    public const string KIT = 'github.com/parler-haut-interagir-librement/ux-kit-shadcn';

    /**
     * The bundles the kernel registers, listed in `install-bundles.php` as an application lists them.
     */
    private function getBundlesPath(): string
    {
        return __DIR__.'/install-bundles.php';
    }

    #[Override]
    public function getProjectDir(): string
    {
        return __DIR__;
    }

    #[Override]
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-shadcn-install/'.hash('xxh128', __DIR__).'/cache';
    }

    #[Override]
    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/phil-ux-kit-shadcn-install/'.hash('xxh128', __DIR__).'/log';
    }

    /**
     * The archive GitHub would serve: the files of the kit under one root directory.
     */
    public static function archive(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'phil-ux-kit-shadcn-archive');
        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::OVERWRITE)) {
            throw new RuntimeException('The archive of the kit cannot be written.');
        }

        $kit = TestKernel::KIT_DIR;
        $zip->addFile($kit.'/manifest.json', 'ux-kit-shadcn-main/manifest.json');
        $zip->addFile($kit.'/icon.svg', 'ux-kit-shadcn-main/icon.svg');

        $manifests = glob($kit.'/*/manifest.json');

        foreach (false === $manifests ? [] : $manifests as $manifest) {
            $recipe = \dirname($manifest);
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($recipe, FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                \assert($file instanceof SplFileInfo);
                $zip->addFile($file->getPathname(), 'ux-kit-shadcn-main/'.basename($recipe).substr($file->getPathname(), \strlen($recipe)));
            }
        }

        $zip->close();
        $archive = (string) file_get_contents($path);
        unlink($path);

        return $archive;
    }

    /**
     * The client the GitHub registry downloads the kit through: each request is answered with the archive.
     */
    public static function httpClient(): MockHttpClient
    {
        return new MockHttpClient(static fn (): MockResponse => new MockResponse(self::archive()));
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
        ]);
        $container->extension('twig_component', ['anonymous_template_directory' => 'components/', 'defaults' => []]);
        $container->extension('ux_toolkit', [
            'components' => ['subdirectory' => 'ui'],
            'controllers' => ['subdirectory' => 'ui'],
        ]);
        $container->services()
            ->set('http_client', MockHttpClient::class)
            ->factory([self::class, 'httpClient'])
            ->public();
    }
}
