<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Infrastructure\Twig;

use Phil\FeedBundle\Application\Contract\FeedUrls;
use Phil\FeedBundle\Application\Exception\UnknownFeed;
use Phil\FeedBundle\Application\Feeds;
use Phil\FeedBundle\Application\FeedWriters;
use Phil\FeedBundle\Domain\FeedFormat;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

/**
 * Writes one `<link rel="alternate">` per feed and format through the template `@PhilFeed/links.html.twig`, each
 * quoted attribute escaped by Twig's HTML autoescaping, as the `html_attr` function of `twig/html-extra` escapes a
 * value: the media type of the format, the title of the feed's channel and the format's name, and the absolute
 * address of the feed. Only the channel of each feed is read, never its entries.
 */
final readonly class FeedRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private Feeds $feeds,
        private FeedWriters $writers,
        private FeedUrls $urls,
    ) {
    }

    /**
     * @param string|list<string>|null $names   the feeds, all of them when `null`
     * @param list<string>|null        $formats the formats among those served, all of them when `null`
     *
     * @throws UnknownFeed when a name is not a feed's
     */
    public function links(Environment $environment, string|array|null $names = null, ?array $formats = null): string
    {
        $served = $this->writers->formats();

        if (null !== $formats) {
            $served = array_values(array_filter($served, static fn (FeedFormat $format): bool => \in_array($format->value, $formats, true)));
        }

        $links = [];

        foreach (\is_string($names) ? [$names] : $names ?? $this->feeds->names() as $name) {
            $title = $this->feeds->source($name)->channel()->title;

            foreach ($served as $format) {
                $links[] = [
                    'type' => $format->mediaType(),
                    'title' => $title.' ('.$format->label().')',
                    'href' => $this->urls->feed($name, $format)->toString(),
                ];
            }
        }

        return $environment->render('@PhilFeed/links.html.twig', ['links' => $links]);
    }
}
