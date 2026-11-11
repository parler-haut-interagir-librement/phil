<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Infrastructure\Http\Document;

use Override;
use Phil\WellKnownBundle\Application\Contract\WellKnownDocument;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * `/.well-known/change-password` (W3C, A Well-Known URL for Changing Passwords): a `302` to the page where a user
 * changes their password — a route of the application, with its parameters, or a URL.
 */
final readonly class ChangePasswordDocument implements WellKnownDocument
{
    /**
     * @param array<string, string> $parameters
     */
    public function __construct(
        private string $target,
        private ?UrlGeneratorInterface $urls = null,
        private array $parameters = [],
    ) {
    }

    #[Override]
    public function serve(Request $request): RedirectResponse
    {
        return new RedirectResponse($this->urls?->generate($this->target, $this->parameters) ?? $this->target, Response::HTTP_FOUND);
    }
}
