<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Infrastructure\Twig;

use Phil\SocialCards\MetaTags;
use Phil\SocialCards\ShareMetadata;
use Phil\SocialCardsBundle\Application\SharePage;
use Phil\SocialCardsBundle\Infrastructure\Http\ShareMetadataFactory;
use Twig\Attribute\AsTwigFunction;

/**
 * `{{ social_meta() }}` in the head of a layout writes the page's canonical link, description, Open Graph, X and
 * fediverse tags — nothing when no layer gives the page a title —; `{{ social_meta({title: 'Another'}) }}` changes
 * values for this call; `{% do social_page({title: post.title, type: 'article'}) %}` in a page's template sets the
 * page's values, as a controller does through `SharePage`.
 */
final readonly class SocialMetaExtension
{
    public function __construct(
        private ShareMetadataFactory $factory,
        private SharePage $page,
    ) {
    }

    /**
     * @param array<string, mixed> $overrides
     */
    #[AsTwigFunction(name: 'social_meta', isSafe: ['html'])]
    public function meta(array $overrides = []): string
    {
        $page = $this->factory->build($overrides);

        return $page instanceof ShareMetadata ? MetaTags::html(MetaTags::of($page)) : '';
    }

    /**
     * @param array<string, mixed> $values
     */
    #[AsTwigFunction(name: 'social_page')]
    public function page(array $values): string
    {
        $this->page->set($values);

        return '';
    }
}
