<?php

declare(strict_types=1);

namespace Phil\VideoBundle\Infrastructure\Http;

use Phil\TimedText\Track;
use Phil\TimedText\TranscriptFormat;
use Symfony\Component\HttpFoundation\Response;

/**
 * A text track served by a controller of the application: the WebVTT a `<track>` element fetches, written from a
 * phil/timed-text `Track`, never sniffed as another type.
 */
final class TextTrackResponse
{
    private function __construct()
    {
    }

    public static function webVtt(Track $track): Response
    {
        return new Response(TranscriptFormat::WebVtt->writer()->write($track), Response::HTTP_OK, [
            'Content-Type' => 'text/vtt; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
