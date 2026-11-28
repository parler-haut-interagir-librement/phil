<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\Http;

use Phil\Shifts\Exception\ShiftRefused;
use Phil\ShiftsBundle\Application\Exception\RequestRefused;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * What the bundle's controllers share: Twig with the time zone of the pages and the CSRF token, the redirections after
 * a form, and the status of a refusal — 403 for a form from elsewhere, 404 for a shift or an offer unknown, 422 for
 * the others.
 */
final readonly class Pages
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private ShiftsGuard $guard,
        private string $timeZone,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render('@PhilShifts/'.$template, ['zone' => $this->timeZone, 'token' => $this->guard->token(), 'refused' => null, ...$context]), $status);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function redirect(string $route, array $parameters = []): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate($route, $parameters), Response::HTTP_SEE_OTHER);
    }

    public function status(ShiftRefused|RequestRefused $refused): int
    {
        return match ($refused->reason) {
            RequestRefused::UNTRUSTED_REQUEST => Response::HTTP_FORBIDDEN,
            RequestRefused::UNKNOWN_SHIFT, RequestRefused::UNKNOWN_OFFER => Response::HTTP_NOT_FOUND,
            default => Response::HTTP_UNPROCESSABLE_ENTITY,
        };
    }

    public function guard(): ShiftsGuard
    {
        return $this->guard;
    }
}
