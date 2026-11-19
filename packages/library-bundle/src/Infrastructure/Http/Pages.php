<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

/**
 * What the pages share: their templates, rendered in the layout the configuration names, never cached by a shared
 * cache, and the role a staff page asks for — refused to all when the application has no security.
 */
final readonly class Pages
{
    public function __construct(
        private Environment $twig,
        private string $layout,
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = [], int $status = Response::HTTP_OK, string $cacheControl = 'private, no-cache'): Response
    {
        return new Response($this->twig->render('@PhilLibrary/'.$template.'.html.twig', $context + ['layout' => $this->layout]), $status, ['Cache-Control' => $cacheControl]);
    }

    /**
     * @throws AccessDeniedException when the request lacks the role, or the application has no security
     */
    public function denyUnlessGranted(string $role): void
    {
        if (true !== $this->authorizationChecker?->isGranted($role)) {
            $exception = new AccessDeniedException();
            $exception->setAttributes([$role]);

            throw $exception;
        }
    }
}
