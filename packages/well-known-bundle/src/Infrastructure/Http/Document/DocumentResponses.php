<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Symfony\Component\HttpFoundation\Response;

/**
 * The answers of the well-known documents: their body, their media type, and `Access-Control-Allow-Origin: *` for the
 * documents browsers of other sites read, such as WebFinger and NodeInfo.
 */
final class DocumentResponses
{
    public const int FLAGS = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

    public static function json(mixed $data, string $contentType, bool $cors): Response
    {
        return self::body(json_encode($data, self::FLAGS), $contentType, $cors);
    }

    public static function body(string $body, string $contentType, bool $cors): Response
    {
        $response = new Response($body, Response::HTTP_OK, ['Content-Type' => $contentType]);

        if ($cors) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        return $response;
    }
}
