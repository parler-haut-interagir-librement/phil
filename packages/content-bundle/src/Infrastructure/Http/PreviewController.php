<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Http;

use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Publication\PublishedContents;
use Phil\ContentBundle\Application\Rendering\TemplateResolver;
use Phil\ContentBundle\Domain\Capability\TemplateCapable;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Exception\InvalidLocaleDimension;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * The draft of a content in one locale at a signed link, rendered through the template of its template key as its
 * page will be, with `preview` set: neither indexed nor cached. A link not signed, expired, or for a content without
 * a draft in the locale is a `404`.
 */
final readonly class PreviewController
{
    public function __construct(
        private PreviewLinks $links,
        private ContentRootRepository $roots,
        private PublishedContents $contents,
        private ContentNormalizer $normalizer,
        private TemplateResolver $templates,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request, string $reference, string $locale): Response
    {
        if (!$this->links->verify($reference, $locale, $request->query->getInt('expires'), $request->query->getString('signature'))) {
            throw new NotFoundHttpException('This preview link is not signed, or expired.');
        }

        try {
            $content = ContentReference::fromString($reference);
            $dimension = Locale::fromString($locale);
        } catch (InvalidContent|InvalidLocaleDimension $invalid) {
            throw new NotFoundHttpException($invalid->getMessage(), $invalid);
        }

        $root = $this->roots->find($content);
        $draft = $root instanceof ContentRoot ? $this->contents->draft($root, $dimension) : null;

        if (!$draft instanceof DimensionContent) {
            throw new NotFoundHttpException(\sprintf('"%s" has no draft in "%s".', $reference, $locale));
        }

        $response = new Response($this->twig->render($this->templates->resolve($draft instanceof TemplateCapable ? $draft->getTemplateKey() : null), [
            'content' => $this->normalizer->normalize($draft),
            'root' => $root,
            'reference' => $content,
            'locale' => $locale,
            'resolved_locale' => $locale,
            'left_language' => false,
            'preview' => true,
        ]));
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
