<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

/**
 * One thing the preflight found: its severity, the code of its rule, a sentence in English, the page it is on (none
 * for the document as a whole), the element or font it is about, and the values the sentence quotes, for an
 * application that writes its own messages.
 */
final readonly class Finding
{
    /**
     * @param array<string, int|float|string> $parameters
     */
    public function __construct(
        public Severity $severity,
        public string $code,
        public string $message,
        public ?int $page = null,
        public ?string $subject = null,
        public array $parameters = [],
    ) {
    }

    /**
     * A finding of a built-in rule, at the rule's own severity.
     *
     * @param array<string, int|float|string> $parameters
     */
    public static function of(Rule $rule, string $message, ?int $page = null, ?string $subject = null, array $parameters = []): self
    {
        return new self($rule->severity(), $rule->value, $message, $page, $subject, $parameters);
    }

    public function withSeverity(Severity $severity): self
    {
        return new self($severity, $this->code, $this->message, $this->page, $this->subject, $this->parameters);
    }

    /**
     * @return array{severity: string, code: string, message: string, page: ?int, subject: ?string, parameters: array<string, int|float|string>}
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity->value,
            'code' => $this->code,
            'message' => $this->message,
            'page' => $this->page,
            'subject' => $this->subject,
            'parameters' => $this->parameters,
        ];
    }
}
