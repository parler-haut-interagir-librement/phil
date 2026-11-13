<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Http;

use Phil\Resource\ResourceReference;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The configuration of the comments controller for one resource and one viewer: the subject, the viewer's reference,
 * the endpoints' URLs when their routes are imported — those of one thread or comment with `__id__` in place of its
 * identifier —, the name of the cookie that carries the controller's CSRF token, and the address of the member search
 * that gives the text areas their `@` autocomplete when `phil/mentions-bundle` is there and its route imported,
 * whether the panel offers to comment on the whole page, and whether it writes the roles of WAI-ARIA 1.3.
 */
final readonly class ClientConfig
{
    public function __construct(
        private ?UrlGeneratorInterface $router = null,
        private ?CommentsRequest $requests = null,
        private ?string $mentionSearchRoute = null,
        private bool $ariaRoles = false,
    ) {
    }

    /**
     * @param bool $page whether the panel offers a form to comment on the whole page, beside the passages
     *
     * @return array{subject: string, viewer: string, urls: ?array<string, string>, csrfCookie: ?string, mentions: ?string, page: bool, ariaRoles: bool}
     */
    public function for(ResourceReference $subject, ResourceReference $viewer, bool $page = true): array
    {
        $urls = null;

        if ($this->router instanceof UrlGeneratorInterface) {
            $urls = [];

            foreach (Routes::NAMES as $key => $name) {
                $urls[$key] = $this->router->generate($name, Routes::PARAMETERS[$key]);
            }
        }

        return ['subject' => $subject->toString(), 'viewer' => $viewer->toString(), 'urls' => $urls, 'csrfCookie' => $this->requests?->cookieName(), 'mentions' => $this->mentionSearch(), 'page' => $page, 'ariaRoles' => $this->ariaRoles];
    }

    /**
     * Whether the roles `comment` and `suggestion` of the WAI-ARIA 1.3 draft are written, with `aria-details` from a
     * passage to its comments; the option `aria_roles` turns them on.
     */
    public function hasAriaRoles(): bool
    {
        return $this->ariaRoles;
    }

    /**
     * The address of the member search of `phil/mentions-bundle`; `null` without that bundle or its route.
     */
    public function mentionSearch(): ?string
    {
        if (null === $this->mentionSearchRoute) {
            return null;
        }

        try {
            return $this->router?->generate($this->mentionSearchRoute);
        } catch (RouteNotFoundException) {
            return null;
        }
    }
}
