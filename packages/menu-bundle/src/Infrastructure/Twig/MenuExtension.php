<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Infrastructure\Twig;

use Phil\MenuBundle\Application\BreadcrumbSchema;
use Phil\MenuBundle\Application\MenuContext;
use Phil\MenuBundle\Application\Menus;
use Phil\MenuBundle\Domain\Menu;
use Phil\MenuBundle\Domain\MenuItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;
use WeakMap;

/**
 * Menus in templates, their current item read from the request being handled.
 *
 * A menu is built and decorated once per request and name: the list, the breadcrumb and its JSON-LD of one page share
 * it, so a decorator that reads a table runs once. Without a request, each call builds the menu again.
 */
final readonly class MenuExtension
{
    /** @var WeakMap<Request, array<string, Menu>> */
    private WeakMap $built;

    public function __construct(
        private Menus $menus,
        private RequestStack $requests,
        private ?TranslatorInterface $translator = null,
        private ?UrlGeneratorInterface $urls = null,
    ) {
        $this->built = new WeakMap();
    }

    #[AsTwigFunction(name: 'menu')]
    public function menu(string $name): Menu
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request instanceof Request) {
            return $this->menus->get($name);
        }

        $built = $this->built[$request] ?? [];

        if (!isset($built[$name])) {
            $built[$name] = $this->menus->get($name, $this->context($request));
            $this->built[$request] = $built;
        }

        return $built[$name];
    }

    /**
     * @return list<MenuItem>
     */
    #[AsTwigFunction(name: 'menu_breadcrumb')]
    public function breadcrumb(string $name): array
    {
        return $this->menu($name)->breadcrumb();
    }

    /**
     * The breadcrumb of a menu as schema.org JSON-LD data, a `BreadcrumbList`: the labels translated in the menu's
     * domain, as the templates show them, and each item's absolute URL — its route generated, or its address on the
     * site completed with the request's scheme and host.
     *
     * @return array<string, mixed>
     */
    #[AsTwigFunction(name: 'menu_breadcrumb_schema')]
    public function breadcrumbSchema(string $name): array
    {
        $menu = $this->menu($name);
        $domain = $menu->translationDomain;

        return BreadcrumbSchema::of(
            $menu->breadcrumb(),
            fn (MenuItem $item): string => null === $domain || !$this->translator instanceof TranslatorInterface ? $item->label : $this->translator->trans($item->label, [], $domain),
            $this->absoluteUrl(...),
        );
    }

    private function absoluteUrl(MenuItem $item): ?string
    {
        if (null !== $item->route) {
            return $this->urls?->generate($item->route, $item->routeParameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $url = $item->url;
        $request = $this->requests->getCurrentRequest();

        return match (true) {
            null === $url => null,
            1 === preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $url) => $url,
            !$request instanceof Request => null,
            str_starts_with($url, '//') => $request->getScheme().':'.$url,
            str_starts_with($url, '/') => $request->getSchemeAndHttpHost().$url,
            default => null,
        };
    }

    private function context(Request $request): MenuContext
    {
        $route = $request->attributes->get('_route');
        $parameters = $request->attributes->get('_route_params');

        return new MenuContext(
            \is_string($route) ? $route : null,
            \is_array($parameters) ? $parameters : [],
            $request->getPathInfo(),
            $request->getLocale(),
        );
    }
}
