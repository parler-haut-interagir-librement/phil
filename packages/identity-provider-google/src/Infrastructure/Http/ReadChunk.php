<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogle\Infrastructure\Http;

use Override;
use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * A chunk of an answer already read whole: the first one, empty, the body, then the last one, empty, at its end.
 */
final readonly class ReadChunk implements ChunkInterface
{
    public function __construct(
        private string $content = '',
        private int $offset = 0,
        private bool $first = false,
        private bool $last = false,
    ) {
    }

    #[Override]
    public function isTimeout(): bool
    {
        return false;
    }

    #[Override]
    public function isFirst(): bool
    {
        return $this->first;
    }

    #[Override]
    public function isLast(): bool
    {
        return $this->last;
    }

    /**
     * @return array{0: int, 1: array<string, list<string>>}|null
     */
    #[Override]
    public function getInformationalStatus(): ?array
    {
        return null;
    }

    #[Override]
    public function getContent(): string
    {
        return $this->content;
    }

    #[Override]
    public function getOffset(): int
    {
        return $this->offset;
    }

    #[Override]
    public function getError(): ?string
    {
        return null;
    }
}
