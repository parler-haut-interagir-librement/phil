<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Report;

use JsonSerializable;
use Override;
use Phil\LinkChecker\LinkStatus;
use Phil\Url\Url;

/**
 * The links of one content and what their checks found, in document order: a link written twice is listed twice, with
 * its own place, and checked once. `$truncated` says the content was only checked in part, past the extractor's limits.
 *
 * It renders as text, a summary followed by one line for each link that is not alive, and as JSON, every link
 * included.
 */
final readonly class LinkReport implements JsonSerializable
{
    /**
     * @param list<CheckedLink> $links
     */
    public function __construct(
        public string $content,
        public Url $page,
        public array $links,
        public bool $truncated = false,
    ) {
    }

    /**
     * @return list<CheckedLink> the links dead, with a missing anchor, or that cannot be read
     */
    public function broken(): array
    {
        return $this->filter(static fn (LinkStatus $status): bool => $status->isBroken());
    }

    /**
     * @return list<CheckedLink> the links whose state could not be told
     */
    public function toReview(): array
    {
        return $this->filter(static fn (LinkStatus $status): bool => LinkStatus::Review === $status);
    }

    /**
     * @return list<CheckedLink> the links that reach their page through redirects
     */
    public function redirected(): array
    {
        return $this->filter(static fn (LinkStatus $status): bool => $status->isRedirect());
    }

    /**
     * @return list<CheckedLink> the links to addresses that are not public, which were not requested
     */
    public function refused(): array
    {
        return $this->filter(static fn (LinkStatus $status): bool => LinkStatus::Refused === $status);
    }

    /**
     * Whether no link is broken.
     */
    public function isClean(): bool
    {
        return [] === $this->broken();
    }

    /**
     * @return array{checked: int, broken: int, review: int, redirected: int, refused: int}
     */
    public function summary(): array
    {
        return [
            'checked' => \count($this->links),
            'broken' => \count($this->broken()),
            'review' => \count($this->toReview()),
            'redirected' => \count($this->redirected()),
            'refused' => \count($this->refused()),
        ];
    }

    public function toText(): string
    {
        $summary = $this->summary();
        $lines = [\sprintf(
            'Links of %s (%s): %d checked, %d broken, %d to review, %d redirected, %d refused.',
            $this->content,
            $this->page->toString(),
            $summary['checked'],
            $summary['broken'],
            $summary['review'],
            $summary['redirected'],
            $summary['refused'],
        )];

        foreach ($this->links as $link) {
            if (LinkStatus::Alive !== $link->result->status) {
                $lines[] = '- '.$link->toText();
            }
        }

        if ($this->truncated) {
            $lines[] = 'The content was checked in part: it passes the bytes or the links the extractor reads.';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array{content: string, page: string, truncated: bool, summary: array{checked: int, broken: int, review: int, redirected: int, refused: int}, links: list<CheckedLink>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'content' => $this->content,
            'page' => $this->page->toString(),
            'truncated' => $this->truncated,
            'summary' => $this->summary(),
            'links' => $this->links,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param callable(LinkStatus): bool $keep
     *
     * @return list<CheckedLink>
     */
    private function filter(callable $keep): array
    {
        return array_values(array_filter($this->links, static fn (CheckedLink $link): bool => $keep($link->result->status)));
    }
}
