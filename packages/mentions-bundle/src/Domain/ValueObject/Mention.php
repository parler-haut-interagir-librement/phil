<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\ValueObject;

use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\Resource\ResourceReference;

/**
 * One mention found in a text: a handle typed after `@` in plain text or Markdown, at a character offset, which the
 * application's directory resolves to a member; or a mention mark of a UJO (Universal JSON Output) document, which
 * already names its member by reference and shows a label.
 */
final readonly class Mention
{
    private function __construct(
        public ?string $handle,
        public ?ResourceReference $member,
        public string $label,
        public ?int $offset,
    ) {
    }

    /**
     * @param string $handle the handle as typed, without its `@`
     * @param int    $offset the character offset of the `@` in the text
     *
     * @throws InvalidMention when the handle is empty or the offset negative
     */
    public static function ofHandle(string $handle, int $offset): self
    {
        if ('' === $handle) {
            throw InvalidMention::because('A mention names a handle.');
        }

        if ($offset < 0) {
            throw InvalidMention::because('The offset of a mention is not negative.');
        }

        return new self($handle, null, '@'.$handle, $offset);
    }

    /**
     * @param string $label the text the mark shows, such as `@Ada`
     */
    public static function ofMember(ResourceReference $member, string $label): self
    {
        return new self(null, $member, $label, null);
    }

    /**
     * Whether the mention is a mark that names its member, rather than a handle to resolve.
     *
     * @phpstan-assert-if-true !null $this->member
     *
     * @phpstan-assert-if-false !null $this->handle
     */
    public function isMark(): bool
    {
        return $this->member instanceof ResourceReference;
    }

    /**
     * What two mentions of the same member share: `member:user:42` for a mark, `handle:ada` for a handle, compared
     * without case.
     */
    public function key(): string
    {
        return $this->member instanceof ResourceReference ? 'member:'.$this->member->toString() : 'handle:'.mb_strtolower((string) $this->handle);
    }
}
