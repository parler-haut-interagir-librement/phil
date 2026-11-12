<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Twig\Components;

use Phil\SocialCards\Preview\LinkPreview;
use Phil\SocialCardsBundle\Application\LinkPreviews;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The card of a link to another site, `<twig:PhilSocialCards:LinkPreview url="https://example.org/post" />`: its
 * image, site, title and description, read from the page's tags and cached; a plain link when the page gives nothing,
 * and no link at all for an address that cannot be fetched, a `javascript:` or `data:` one among them.
 * The image is loaded lazily and without a referrer.
 *
 * `:snapshot="message.linkPreview"` shows a preview kept with a message, the array of `LinkPreview::toArray()`, as it
 * was when the message was written: nothing is fetched, and the card does not change when the page does.
 */
#[AsTwigComponent(name: 'PhilSocialCards:LinkPreview', template: '@PhilSocialCards/components/LinkPreview.html.twig', exposePublicProps: false)]
final class LinkPreviewComponent
{
    public string $url = '';

    /**
     * A preview kept with a message, as `LinkPreview::toArray()` gives it; it wins over `url`.
     *
     * @var array<array-key, mixed>|null
     */
    public ?array $snapshot = null;

    /** Whether the card shows the page's image, which the reader's browser fetches from the other site. */
    public bool $image = true;

    public function __construct(
        private readonly LinkPreviews $previews,
    ) {
    }

    public function getPreview(): ?LinkPreview
    {
        if (null !== $this->snapshot) {
            return LinkPreview::fromArray($this->snapshot);
        }

        return $this->previews->preview($this->url);
    }

    /**
     * The address of the plain link shown when there is no preview: only one that could be fetched, absolute HTTP or
     * HTTPS, so that no `javascript:` address a member typed becomes a link.
     */
    public function getPlainUrl(): ?string
    {
        return LinkPreviews::isFetchable($this->url) ? $this->url : null;
    }
}
