<?php

declare(strict_types=1);

namespace Phil\BounceParser;

use Phil\BounceParser\Mime\Part;

/**
 * What one message received on the bounce mailbox says: how it was recognised, the kind of bounce, the action for the
 * application, each recipient it speaks of, the abuse report, the message it is about, and the rule that decided.
 */
final readonly class BounceReport
{
    /**
     * @param list<Recipient> $recipients
     * @param string|null     $verpTag    the tag of the bounce address that received it, decoded by the `Verp` given
     * @param string|null     $rule       the name of the rule that decided the kind and the action
     * @param bool            $authentic  whether the authenticator vouched for the abuse report; `false` otherwise
     */
    public function __construct(
        public ReportFormat $format,
        public BounceKind $kind,
        public BounceAction $action,
        public Part $message,
        public array $recipients = [],
        public ?FeedbackReport $feedback = null,
        public ?OriginalMessage $original = null,
        public ?string $verpTag = null,
        public ?string $rule = null,
        public bool $authentic = false,
    ) {
    }

    /**
     * Whether the parser or a rule recognised the message.
     */
    public function isRecognised(): bool
    {
        return BounceKind::Unknown !== $this->kind;
    }

    /**
     * The addresses of the recipients the message failed for, or of all of them when none failed.
     *
     * @return list<string>
     */
    public function addresses(): array
    {
        $failed = array_filter($this->recipients, static fn (Recipient $recipient): bool => \in_array($recipient->kind, [BounceKind::Hard, BounceKind::Soft], true));

        return array_values(array_unique(array_map(static fn (Recipient $recipient): string => $recipient->address, [] === $failed ? $this->recipients : $failed)));
    }

    /**
     * A copy decided by a rule: its kind, its action and its name.
     */
    public function decidedBy(string $rule, BounceKind $kind, BounceAction $action): self
    {
        return new self($this->format, $kind, $action, $this->message, $this->recipients, $this->feedback, $this->original, $this->verpTag, $rule, $this->authentic);
    }
}
