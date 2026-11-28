<?php

declare(strict_types=1);

namespace Phil\EmailPreview;

/**
 * One problem a rule found in an email: the rule, how grave it is, what it says, and what it is about — the address of
 * a link, the element and its value — when it is about one thing.
 */
final readonly class Finding
{
    public function __construct(
        public string $rule,
        public Severity $severity,
        public string $message,
        public ?string $subject = null,
    ) {
    }

    public static function error(string $rule, string $message, ?string $subject = null): self
    {
        return new self($rule, Severity::Error, $message, $subject);
    }

    public static function warning(string $rule, string $message, ?string $subject = null): self
    {
        return new self($rule, Severity::Warning, $message, $subject);
    }

    /**
     * The finding on one line: its severity, its rule, its message and what it is about.
     */
    public function toText(): string
    {
        return \sprintf('[%s] %s: %s', $this->severity->value, $this->rule, $this->message).(null === $this->subject ? '' : ' ('.$this->subject.')');
    }

    /**
     * @return array{rule: string, severity: string, message: string, subject: ?string}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'subject' => $this->subject,
        ];
    }
}
