<?php

declare(strict_types=1);

namespace Phil\EmailPreview;

use Countable;
use JsonSerializable;
use Override;

/**
 * What a check of an email found: the rules that ran, in order, and their findings, rule after rule. It is shown beside
 * the preview as text or as an HTML fragment, or given as JSON.
 */
final readonly class Report implements Countable, JsonSerializable
{
    /**
     * @param list<string>  $rules    the names of the rules that ran
     * @param list<Finding> $findings
     */
    public function __construct(
        public array $rules = [],
        public array $findings = [],
    ) {
    }

    /**
     * Whether the email may be sent: no finding is an error, or none at all when warnings count.
     */
    public function passes(bool $strict = false): bool
    {
        return [] === ($strict ? $this->findings : $this->errors());
    }

    /**
     * @return list<Finding>
     */
    public function errors(): array
    {
        return $this->withSeverity(Severity::Error);
    }

    /**
     * @return list<Finding>
     */
    public function warnings(): array
    {
        return $this->withSeverity(Severity::Warning);
    }

    /**
     * @return list<Finding>
     */
    public function ofRule(string $rule): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $finding): bool => $rule === $finding->rule));
    }

    /**
     * The report as plain text: one finding a line, or a line that says the email passes every rule.
     */
    public function toText(): string
    {
        if ([] === $this->findings) {
            return \sprintf('No finding: the email passes the %d rules.', \count($this->rules));
        }

        return implode("\n", array_map(static fn (Finding $finding): string => $finding->toText(), $this->findings));
    }

    /**
     * The report as an HTML fragment for the page of the preview, every value escaped: a list of the findings, each
     * marked with its severity and its rule.
     */
    public function toHtml(): string
    {
        if ([] === $this->findings) {
            return '<p class="email-preview-report is-passed">'.$this->escape($this->toText()).'</p>';
        }

        $items = '';

        foreach ($this->findings as $finding) {
            $items .= \sprintf(
                '<li data-severity="%s" data-rule="%s"><strong>%s</strong> %s%s</li>',
                $finding->severity->value,
                $this->escape($finding->rule),
                $this->escape($finding->rule),
                $this->escape($finding->message),
                null === $finding->subject ? '' : ' <code>'.$this->escape($finding->subject).'</code>',
            );
        }

        return '<ul class="email-preview-report">'.$items.'</ul>';
    }

    #[Override]
    public function count(): int
    {
        return \count($this->findings);
    }

    /**
     * @return array{passed: bool, counts: array{error: int, warning: int}, rules: list<string>, findings: list<array{rule: string, severity: string, message: string, subject: ?string}>}
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passes(),
            'counts' => ['error' => \count($this->errors()), 'warning' => \count($this->warnings())],
            'rules' => $this->rules,
            'findings' => array_map(static fn (Finding $finding): array => $finding->toArray(), $this->findings),
        ];
    }

    /**
     * @return array{passed: bool, counts: array{error: int, warning: int}, rules: list<string>, findings: list<array{rule: string, severity: string, message: string, subject: ?string}>}
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return list<Finding>
     */
    private function withSeverity(Severity $severity): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $finding): bool => $severity === $finding->severity));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
