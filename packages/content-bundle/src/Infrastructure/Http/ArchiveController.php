<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Publication\ArchivedVersion;
use Phil\ContentBundle\Application\Publication\ContentArchive;
use Phil\ContentBundle\Application\Rendering\TemplateResolver;
use Phil\ContentBundle\Application\Routing\RouteMatch;
use Phil\ContentBundle\Application\Routing\RouteResolver;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * One dated version of a content whose kind has a public archive (`phil_content.archives`), as readers read it when it
 * was published: the « now » page of a past month. The application mounts it on a route of its own, with the kind as
 * the `resourceKey` default; the content is the one its `slug` names in the request locale, or the content of a
 * single kind when the route has no slug:
 *
 *     now_archive:
 *         path: /{_locale}/now/{version}
 *         controller: Phil\ContentBundle\Infrastructure\Http\ArchiveController
 *         defaults: { resourceKey: now_page }
 *         requirements: { version: '\d{1,9}' }
 *
 * The template is the content's own, which receives the variables `ContentController` gives it — `content`,
 * `reference`, `locale`, `resolved_locale` (the request locale: an archive does not fall back) and `left_language`
 * (false) —, plus `archived`, the `ArchivedVersion` shown, and `archive`, every publication of the locale, the latest
 * first. Anything not found —
 * the kind without an archive, the content, a version the locale was not published from — answers 404.
 */
final readonly class ArchiveController
{
    public function __construct(
        private ContentArchive $archive,
        private ContentManager $contents,
        private RouteResolver $resolver,
        private TemplateResolver $templates,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $resourceKey, int $version, ?string $slug = null): Response
    {
        try {
            $locale = Locale::fromString($request->getLocale());
            $reference = $this->reference($resourceKey, $locale, $slug);
            $content = $this->archive->read($reference, $locale, $version);
            $archive = $this->archive->history($reference, $locale);
        } catch (DomainError $domainError) {
            throw new NotFoundHttpException($domainError->getMessage(), $domainError);
        } catch (InvalidLocaleDimension $invalidLocaleDimension) {
            throw new NotFoundHttpException($invalidLocaleDimension->getMessage(), $invalidLocaleDimension);
        }

        $archived = array_find($archive, static fn (ArchivedVersion $published): bool => $published->version === $version);
        $templateKey = $content['templateKey'] ?? null;

        return new Response($this->twig->render($this->templates->resolve(\is_string($templateKey) ? $templateKey : null), [
            'content' => $content,
            'reference' => $reference,
            'locale' => $locale->value,
            'resolved_locale' => $locale->value,
            'left_language' => false,
            'archived' => $archived,
            'archive' => $archive,
        ]));
    }

    /**
     * @throws DomainError
     */
    private function reference(string $resourceKey, Locale $locale, ?string $slug): ContentReference
    {
        if (null === $slug) {
            return $this->contents->single($resourceKey) ?? throw new NotFoundHttpException(\sprintf('The single kind "%s" has no content yet.', $resourceKey));
        }

        $match = $this->resolver->resolve($resourceKey, FallbackChain::of([$locale]), $slug);

        if (!$match instanceof RouteMatch) {
            throw new NotFoundHttpException(\sprintf('No content of "%s" has the slug "%s".', $resourceKey, $slug));
        }

        return $match->route->content();
    }
}
