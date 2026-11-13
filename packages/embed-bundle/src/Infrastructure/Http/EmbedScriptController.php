<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\BrowserSecurityContext;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The script other sites load: the `<phil-embed>` element and the protocol's module, under stable addresses, whatever
 * AssetMapper names the bundle's files. Only these two files are served. They are modules loaded across origins, so
 * the answer allows any origin to read them, and allows itself to be loaded by another site.
 */
final readonly class EmbedScriptController
{
    public const array FILES = [
        'phil-embed.js' => 'phil-embed.js',
        'lib/message.js' => 'lib/message.js',
    ];

    public function __construct(
        private string $directory,
        private ?BrowserSecurityContext $browserSecurity = null,
        private int $maxAge = 86_400,
    ) {
    }

    public function __invoke(Request $request, string $file): Response
    {
        $path = isset(self::FILES[$file]) ? $this->directory.'/'.self::FILES[$file] : null;
        $content = null === $path ? false : file_get_contents($path);

        if (false === $content) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $this->browserSecurity?->override(new ProtectionOverride(replace: ['Cross-Origin-Resource-Policy' => 'cross-origin']));
        $response = new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Access-Control-Allow-Origin' => '*',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setPublic();
        $response->setMaxAge($this->maxAge);
        $response->setEtag(hash('xxh128', $content));
        $response->isNotModified($request);

        return $response;
    }
}
