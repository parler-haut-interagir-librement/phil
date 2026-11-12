<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Twig;

use Phil\ContentBundle\Application\Content\SingleKinds;
use Phil\ContentBundle\Application\Pipeline\ContentNormalizer;
use Phil\ContentBundle\Application\Publication\ArchivedVersion;
use Phil\ContentBundle\Application\Publication\ContentArchive;
use Phil\ContentBundle\Application\Publication\LiveContentReader;
use Phil\ContentBundle\Application\Rendering\ContentSchema;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Dimension\DimensionLocales;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\Repository\ContentRootRepository;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\LocaleDimensions\DeclaredLocales;
use Phil\LocaleDimensions\Locale;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * What the functions of `ContentExtension` do.
 *
 * - **A page never fails on what it links to.** `content()`, `url()` and `linkUrl()` answer `null` for
 *   whatever the domain refuses: nothing published along the chain, a request locale `phil_content.locales`
 *   does not serve or that is not spelled as a `Locale` (`fr-CA`), a malformed reference. A URL of a kind of
 *   content mounted on no route is `null` as well. `content()` of a kind no root declares still fails: the
 *   reference is a mistake of the template or of the configuration, not a state of the content.
 * - `seo()` escapes every value it prints: the tags are safe to print raw because they are built here, not
 *   because the values were trusted.
 */
final readonly class ContentRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ContentRootRepository $roots,
        private LiveContentReader $reader,
        private ContentNormalizer $normalizer,
        private DeclaredLocales $locales,
        private ContentUrlGenerator $urls,
        private RequestStack $requests,
        private ?SingleKinds $singles = null,
        private ?ContentArchive $archive = null,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function content(ContentReference|ContentRoot|string $reference, ?string $locale = null): ?array
    {
        try {
            $root = $reference instanceof ContentRoot ? $reference : $this->roots->find($this->reference($reference));

            if (!$root instanceof ContentRoot) {
                return null;
            }

            return $this->normalizer->normalize($this->reader->read($root, DimensionLocales::chain($this->locales, $this->locale($locale))));
        } catch (DomainError) {
            return null;
        }
    }

    /**
     * The live content of a single kind — the home page, the contact details —, as `content()` reads it; `null` before
     * its root is created or published.
     *
     * @return array<string, mixed>|null
     *
     * @throws InvalidContent when the kind is not declared under `phil_content.singles`
     */
    public function single(string $resourceKey, ?string $locale = null): ?array
    {
        if (true !== $this->singles?->isSingle($resourceKey)) {
            throw InvalidContent::because(\sprintf('"%s" is not a single kind; declare it under "phil_content.singles".', $resourceKey));
        }

        $roots = $this->roots->findAll($resourceKey);

        return [] === $roots ? null : $this->content($roots[0], $locale);
    }

    /**
     * Every publication of a content in a locale, the latest first, when its kind has a public archive; an empty list
     * otherwise, or for a content that does not exist.
     *
     * @return list<ArchivedVersion>
     */
    public function archive(ContentReference|ContentRoot|string $reference, ?string $locale = null): array
    {
        try {
            return $this->archive?->history($this->reference($reference), $this->locale($locale)) ?? [];
        } catch (DomainError) {
            return [];
        }
    }

    public function url(ContentReference|ContentRoot|string $reference, ?string $locale = null, bool $absolute = false): ?string
    {
        try {
            $locale = $this->locale($locale);
            DimensionLocales::chain($this->locales, $locale);

            return $this->urls->generate($this->reference($reference), $locale, $absolute);
        } catch (DomainError) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed>|null $link the `link` of a normalized content
     */
    public function linkUrl(?array $link, ?string $locale = null, bool $absolute = false): ?string
    {
        // No link reads as no URL nor reference below: `null`.
        if (\is_string($link['url'] ?? null)) {
            return $link['url'];
        }

        $key = $link['resourceKey'] ?? null;
        $id = $link['resourceId'] ?? null;
        $pinned = $link['locale'] ?? null;

        if (!\is_string($key) || !\is_string($id)) {
            return null;
        }

        try {
            return $this->url(ContentReference::of($key, $id), \is_string($pinned) ? $pinned : $locale, $absolute);
        } catch (DomainError) {
            return null;
        }
    }

    /**
     * @param array<array-key, mixed> $content a normalized content
     */
    public function seo(array $content): string
    {
        $tags = [];
        $description = $content['seoDescription'] ?? $content['excerptDescription'] ?? null;

        if (\is_string($description)) {
            $tags[] = \sprintf('<meta name="description" content="%s">', $this->escape($description));
        }

        $keywords = $content['seoKeywords'] ?? [];

        if (\is_array($keywords) && [] !== $keywords) {
            $tags[] = \sprintf('<meta name="keywords" content="%s">', $this->escape(implode(', ', array_filter($keywords, \is_string(...)))));
        }

        $robots = array_keys(array_filter([
            'noindex' => true === ($content['seoNoIndex'] ?? false),
            'nofollow' => true === ($content['seoNoFollow'] ?? false),
        ]));

        if ([] !== $robots) {
            $tags[] = \sprintf('<meta name="robots" content="%s">', implode(', ', $robots));
        }

        $canonical = $content['seoCanonicalUrl'] ?? null;

        if (\is_string($canonical)) {
            $tags[] = \sprintf('<link rel="canonical" href="%s">', $this->escape($canonical));
        }

        return implode("\n", $tags);
    }

    /**
     * The content as schema.org JSON-LD data, in the request's language.
     *
     * @param array<array-key, mixed> $content a normalized content
     *
     * @return array<string, mixed>
     */
    public function schema(array $content, string $type = 'WebPage', ?string $url = null): array
    {
        return ContentSchema::of($content, $type, $url, $this->locale(null)->value);
    }

    private function reference(ContentReference|ContentRoot|string $reference): ContentReference
    {
        return match (true) {
            $reference instanceof ContentReference => $reference,
            $reference instanceof ContentRoot => $this->roots->referenceOf($reference),
            default => ContentReference::fromString($reference),
        };
    }

    private function locale(?string $locale): Locale
    {
        return DimensionLocales::parse($locale ?? $this->requests->getCurrentRequest()?->getLocale() ?? $this->locales->all()[0]->value ?? 'en');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
