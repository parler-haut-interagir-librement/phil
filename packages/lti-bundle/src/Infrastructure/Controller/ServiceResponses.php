<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Controller;

use JsonException;
use Phil\Lti\Exception\AccessDenied;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The answers of the services of the platform: JSON of their media type, errors with the `WWW-Authenticate` of RFC 6750.
 *
 * @internal
 */
final class ServiceResponses
{
    public const int MAX_BODY = 65_536;

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, string>   $headers
     */
    public static function json(array $data, string $mediaType, int $status = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return new JsonResponse(json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION), $status, ['Content-Type' => $mediaType, 'Cache-Control' => 'no-store'] + $headers, true);
    }

    public static function denied(AccessDenied $denied): Response
    {
        return $denied->insufficientScope
            ? new Response('', Response::HTTP_FORBIDDEN, ['WWW-Authenticate' => 'Bearer error="insufficient_scope"'])
            : new Response('', Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer error="invalid_token"']);
    }

    public static function error(string $message, int $status = Response::HTTP_BAD_REQUEST): Response
    {
        return new Response($message, $status, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function notFound(): Response
    {
        return new Response('', Response::HTTP_NOT_FOUND, ['Cache-Control' => 'no-store']);
    }

    /**
     * The JSON object of the body, 64 KiB at most; null when it is none.
     *
     * @return array<array-key, mixed>|null
     */
    public static function body(Request $request): ?array
    {
        $content = $request->getContent();

        if (\strlen($content) > self::MAX_BODY) {
            return null;
        }

        try {
            $data = json_decode($content, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return \is_array($data) && !array_is_list($data) ? $data : null;
    }

    public static function intQuery(Request $request, string $name): ?int
    {
        $value = $request->query->get($name);

        return \is_string($value) && 1 === preg_match('/^\d{1,9}$/D', $value) ? (int) $value : null;
    }

    public static function stringQuery(Request $request, string $name): ?string
    {
        $value = $request->query->get($name);

        return \is_string($value) && '' !== $value && \strlen($value) <= 255 ? $value : null;
    }
}
