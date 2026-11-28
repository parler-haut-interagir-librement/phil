<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

use DateTimeImmutable;
use Phil\ContentAccess\Exception\InvalidPolicy;
use Phil\Rules\Rule;

/**
 * What a content asks of its reader: its audience — public, members, or whoever a rule lets in —, an optional instant
 * from which it becomes public for good (an early access), whether a viewer without the right sees its public part
 * before the cut, and whether such a viewer sees a locked entry or nothing at all.
 *
 * Immutable: the `with…()` methods return a new policy. A contradiction — a rule on a public content, a preview of a
 * hidden one — is refused when the policy is built.
 */
final readonly class AccessPolicy
{
    /**
     * @throws InvalidPolicy when the audience and the options contradict each other
     */
    private function __construct(
        public Audience $audience,
        public ?Rule $rule = null,
        public ?DateTimeImmutable $publicFrom = null,
        public bool $preview = false,
        public WhenDenied $whenDenied = WhenDenied::Locked,
    ) {
        if (Audience::Restricted === $audience && !$rule instanceof Rule) {
            throw InvalidPolicy::contradiction('a restricted content needs a rule');
        }

        if (Audience::Restricted !== $audience && $rule instanceof Rule) {
            throw InvalidPolicy::contradiction('only a restricted content has a rule');
        }

        if (Audience::Public === $audience && ($publicFrom instanceof DateTimeImmutable || $preview || WhenDenied::Hidden === $whenDenied)) {
            throw InvalidPolicy::contradiction('a public content has no early access, preview or hiding');
        }

        if ($preview && WhenDenied::Hidden === $whenDenied) {
            throw InvalidPolicy::contradiction('a hidden content shows no preview');
        }
    }

    public static function public(): self
    {
        return new self(Audience::Public);
    }

    public static function members(): self
    {
        return new self(Audience::Members);
    }

    /**
     * A content whose rule decides who reads it; build the rule on `AccessConditions::types()`.
     */
    public static function restricted(Rule $rule): self
    {
        return new self(Audience::Restricted, $rule);
    }

    /**
     * The same policy, public for good from that instant: before it, the audience applies; from it on, everyone reads.
     */
    public function withPublicFrom(?DateTimeImmutable $instant): self
    {
        return new self($this->audience, $this->rule, $instant, $this->preview, $this->whenDenied);
    }

    /**
     * The same policy, showing the public part before the cut to a viewer without the right.
     */
    public function withPreview(bool $preview = true): self
    {
        return new self($this->audience, $this->rule, $this->publicFrom, $preview, $this->whenDenied);
    }

    /**
     * The same policy, showing a viewer without the right a locked entry or nothing at all.
     */
    public function withWhenDenied(WhenDenied $whenDenied): self
    {
        return new self($this->audience, $this->rule, $this->publicFrom, $this->preview, $whenDenied);
    }

    /**
     * Whether everyone reads the content at that instant: public, or past the end of its early access.
     */
    public function isPublicAt(DateTimeImmutable $at): bool
    {
        return Audience::Public === $this->audience || ($this->publicFrom instanceof DateTimeImmutable && $this->publicFrom <= $at);
    }
}
