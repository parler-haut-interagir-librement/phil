<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi;

use Phil\ApiContract\Contract\OpenApiRule;

/**
 * One place where a document breaks one rule: the rule's id, its severity, the JSON Pointer (RFC 6901) of the place
 * in the document, and a sentence that says what is wrong there.
 *
 * The location and the message quote the document, which anyone may have written: each is kept to valid UTF-8 and
 * 1024 characters, a control character replaced by U+FFFD, so that a report printed in a terminal or a CI log shows
 * the document's text without obeying it.
 */
final readonly class Finding
{
    public const int MAX_LENGTH = 1024;

    public string $location;

    public string $message;

    public function __construct(
        public string $ruleId,
        public Severity $severity,
        string $location,
        string $message,
    ) {
        $this->location = $this->printable($location);
        $this->message = $this->printable($message);
    }

    public static function of(OpenApiRule $rule, string $location, string $message): self
    {
        return new self($rule->id(), $rule->severity(), $location, $message);
    }

    private function printable(string $text): string
    {
        // Text that is not UTF-8 keeps its ASCII; each other byte becomes U+FFFD.
        $text = 1 === preg_match('//u', $text) ? $text : (string) preg_replace('/[\x80-\xFF]/', "\u{FFFD}", $text);
        $text = (string) preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}]/u', "\u{FFFD}", $text);

        return 1 === preg_match('/^.{'.self::MAX_LENGTH.'}./su', $text) ? preg_replace('/^(.{'.(self::MAX_LENGTH - 1).'}).*$/su', '$1…', $text) ?? '' : $text;
    }

    /**
     * @return array{rule: string, severity: string, location: string, message: string}
     */
    public function toArray(): array
    {
        return ['rule' => $this->ruleId, 'severity' => $this->severity->value, 'location' => $this->location, 'message' => $this->message];
    }
}
