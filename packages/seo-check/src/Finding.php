<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

/**
 * One point to correct: the rule that found it, how much it matters, what it says, and what it is about — a link, an
 * image, the path of a rendering —, when it is about one thing of the page.
 */
final readonly class Finding
{
    public function __construct(
        public string $rule,
        public Severity $severity,
        public Message $message,
        public ?string $subject = null,
    ) {
    }

    /**
     * @return array{rule: string, severity: string, message: string, messageKey: string, parameters: array<string, int|string>, subject: ?string}
     */
    public function toArray(string $message): array
    {
        return [
            'rule' => $this->rule,
            'severity' => $this->severity->value,
            'message' => $message,
            'messageKey' => $this->message->key,
            'parameters' => $this->message->parameters,
            'subject' => $this->subject,
        ];
    }
}
