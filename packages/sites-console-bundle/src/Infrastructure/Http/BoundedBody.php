<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Request;

/**
 * The body of a request, read up to a number of bytes and never further: a declared `Content-Length` past the cap is
 * refused before any byte is read, and the stream is read to one byte past the cap whatever the header says, absent,
 * malformed or false.
 *
 * @internal
 */
final readonly class BoundedBody
{
    private function __construct()
    {
    }

    /**
     * @param positive-int $maxBytes
     *
     * @return ?string the body, or null when it is longer than `$maxBytes`
     */
    public static function read(Request $request, int $maxBytes): ?string
    {
        $declared = $request->headers->get('Content-Length');

        // A declared length past PHP_INT_MAX reads as PHP_INT_MAX, past any cap.
        if (null !== $declared && 1 === preg_match('/^\d+$/D', $declared) && (int) $declared > $maxBytes) {
            return null;
        }

        $stream = $request->getContent(true);
        $body = stream_get_contents($stream, $maxBytes + 1);

        if (false === $body || \strlen($body) > $maxBytes) {
            return null;
        }

        return $body;
    }
}
