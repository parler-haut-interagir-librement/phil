<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Http;

use Phil\CoursesBundle\Application\ContentUrls;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The frame of the SCORM run time, served on the content origin only: a page that the activity page frames, that holds
 * the run-time API of the session — `API` for SCORM 1.2, `API_1484_11` for SCORM 2004 — and frames the SCO, on the same
 * origin, so that the SCO finds the API in its parent window as SCORM asks. The API answers the SCO at once from the
 * values it keeps, and hands the commits to the activity page by `postMessage()`, to the application's origin alone.
 *
 * The page and its scripts go out with a content security policy that runs only these scripts, frames only the content
 * origin, and lets only the application's origin frame the page.
 */
final readonly class ScormFrameController
{
    public const string ROUTE = 'phil_courses_scorm_frame';

    public const string PATH = '_phil/scorm';

    /** The scripts of the frame, by the path they are served at under `PATH`. */
    public const array SCRIPTS = [
        'scorm_frame.js' => 'scorm_frame.js',
        'lib/cmi.js' => 'lib/cmi.js',
        'lib/api.js' => 'lib/api.js',
    ];

    private string $assets;

    /**
     * @param ?string $application the origin of the application, which alone frames the page and receives its messages
     */
    public function __construct(
        private ContentUrls $urls,
        private ?string $application,
        ?string $assets = null,
    ) {
        $this->assets = $assets ?? \dirname(__DIR__, 3).'/assets/src';
    }

    public function serve(Request $request, string $file): Response
    {
        if (null === $this->urls->origin() || $request->getSchemeAndHttpHost() !== $this->urls->origin() || null === $this->application) {
            throw new NotFoundHttpException('The frame of the SCORM run time is served from the content origin only.');
        }

        if ('frame.html' === $file) {
            return $this->response($this->page(), 'text/html; charset=UTF-8');
        }

        $script = self::SCRIPTS[$file] ?? null;
        $contents = null === $script ? false : @file_get_contents($this->assets.'/'.$script);

        if (false === $contents) {
            throw new NotFoundHttpException('No such file.');
        }

        return $this->response($contents, 'text/javascript; charset=UTF-8');
    }

    private function page(): string
    {
        $parent = htmlspecialchars((string) $this->application, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return <<<HTML
            <!doctype html>
            <html lang="en" data-parent="{$parent}">
            <head>
            <meta charset="utf-8">
            <title>SCORM</title>
            <style>html,body{margin:0;height:100%;overflow:hidden}iframe{display:block;border:0;width:100%;height:100%}</style>
            <script type="module" src="scorm_frame.js"></script>
            </head>
            <body></body>
            </html>
            HTML;
    }

    private function response(string $contents, string $type): Response
    {
        return new Response($contents, Response::HTTP_OK, [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-cache',
            'Content-Security-Policy' => implode('; ', [
                "default-src 'none'",
                "script-src 'self'",
                "style-src 'unsafe-inline'",
                "frame-src 'self'",
                "base-uri 'none'",
                "form-action 'none'",
                'frame-ancestors '.$this->application,
            ]),
        ]);
    }
}
