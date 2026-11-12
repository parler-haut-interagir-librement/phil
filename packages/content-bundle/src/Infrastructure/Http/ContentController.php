<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Publication\LiveContentReader;
use Phil\ContentBundle\Application\Rendering\TemplateResolver;
use Phil\ContentBundle\Application\Routing\RouteResolver;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\RouteRepository;
use Phil\ContentBundle\Domain\Routing\Route;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Serves the live content a slug addresses, rendered through the template of its template key.
 *
 * The application mounts it once per kind of content, under a path of its choosing with a `{slug}`
 * parameter, and names the route under `phil_content.routes`:
 *
 * ```yaml
 * # config/routes/content.yaml
 * news_show:
 *     path: /news/{slug}
 *     controller: Phil\ContentBundle\Infrastructure\Http\ContentController
 * ```
 *
 * A kind may also be mounted on routes of a fixed address, the slug given as a route default, listed after
 * its generating route (see `ResourceRoutes`); an old slug then redirects to the generating route.
 *
 * 1. The request's locale must be served; its declared fallback chain is used throughout.
 * 2. The slug is looked up in each locale of the chain. An old slug answers `301 Moved Permanently` to the
 *    current one, on the same route.
 * 3. The live content is read along the chain and normalized; the template receives it as `content`, with
 *    `root`, `reference`, `locale` (requested), `resolved_locale` (served) and `left_language`.
 * 4. **One content, one address.** When the locale served is not the locale whose route matched — the slug
 *    of `fr` requested in `fr_CA`, where the root publishes `fr_CA` under a slug of its own — the request
 *    answers `301` to the current slug of the locale served. A locale served without a route of its own is
 *    not read: the content is read from the locale of the route that matched onward.
 *
 * Anything not found — a locale not served, an unknown slug, a root no locale of the chain publishes —
 * answers 404.
 */
final readonly class ContentController
{
    public function __construct(
        private DeclaredLocales $locales,
        private ResourceRoutes $resourceRoutes,
        private RouteResolver $resolver,
        private ContentRootRepository $roots,
        private LiveContentReader $reader,
        private ContentNormalizer $normalizer,
        private TemplateResolver $templates,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private RouteRepository $routes,
    ) {
    }

    public function __invoke(Request $request, string $slug): Response
    {
        $route = $request->attributes->getString('_route');
        $canonicalRoute = $request->attributes->getString('_canonical_route', $route);
        $resourceKey = $this->resourceRoutes->resourceKeyOf($canonicalRoute)
            ?? throw new NotFoundHttpException(\sprintf('The route "%s" serves no kind of content under "phil_content.routes".', $route));

        try {
            $locale = Locale::fromString($request->getLocale());
            $chain = $this->locales->chainFor($locale);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw new NotFoundHttpException($invalidLocaleDimension->getMessage(), $invalidLocaleDimension);
        }

        $match = $this->resolver->resolve($resourceKey, $chain, $slug)
            ?? throw new NotFoundHttpException(\sprintf('No content of "%s" has the slug "%s".', $resourceKey, $slug));

        // A route of its own, with its slug as a default, cannot address another slug: a redirect goes to the
        // route the kind's URLs are generated on.
        $generating = $this->resourceRoutes->routeOf($resourceKey) ?? $canonicalRoute;
        $redirectRoute = $canonicalRoute === $generating ? $route : $generating;

        if ($match->redirect) {
            return $this->redirect($request, $redirectRoute, $match->route);
        }

        $reference = $match->route->content();
        $root = $this->roots->find($reference);

        if (!$root instanceof ContentRoot) {
            throw new NotFoundHttpException(\sprintf('The content "%s" does not exist.', $reference->toString()));
        }

        $content = $this->read($root, $chain);
        $resolved = $content->dimension()->locale ?? $locale;
        $matched = $match->route->locale();

        if (!$resolved->equals($matched)) {
            $own = $this->routes->findCurrent($reference, $resolved);

            if ($own instanceof Route) {
                if (!$own->slug()->equals($match->route->slug())) {
                    return $this->redirect($request, $redirectRoute, $own);
                }
            } else {
                $content = $this->read($root, $this->chainFrom($chain, $matched));
                $resolved = $content->dimension()->locale ?? $locale;
            }
        }

        $template = $this->templates->resolve($content instanceof TemplateCapable ? $content->getTemplateKey() : null);

        return new Response($this->twig->render($template, [
            'content' => $this->normalizer->normalize($content),
            'root' => $root,
            'reference' => $reference,
            'locale' => $locale->value,
            'resolved_locale' => $resolved->value,
            'left_language' => $chain->leavesRequestedLanguage($resolved),
        ]));
    }

    /**
     * @throws NotFoundHttpException when no locale of the chain is published
     */
    private function read(ContentRoot $root, FallbackChain $chain): DimensionContent
    {
        try {
            return $this->reader->read($root, $chain);
        } catch (ContentNotFound $contentNotFound) {
            throw new NotFoundHttpException($contentNotFound->getMessage(), $contentNotFound);
        }
    }

    /**
     * The end of `$chain` that starts at `$locale`, a locale of the chain: the route resolver found the slug
     * there.
     */
    private function chainFrom(FallbackChain $chain, Locale $locale): FallbackChain
    {
        $locales = $chain->locales;

        while ([] !== $locales && !$locales[0]->equals($locale)) {
            array_shift($locales);
        }

        return FallbackChain::of($locales);
    }

    private function redirect(Request $request, string $route, Route $target): RedirectResponse
    {
        $parameters = $request->attributes->all('_route_params');
        $parameters['slug'] = $target->slug()->value;

        return new RedirectResponse($this->urlGenerator->generate($route, $parameters), Response::HTTP_MOVED_PERMANENTLY);
    }
}
