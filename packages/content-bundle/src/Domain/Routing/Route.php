<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Routing;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use Phil\Slug\Slug;

/**
 * One address of a content root in one locale: a slug, under the resource key of its kind of content.
 *
 * - **One table for every kind of content, keyed by `(resource_key, locale, slug)`.** The application mounts
 *   one Symfony route per kind (`/news/{slug}`, `/{slug}`), so two kinds may share a slug, and one kind may
 *   not use the same slug twice in one locale.
 * - **A route is current or history.** When a published slug changes, the old route stays, marked as
 *   history, so a request for it is answered with a permanent redirect to the current route of the same root
 *   and locale. A history route stores no target: the current route is looked up when the request comes, so a
 *   chain of renames never redirects twice.
 * - **A slug comes back to its root.** Publishing a slug the root used before revives its history route.
 *   A history route of another root gives its slug up: the old address then serves the new content.
 *
 * Routes describe what readers are served: they are written when a localized row is published, from its
 * slug, never while it is edited.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_content_route')]
#[ORM\UniqueConstraint(columns: ['resource_key', 'locale', 'slug'])]
#[ORM\Index(columns: ['resource_key', 'resource_id', 'locale'])]
final class Route
{
    #[ORM\Id]
    #[ORM\Column(name: 'id', type: 'phil_content_route_id')]
    private RouteId $id;

    #[ORM\Column(name: 'resource_key', length: 64)]
    private string $resourceKey;

    #[ORM\Column(name: 'resource_id', length: 64)]
    private string $resourceId;

    #[ORM\Column(name: 'slug', length: 255)]
    private string $slug;

    #[ORM\Column(name: 'history')]
    private bool $history = false;

    private function __construct(
        ContentReference $content,
        #[ORM\Column(name: 'locale', type: 'phil_content_locale')]
        private Locale $locale,
        Slug $slug,
    ) {
        $this->id = RouteId::generate();
        $this->resourceKey = $content->resourceKey;
        $this->resourceId = $content->resourceId;
        $this->slug = $slug->value;
    }

    public static function current(ContentReference $content, Locale $locale, Slug $slug): self
    {
        return new self($content, $locale, $slug);
    }

    public function id(): RouteId
    {
        return $this->id;
    }

    public function content(): ContentReference
    {
        return ContentReference::of($this->resourceKey, $this->resourceId);
    }

    public function locale(): Locale
    {
        return $this->locale;
    }

    public function slug(): Slug
    {
        return Slug::fromString($this->slug);
    }

    public function isHistory(): bool
    {
        return $this->history;
    }

    /**
     * The root moved to another slug: this one now redirects.
     */
    public function retire(): void
    {
        $this->history = true;
    }

    /**
     * Makes this route the current one of `$content`: its own history route revived, or a history route of
     * another root of the same kind taken over.
     *
     * @throws InvalidContent when `$content` is of another kind, whose routes live under another resource key
     */
    public function pointTo(ContentReference $content): void
    {
        if ($content->resourceKey !== $this->resourceKey) {
            throw InvalidContent::because(\sprintf('A route of "%s" cannot point to a content of "%s".', $this->resourceKey, $content->resourceKey));
        }

        $this->resourceId = $content->resourceId;
        $this->history = false;
    }
}
