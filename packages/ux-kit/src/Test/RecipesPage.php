<?php

declare(strict_types=1);

namespace Phil\UxKit\Test;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * Writes the web root the browser opens: every recipe of a kit rendered through TwigComponent, in clear, in dark and
 * right to left, beside its stylesheet, its Stimulus controllers, the build of Stimulus the suite pins and the files of
 * the `media` directory of its resources, the videos and text tracks the page plays, under `media/`.
 *
 * Published with `BrowserTestCase` for the browser suites of the kits built on the same controllers; it needs
 * `symfony/http-kernel`, `twig/twig` and `psr/container`, which the kit requires for development only.
 */
final readonly class RecipesPage
{
    /**
     * @var array<string, array{string, string}> The file of each mode, with its direction and its theme
     */
    private const array PAGES = [
        'index.html' => ['ltr', 'light'],
        'dark.html' => ['ltr', 'dark'],
        'rtl.html' => ['rtl', 'light'],
    ];

    /**
     * @param KernelInterface $kernel     An application where the recipes of the kit are installed
     * @param string          $kit        The directory of the kit
     * @param string          $resources  The directory of its app.js, page.html.twig and media files
     * @param string          $stylesheet The stylesheet of the page, served under its own name
     * @param string          $root       The web root to write
     * @param string          $stimulus   The file `stimulus.js` of `@hotwired/stimulus`, installed by the suite
     */
    public function __construct(
        private KernelInterface $kernel,
        private string $kit,
        private string $resources,
        private string $stylesheet,
        private string $root,
        private string $stimulus,
    ) {
    }

    /**
     * @return string The web root
     */
    public function write(): string
    {
        $controllers = $this->root.'/controllers';
        $this->directory($controllers);

        $this->copy($this->stylesheet, $this->root.'/'.basename($this->stylesheet));

        if (!is_file($this->stimulus)) {
            throw new RuntimeException('Stimulus is missing at '.$this->stimulus.": run `npm ci --ignore-scripts` in the directory of the suite's package.json.");
        }

        $this->copy($this->stimulus, $this->root.'/stimulus.js');
        $this->copy($this->resources.'/app.js', $this->root.'/app.js');

        $sources = glob($this->kit.'/*/assets/controllers/*_controller.js');

        foreach (false === $sources ? [] : $sources as $source) {
            $this->copy($source, $controllers.'/'.basename($source));
        }

        $this->copyMedia();

        $this->kernel->boot();

        $tests = $this->kernel->getContainer()->get('test.service_container');
        $twig = $tests instanceof ContainerInterface ? $tests->get('twig') : null;

        if (!$twig instanceof Environment) {
            throw new RuntimeException('Twig is not there.');
        }

        $page = file_get_contents($this->resources.'/page.html.twig');

        if (false === $page) {
            throw new RuntimeException('Cannot read '.$this->resources.'/page.html.twig');
        }

        foreach (self::PAGES as $file => [$direction, $theme]) {
            file_put_contents($this->root.'/'.$file, $twig->createTemplate($page)->render(['dir' => $direction, 'theme' => $theme]));
        }

        $this->kernel->shutdown();

        return $this->root;
    }

    /**
     * The files of the `media` directory of the resources, when it holds any, copied under `media/` of the web root.
     */
    private function copyMedia(): void
    {
        $files = glob($this->resources.'/media/*');

        if (false === $files || [] === $files) {
            return;
        }

        $media = $this->root.'/media';
        $this->directory($media);

        foreach ($files as $file) {
            $this->copy($file, $media.'/'.basename($file));
        }
    }

    /**
     * A directory and those above it, unless it is there: a failure is the warning of mkdir(), then that of the copy.
     */
    private function directory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, recursive: true);
        }
    }

    private function copy(string $from, string $to): void
    {
        if (!copy($from, $to)) {
            throw new RuntimeException('Cannot copy '.$from);
        }
    }
}
