<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Http;

use Phil\MediaBundle\Domain\Model\Media;
use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Card\CardTemplate;
use Phil\SocialCards\ShareMetadata;
use Phil\SocialCardsBundle\Application\Contract\ShareMetadataSource;
use Phil\SocialCardsBundle\Application\SharePage;
use Phil\SocialCardsBundle\Application\SocialCards;
use Phil\SocialCardsBundle\Infrastructure\Media\MediaShareImages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The share metadata of the page being rendered, from four layers, each over the one before: the defaults of the
 * configuration, the sources of the application, what the request set — `SharePage` —, and what the template passes
 * to `social_meta()`. The address of the page is the request's, without its query, when no layer gives one; its locale
 * the request's; its image a medium of phil/media-bundle, an address or an array, else the page's card — drawn from its
 * title and its description, or the name of the site —, unless `card` is false.
 */
final readonly class ShareMetadataFactory
{
    /**
     * @param array<string, mixed>          $defaults
     * @param iterable<ShareMetadataSource> $sources
     */
    public function __construct(
        private SharePage $page,
        private RequestStack $requests,
        private UrlGeneratorInterface $urls,
        private array $defaults = [],
        private iterable $sources = [],
        private ?SocialCards $cards = null,
        private ?MediaShareImages $media = null,
    ) {
    }

    /**
     * The metadata, or null when no layer gives the page a title, or an address outside a request.
     *
     * @param array<string, mixed> $overrides
     */
    public function build(array $overrides = []): ?ShareMetadata
    {
        $values = $this->defined($this->defaults);

        foreach ($this->sources as $source) {
            $values = [...$values, ...$this->defined($source->values())];
        }

        $values = [...$values, ...$this->page->values(), ...$this->defined($overrides)];
        $title = $values['title'] ?? null;

        if (!\is_string($title) || '' === trim($title)) {
            return null;
        }

        $request = $this->requests->getMainRequest();

        if ($request instanceof Request) {
            $values['url'] ??= $request->getSchemeAndHttpHost().$request->getBaseUrl().$request->getPathInfo();
            $values['locale'] ??= $request->getLocale();
        }

        if (!isset($values['url'])) {
            return null;
        }

        $template = $values['card_template'] ?? null;
        $card = $values['card'] ?? true;
        unset($values['card_template'], $values['card']);

        if ($this->media instanceof MediaShareImages && ($values['image'] ?? null) instanceof Media) {
            $values['image'] = $this->media->image($values['image']);
        }

        if (!isset($values['image']) && false !== $card && $this->cards instanceof SocialCards) {
            $values['image'] = $this->card($this->cards, $this->urls, $title, $values, \is_string($template) ? $template : null);
        }

        return ShareMetadata::fromArray($values);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array{url: string, alt: string, width: int, height: int, type: string}
     */
    private function card(SocialCards $cards, UrlGeneratorInterface $urls, string $title, array $values, ?string $template): array
    {
        $subtitle = $values['description'] ?? $values['site_name'] ?? null;
        $locale = $values['locale'] ?? 'en';
        $content = new CardContent($title, \is_string($subtitle) ? $subtitle : null, \is_string($locale) ? $locale : 'en');
        $fingerprint = $cards->request($content, $template);

        return [
            'url' => $urls->generate(SocialCardController::ROUTE, ['fingerprint' => $fingerprint], UrlGeneratorInterface::ABSOLUTE_URL),
            'alt' => $content->title,
            'width' => CardTemplate::WIDTH,
            'height' => CardTemplate::HEIGHT,
            'type' => 'image/png',
        ];
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function defined(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => null !== $value);
    }
}
