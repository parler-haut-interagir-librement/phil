<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Search;

use Override;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Content\DimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\Repository\DimensionContentRepository;
use Phil\ContentBundle\Domain\ValueObject\Body;
use Phil\ContentBundle\Infrastructure\Doctrine\ContentRoots;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\SearchBundle\Application\DocumentProvider;
use Phil\SearchBundle\Domain\ValueObject\Document;
use Phil\Ujo\Exception\UjoException;
use Phil\Ujo\Json\JsonDecoder;
use Phil\Ujo\Text\PlainText;

/**
 * What readers see, fed to `phil/search-bundle`: one document per live localized row of the declared roots, kept up to
 * date at each Doctrine flush — a publication adds or replaces it, unpublishing removes it.
 *
 * - **Its text**: `title`, the excerpt title or else the SEO title; `body`, the passage of the published UJO
 *   (Universal JSON Output) in the row's own language; and each passage another language tunes, as
 *   `body_<language>` — `body_en` for an English quotation in a French page —, so an index reads each in its language.
 * - **Its filters**: `kind`, the resource key; `locale`, the locale of the row; `reference`, the content to link to.
 * - **Its identifier**: the kind, the identifier of the root and the locale, as `news-42-fr`.
 * - A row marked `noindex` is not found: it has no document.
 *
 * The whole index is rebuilt from every root of every kind, in each declared locale.
 */
final readonly class ContentDocumentProvider implements DocumentProvider
{
    public const string DEFAULT_INDEX = 'content';

    public function __construct(
        private ContentRoots $contentRoots,
        private ContentRootRepository $roots,
        private DimensionContentRepository $rows,
        private DeclaredLocales $locales,
        private string $index = self::DEFAULT_INDEX,
        private JsonDecoder $decoder = new JsonDecoder(),
        private PlainText $text = new PlainText(),
    ) {
    }

    #[Override]
    public function index(): string
    {
        return $this->index;
    }

    #[Override]
    public function supports(object $resource): bool
    {
        if (!$resource instanceof DimensionContent) {
            return false;
        }

        $dimension = $resource->dimension();

        return Stage::Live === $dimension->stage && $dimension->isLocalized() && \in_array($resource->root()::class, array_map($this->contentRoots->classOf(...), $this->contentRoots->resourceKeys()), true);
    }

    #[Override]
    public function document(object $resource): ?Document
    {
        if (!$resource instanceof DimensionContent || ($resource instanceof SeoCapable && true === $resource->getSeoNoIndex())) {
            return null;
        }

        $locale = $resource->dimension()->locale;
        $reference = $this->roots->referenceOf($resource->root());

        if (!$locale instanceof Locale) {
            return null;
        }

        $text = array_filter(['title' => $this->title($resource), ...$this->passages($resource, $locale)], static fn (string $value): bool => '' !== $value);

        return Document::of($this->index, $this->id($resource), $text, [
            'kind' => $reference->resourceKey,
            'locale' => $locale->value,
            'reference' => $reference->toString(),
        ]);
    }

    #[Override]
    public function id(object $resource): string
    {
        if (!$resource instanceof DimensionContent) {
            return '';
        }

        $reference = $this->roots->referenceOf($resource->root());

        return $reference->resourceKey.'-'.$reference->resourceId.'-'.($resource->dimension()->locale->value ?? '');
    }

    #[Override]
    public function all(): iterable
    {
        foreach ($this->contentRoots->resourceKeys() as $resourceKey) {
            foreach ($this->roots->findAll($resourceKey) as $root) {
                foreach ($this->locales->all() as $locale) {
                    $version = $this->rows->latestLocalizedVersion($root, Stage::Live, $locale);
                    $row = $version instanceof Version ? $this->rows->find($root, DimensionKey::localized($locale, Stage::Live, $version)) : null;

                    if ($row instanceof DimensionContent) {
                        yield $row;
                    }
                }
            }
        }
    }

    private function title(DimensionContent $row): string
    {
        $title = $row instanceof ExcerptCapable ? $row->getExcerptTitle() : null;
        $title ??= $row instanceof SeoCapable ? $row->getSeoTitle() : null;

        return trim($title ?? '');
    }

    /**
     * The passages of the published body by language: `body` for the row's own, `body_<language>` for the others.
     *
     * @return array<string, string>
     */
    private function passages(DimensionContent $row, Locale $locale): array
    {
        $body = $row instanceof BodyCapable ? $row->getBody() : null;

        if (!$body instanceof Body || 'ujo' !== $body->format) {
            return [];
        }

        try {
            $document = $this->decoder->decode($body->source);
        } catch (UjoException) {
            return [];
        }

        $own = strtolower($locale->toLanguageTag());
        $fields = [];

        foreach ($this->text->passages($document, $own) as $language => $passage) {
            $field = $language === $own ? 'body' : 'body_'.preg_replace('/[^a-z0-9]+/', '_', $language);

            if (1 === preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $field)) {
                $fields[$field] = $passage;
            }
        }

        return $fields;
    }
}
