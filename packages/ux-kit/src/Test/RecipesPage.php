<?php

declare(strict_types=1);

namespace Phil\UxKit\Test;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

/**
 * Writes the web root the browser opens: every recipe of a kit rendered through TwigComponent, in clear, in dark and
 * right to left, beside its stylesheet, its Stimulus controllers, the build of Stimulus the suite pins, the `page.css`
 * of its resources, when it has one, and the files of the `media` directory of its resources, the videos and text tracks
 * the page plays, under `media/`.
 *
 * Each mode is also written under a strict Content Security Policy, `strict.html`, `strict-dark.html` and
 * `strict-rtl.html`: the policy first in the head, in a `meta` element, with nothing inline allowed but the inline
 * scripts the page itself runs, by a nonce of its own, and a watcher, `strict.js`, that lists in `window.violations` whatever the
 * browser refuses. The template reads `dir`, `theme` and `strict`, true on those pages.
 *
 * Published with `BrowserTestCase` for the browser suites of the kits built on the same controllers; it needs
 * `symfony/http-kernel`, `twig/twig` and `psr/container`, which the kit requires for development only.
 */
final readonly class RecipesPage
{
    /**
     * The strict policy of the pages, that of a site sending nothing inline: no inline script but those of the nonce in
     * place of `%s`, no inline style, images of the site or inline, every other resource of the site alone.
     */
    public const string POLICY = "default-src 'self'; script-src 'self'%s; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'";

    /**
     * @var array<string, array{string, string, bool}> The file of each mode, with its direction, its theme and whether it is under the strict policy
     */
    private const array PAGES = [
        'index.html' => ['ltr', 'light', false],
        'dark.html' => ['ltr', 'dark', false],
        'rtl.html' => ['rtl', 'light', false],
        'strict.html' => ['ltr', 'light', true],
        'strict-dark.html' => ['ltr', 'dark', true],
        'strict-rtl.html' => ['rtl', 'light', true],
    ];

    /**
     * The types of the inline scripts the browser runs, which the policy allows by the nonce of the page; a data block,
     * such as JSON-LD, runs nothing and needs none.
     */
    private const array RUN = ['module', 'importmap', 'text/javascript', 'application/javascript'];

    /**
     * What the browser refuses under the strict policy, listed in `window.violations` before anything of the body runs.
     */
    private const string WATCHER = <<<'JS'
        window.violations = [];
        document.addEventListener('securitypolicyviolation', (event) => {
            window.violations.push(`${event.effectiveDirective} ${event.blockedURI || 'inline'} ${event.sourceFile}:${event.lineNumber}`);
        });

        JS;

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

        if (is_file($this->resources.'/page.css')) {
            $this->copy($this->resources.'/page.css', $this->root.'/page.css');
        }

        file_put_contents($this->root.'/strict.js', self::WATCHER);

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

        foreach (self::PAGES as $file => [$direction, $theme, $strict]) {
            $html = $twig->createTemplate($page)->render(['dir' => $direction, 'theme' => $theme, 'strict' => $strict]);
            file_put_contents($this->root.'/'.$file, $strict ? $this->strict($html) : $html);
        }

        $this->kernel->shutdown();

        return $this->root;
    }

    /**
     * A page under the strict policy: the policy, the inline scripts it runs allowed by a nonce of its own, then the
     * watcher, first in its head, or before all of it when it has none.
     */
    private function strict(string $html): string
    {
        $nonce = implode('', array_map(static fn (): string => dechex(random_int(0, 15)), range(1, 32)));
        $allowed = false;
        $marked = preg_replace_callback('#<script\b([^>]*)>(.*?)</script>#s', static function (array $script) use ($nonce, &$allowed): string {
            preg_match('#\stype=["\']?([^"\'\s>]+)#i', $script[1], $type);

            if ('' === $script[2] || 1 === preg_match('#\ssrc=#i', $script[1]) || !\in_array(strtolower($type[1] ?? 'text/javascript'), self::RUN, true)) {
                return $script[0];
            }

            $allowed = true;

            return '<script nonce="'.$nonce.'"'.$script[1].'>'.$script[2].'</script>';
        }, $html) ?? $html;

        $policy = '<meta http-equiv="Content-Security-Policy" content="'.\sprintf(self::POLICY, $allowed ? " 'nonce-".$nonce."'" : '').'"><script src="strict.js"></script>';
        $head = preg_replace('#<head\b[^>]*>#i', '$0'.$policy, $marked, 1, $count);

        return 1 === $count && \is_string($head) ? $head : $policy.$marked;
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
