<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\Http;

use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * Answers a refusal `429 Too Many Requests`, with `Retry-After` and the rate-limit headers of the limiter that
 * refused: an RFC 9457 problem as `application/problem+json` to a request that asks for JSON, the bundle's Twig page to
 * the others. Without Twig, Symfony's own error page answers, with the same headers.
 *
 * A request asks for JSON when its format is JSON, or when its first acceptable type is.
 */
final readonly class TooManyRequestsResponder
{
    public const string TITLE = 'Too many requests';

    public const string DETAIL = 'Too many requests. Try again in %seconds% seconds.';

    public const string DOMAIN = 'phil_rate_limit';

    public function __construct(
        private ?Environment $twig = null,
        private string $template = '@PhilRateLimit/too_many_requests.html.twig',
        private ?TranslatorInterface $translator = null,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        if (!$exception instanceof TooManyRequests) {
            return;
        }

        $request = $event->getRequest();
        $seconds = $exception->decision->retryAfter;
        $title = $this->translated(self::TITLE, []);
        $detail = $this->translated(self::DETAIL, ['%seconds%' => (string) $seconds]);

        if ($this->wantsJson($request)) {
            $response = new JsonResponse(['type' => 'about:blank', 'title' => $title, 'status' => Response::HTTP_TOO_MANY_REQUESTS, 'detail' => $detail, 'retry_after' => $seconds], Response::HTTP_TOO_MANY_REQUESTS, ['Content-Type' => 'application/problem+json']);
        } elseif ($this->twig instanceof Environment) {
            $response = new Response($this->twig->render($this->template, ['locale' => $request->getLocale(), 'title' => $title, 'detail' => $detail, 'retry_after' => $seconds, 'limiter' => $exception->decision->limiter]), Response::HTTP_TOO_MANY_REQUESTS);
        } else {
            return;
        }

        $response->headers->add($exception->getHeaders());
        $event->setResponse($response);
    }

    private function wantsJson(Request $request): bool
    {
        $format = $request->getRequestFormat(null);

        if (null !== $format) {
            return \in_array($format, ['json', 'jsonld', 'jsonapi', 'problem'], true);
        }

        $first = $request->getAcceptableContentTypes()[0] ?? '';

        return 'application/json' === $first || str_ends_with($first, '+json');
    }

    /**
     * @param array<string, string> $parameters
     */
    private function translated(string $message, array $parameters): string
    {
        return $this->translator?->trans($message, $parameters, self::DOMAIN) ?? strtr($message, $parameters);
    }
}
