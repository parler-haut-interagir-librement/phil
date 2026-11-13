<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Infrastructure\Http;

use Phil\PwaBundle\Application\ManifestDocument;
use Phil\PwaBundle\Application\ServiceWorkerScript;
use Phil\PwaBundle\Domain\ValueObject\Manifest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * The manifest, the service worker and the offline page. None reads the session or sets a cookie.
 *
 * The service worker is answered with `Cache-Control: no-cache`, so the browser checks it on every visit, and with
 * `Service-Worker-Allowed` only when its scope is wider than the directory of its address.
 */
final readonly class PwaController
{
    public const string MANIFEST_ROUTE = 'phil_pwa_manifest';

    public const string SERVICE_WORKER_ROUTE = 'phil_pwa_service_worker';

    public const string OFFLINE_ROUTE = 'phil_pwa_offline';

    public const string OFFLINE_TEMPLATE = '@PhilPwa/offline.html.twig';

    public function __construct(
        private Manifest $manifest,
        private ManifestDocument $document,
        private ServiceWorkerScript $script,
        private string $scope,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private string $layout,
        private int $maxAge = 600,
    ) {
    }

    public function manifest(): Response
    {
        return $this->respond($this->document->render($this->manifest), 'application/manifest+json', $this->cacheControl());
    }

    public function serviceWorker(Request $request): Response
    {
        $response = $this->respond($this->script->render($this->urls->generate(self::OFFLINE_ROUTE)), 'text/javascript; charset=UTF-8', 'no-cache');
        $path = $request->getBaseUrl().$request->getPathInfo();

        if (!str_starts_with($this->scope, substr($path, 0, (int) strrpos($path, '/') + 1))) {
            $response->headers->set('Service-Worker-Allowed', $this->scope);
        }

        return $response;
    }

    public function offline(): Response
    {
        return $this->respond($this->twig->render(self::OFFLINE_TEMPLATE, ['layout' => $this->layout, 'manifest' => $this->manifest]), 'text/html; charset=UTF-8', $this->cacheControl());
    }

    private function cacheControl(): string
    {
        return 0 === $this->maxAge ? 'no-cache' : 'public, max-age='.$this->maxAge;
    }

    private function respond(string $content, string $type, string $cacheControl): Response
    {
        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => $type,
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
