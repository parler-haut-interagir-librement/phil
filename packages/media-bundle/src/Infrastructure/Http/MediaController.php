<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Phil\MediaBundle\Application\MediaLibrary;
use Phil\MediaBundle\Application\UrlSigner;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Domain\Model\Media;
use Phil\MediaBundle\Domain\Repository\MediaRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves a medium's file and its formats at signed URLs: `/{id}/{version}/original`,
 * `/{id}/{version}/{format}.{type}` and `/{id}/{version}/{format}@{width}.{type}`; a format missing from the storage is
 * rendered there and then. The version is in the path, so a response is kept for a year, or until its URL
 * expires; a signature that does not match, a URL that expired, an old version or an unknown format is a `404`.
 *
 * A file is shown in the browser only when its type cannot carry a script on the site's origin — a raster image or a
 * PDF —; any other file, an HTML page among them, is downloaded as `application/octet-stream`. Every response forbids
 * type sniffing, and every one but a PDF is sandboxed.
 *
 * An SVG image is served as `image/svg+xml` when the library has an SVG purifier: purified again the first time it is
 * served, it shows in an `<img>`, where an SVG never runs a script, and stays an attachment, sandboxed, when it is
 * opened directly. Without a purifier, it is downloaded as `application/octet-stream` like any other file.
 *
 * When phil/browser-security-bundle is installed, its policy of user content decides instead: the types it shows
 * inline are shown, every other file is downloaded as `application/octet-stream` — a purified SVG image keeps its
 * type, as an attachment —, and every response carries its headers — its own `Content-Security-Policy`, `nosniff`,
 * the disposition, `Cross-Origin-Resource-Policy`, `Referrer-Policy: no-referrer`.
 */
final readonly class MediaController
{
    /**
     * The media types a browser is let show: none of them runs a script on the site's origin.
     */
    public const array INLINE_TYPES = ['image/avif', 'image/gif', 'image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    private const int YEAR = 31_536_000;

    public function __construct(
        private MediaRepository $media,
        private MediaLibrary $library,
        private UrlSigner $signer,
        private ClockInterface $clock,
        private ?UserContentPolicy $userContent = null,
    ) {
    }

    public function original(Request $request, string $id, int $version): Response
    {
        $media = $this->found($request, $id, $version);
        $file = $media->file();

        try {
            $svg = $this->library->purifiedSvg($media);
        } catch (InvalidMedia $invalidMedia) {
            throw new NotFoundHttpException($invalidMedia->getMessage(), $invalidMedia);
        }

        if (null !== $svg) {
            $disposition = \sprintf('attachment; filename="%s"', addcslashes($file->safeFilename(), '"\\'));

            return $this->response($request, $svg, MediaLibrary::SVG, ['Content-Disposition' => $disposition], $file->safeFilename());
        }

        $inline = $this->userContent?->isInline($file->mimeType) ?? \in_array($file->mimeType, self::INLINE_TYPES, true);
        $disposition = \sprintf('%s; filename="%s"', $inline ? 'inline' : 'attachment', addcslashes($file->safeFilename(), '"\\'));

        return $this->response($request, $this->library->original($media), $inline ? $file->mimeType : 'application/octet-stream', ['Content-Disposition' => $disposition], $file->safeFilename());
    }

    public function format(Request $request, string $id, int $version, string $format, string $type, ?int $width = null): Response
    {
        $media = $this->found($request, $id, $version);

        try {
            return $this->response($request, $this->library->rendered($media, $format, $type, $width), MediaLibrary::TYPES[$type] ?? 'application/octet-stream');
        } catch (InvalidMedia $invalidMedia) {
            throw new NotFoundHttpException($invalidMedia->getMessage(), $invalidMedia);
        }
    }

    private function found(Request $request, string $id, int $version): Media
    {
        if (!$this->signer->verify($request->getPathInfo(), $this->expires($request), $request->query->getString('signature'), $this->now())) {
            throw new NotFoundHttpException('This media URL is not signed, or expired.');
        }

        $media = $this->media->get($id);

        if (!$media instanceof Media || $media->version() !== $version) {
            throw new NotFoundHttpException('There is no such medium, or no such version.');
        }

        return $media;
    }

    /**
     * @param array<string, string> $headers
     * @param string|null           $filename the name the browser saves the file under, with the policy of user content
     */
    private function response(Request $request, string $contents, string $mimeType, array $headers = [], ?string $filename = null): Response
    {
        $headers = ['Content-Type' => $mimeType, 'X-Content-Type-Options' => 'nosniff', ...$headers];

        // A browser's PDF viewer does not open a sandboxed document; a PDF's own scripts never reach the origin.
        if ('application/pdf' !== $mimeType) {
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        $response = new Response($contents, Response::HTTP_OK, $headers);

        if ($this->userContent instanceof UserContentPolicy) {
            $this->userContent->apply($response, $filename);
        }

        $expires = $this->expires($request);
        $response->setPublic();
        // A URL that expires is not kept past that moment, by a browser or by a shared cache.
        $response->setMaxAge(null === $expires ? self::YEAR : max(0, min(self::YEAR, $expires - $this->now())));
        $response->headers->addCacheControlDirective('immutable');

        return $response;
    }

    private function expires(Request $request): ?int
    {
        return $request->query->has('expires') ? $request->query->getInt('expires') : null;
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
