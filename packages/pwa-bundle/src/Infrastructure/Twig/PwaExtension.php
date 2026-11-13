<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Infrastructure\Twig;

use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Phil\PwaBundle\Infrastructure\Http\PwaController;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * What the layout of a site uses, declared by attributes and run as a Twig runtime:
 *
 * - `phil_pwa_head()`, the `<link rel="manifest">` and the `<meta name="theme-color">` of the `<head>`;
 * - `phil_pwa_manifest_path()` and `phil_pwa_service_worker_path()`, the addresses of the manifest and the worker;
 * - `phil_pwa_scope()`, the scope the worker is registered with.
 */
final readonly class PwaExtension
{
    public function __construct(
        private Manifest $manifest,
        private UrlGeneratorInterface $urls,
        private string $scope,
    ) {
    }

    #[AsTwigFunction(name: 'phil_pwa_head', isSafe: ['html'])]
    public function head(): string
    {
        $html = '<link rel="manifest" href="'.$this->escape($this->manifestPath()).'">';

        if (null !== $this->manifest->themeColor) {
            $html .= "\n".'<meta name="theme-color" content="'.$this->escape($this->manifest->themeColor).'">';
        }

        return $html;
    }

    #[AsTwigFunction(name: 'phil_pwa_manifest_path')]
    public function manifestPath(): string
    {
        return $this->urls->generate(PwaController::MANIFEST_ROUTE);
    }

    #[AsTwigFunction(name: 'phil_pwa_service_worker_path')]
    public function serviceWorkerPath(): string
    {
        return $this->urls->generate(PwaController::SERVICE_WORKER_ROUTE);
    }

    #[AsTwigFunction(name: 'phil_pwa_scope')]
    public function scope(): string
    {
        return $this->scope;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
