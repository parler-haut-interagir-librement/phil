<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures\Content;

use Override;
use Phil\ContentBundle\Application\Publication\PublishedContent;
use Phil\FeedBundle\Infrastructure\Content\Contract\ContentEntries;
use Phil\LocaleDimensions\Locale;

/**
 * Published contents held in memory, in the order given, and the addresses of some of them. It records what it is
 * asked.
 */
final class InMemoryEntries implements ContentEntries
{
    /** @var list<array{string, string, int, int}> */
    public array $asked = [];

    /** @var list<list<string>> */
    public array $addressed = [];

    /**
     * @param list<PublishedContent> $contents
     * @param array<string, string>  $addresses
     */
    public function __construct(
        private readonly array $contents,
        private readonly array $addresses,
    ) {
    }

    #[Override]
    public function latest(string $kind, Locale $locale, int $offset, int $limit): array
    {
        $this->asked[] = [$kind, $locale->value, $offset, $limit];

        return \array_slice($this->contents, $offset, $limit);
    }

    #[Override]
    public function addresses(array $contents, Locale $locale): array
    {
        $this->addressed[] = array_map(static fn (PublishedContent $content): string => $content->reference->toString(), $contents);

        return array_intersect_key($this->addresses, array_flip(array_map(static fn (PublishedContent $content): string => $content->reference->toString(), $contents)));
    }
}
