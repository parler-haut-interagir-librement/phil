<?php

declare(strict_types=1);

namespace Phil\BounceParser\Mime;

/**
 * A message or one of its MIME parts: its header fields, its media type and parameters, its body with its transfer
 * encoding undone, its child parts when it is a multipart, and the message it holds when it is a `message/rfc822`.
 */
final readonly class Part
{
    /**
     * @param string                $type       the media type in lowercase, `text/plain` when none is given
     * @param array<string, string> $parameters the parameters of its type, by name in lowercase
     * @param list<Part>            $parts
     */
    public function __construct(
        public Headers $headers,
        public string $type,
        public array $parameters,
        public string $body,
        public array $parts = [],
        public ?self $message = null,
    ) {
    }

    /**
     * This part and every part under it, depth first, the parts of the messages it holds included only when asked.
     *
     * @return iterable<Part>
     */
    public function walk(bool $intoMessages = false): iterable
    {
        yield $this;

        foreach ($this->parts as $part) {
            yield from $part->walk($intoMessages);
        }

        if ($intoMessages && $this->message instanceof self) {
            yield from $this->message->walk(true);
        }
    }

    /**
     * The first part of that media type, this one included, without entering the messages it holds.
     */
    public function find(string $type): ?self
    {
        foreach ($this->walk() as $part) {
            if ($part->type === $type) {
                return $part;
            }
        }

        return null;
    }
}
