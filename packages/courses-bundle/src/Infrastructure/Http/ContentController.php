<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use Phil\CoursesBundle\Application\ContentUrls;
use Phil\CoursesBundle\Application\Contract\ContentStorage;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackagePath;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves the files of the imported packages, on the content origin only: a request to any other host is not found,
 * so that a package's scripts never run on the application's origin, with its cookies. Each file goes out with the
 * media type of its extension — an unknown one as a download —, `nosniff`, no referrer, and a content security policy
 * that lets the content run its own scripts and talk to the application's xAPI endpoint, and be framed by the
 * application, or by the frame of the SCORM run time on the content origin itself.
 */
final readonly class ContentController
{
    public const string ROUTE = 'phil_courses_content';

    private const array TYPES = [
        'html' => 'text/html; charset=UTF-8', 'htm' => 'text/html; charset=UTF-8', 'xhtml' => 'application/xhtml+xml',
        'js' => 'text/javascript; charset=UTF-8', 'mjs' => 'text/javascript; charset=UTF-8', 'css' => 'text/css; charset=UTF-8',
        'json' => 'application/json', 'xml' => 'application/xml', 'txt' => 'text/plain; charset=UTF-8', 'vtt' => 'text/vtt',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'avif' => 'image/avif',
        'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav', 'm4a' => 'audio/mp4',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'pdf' => 'application/pdf',
    ];

    /**
     * @param ?string $frameAncestors the origin of the application, which alone may frame the content
     * @param ?string $connectTo      the origin of the xAPI endpoint the content talks to
     */
    public function __construct(
        private ContentStorage $storage,
        private ContentUrls $urls,
        private ?string $frameAncestors = null,
        private ?string $connectTo = null,
    ) {
    }

    public function serve(Request $request, string $path): Response
    {
        if (null === $this->urls->origin() || $request->getSchemeAndHttpHost() !== $this->urls->origin()) {
            throw new NotFoundHttpException('The content is served from the content origin only.');
        }

        try {
            $contents = $this->storage->read(PackagePath::check($path));
        } catch (PackageRefused $packageRefused) {
            throw new NotFoundHttpException('No such file.', $packageRefused);
        }

        if (null === $contents) {
            throw new NotFoundHttpException('No such file.');
        }

        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));
        $type = self::TYPES[$extension] ?? null;
        $response = new Response($contents, Response::HTTP_OK, [
            'Content-Type' => $type ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => $this->policy(),
            'Cache-Control' => 'private, max-age=300',
        ]);

        if (null === $type) {
            $response->headers->set('Content-Disposition', 'attachment');
        }

        return $response;
    }

    private function policy(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "font-src 'self' data:",
            "connect-src 'self'".(null === $this->connectTo ? '' : ' '.$this->connectTo),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'frame-ancestors '.(null === $this->frameAncestors ? "'none'" : "'self' ".$this->frameAncestors),
        ]);
    }
}
